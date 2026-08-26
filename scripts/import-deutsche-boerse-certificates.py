#!/usr/bin/env python3
"""Build and atomically publish the official Deutsche Boerse certificate catalogue."""

from __future__ import annotations

import csv
import io
import os
import sys
import zipfile
from pathlib import Path

import psycopg


ROOT = Path(os.environ.get("AKTIENKI_ROOT", "/home/aktienki/AktienKI"))
ARCHIVE = ROOT / "storage/imports/deutsche-boerse/t7-xfra-BFZ-allTradableInstruments-current.zip"
ENV_FILE = ROOT / "laravel/.env"


def env_values(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key] = value.strip().strip('"').strip("'")
    return values


def clean_date(value: str) -> str | None:
    value = value.strip()
    return value if len(value) == 10 and value[4] == "-" and value[7] == "-" else None


def clean_int(value: str) -> int | None:
    value = value.strip()
    return int(value) if value.isdigit() else None


def main() -> int:
    if not ARCHIVE.is_file():
        raise FileNotFoundError(ARCHIVE)

    env = env_values(ENV_FILE)
    connection_info = {
        "host": env.get("DB_HOST", "127.0.0.1"),
        "port": int(env.get("DB_PORT", "5432")),
        "dbname": env["DB_DATABASE"],
        "user": env["DB_USERNAME"],
        "password": env.get("DB_PASSWORD", ""),
    }

    with zipfile.ZipFile(ARCHIVE) as archive:
        csv_name = next(name for name in archive.namelist() if name.lower().endswith(".csv"))
        with archive.open(csv_name) as binary:
            text = io.TextIOWrapper(binary, encoding="utf-8-sig", errors="replace", newline="")
            market_line = next(text).strip()
            date_line = next(text).strip()
            reader = csv.DictReader(text, delimiter=";")

            with psycopg.connect(**connection_info) as connection:
                with connection.cursor() as cursor:
                    cursor.execute("DROP TABLE IF EXISTS deutsche_boerse_certificates_next")
                    cursor.execute(
                        """
                        CREATE UNLOGGED TABLE deutsche_boerse_certificates_next (
                            id bigserial PRIMARY KEY,
                            instrument_name text NOT NULL,
                            isin varchar(20) NOT NULL,
                            wkn varchar(20),
                            product_id bigint,
                            exchange_instrument_id bigint,
                            underlying_code bigint,
                            instrument_type varchar(20),
                            security_sub_type integer,
                            warrant_type varchar(30),
                            specialist text,
                            currency varchar(3),
                            issue_date date,
                            maturity_date date,
                            first_trading_date date,
                            last_trading_date date,
                            mic_code varchar(12),
                            source_market varchar(20),
                            source_date date,
                            source_file text NOT NULL,
                            imported_at timestamptz NOT NULL DEFAULT now()
                        )
                        """
                    )
                    copy_sql = """
                        COPY deutsche_boerse_certificates_next (
                            instrument_name, isin, wkn, product_id,
                            exchange_instrument_id, underlying_code, instrument_type,
                            security_sub_type, warrant_type, specialist, currency,
                            issue_date, maturity_date, first_trading_date,
                            last_trading_date, mic_code, source_market, source_date,
                            source_file
                        ) FROM STDIN
                    """
                    source_date_raw = date_line.split(";", 1)[-1].strip()
                    day, month, year = source_date_raw.split(".")
                    source_date = f"{year}-{month}-{day}"
                    source_market = market_line.split(";", 1)[-1].strip()
                    imported = 0
                    with cursor.copy(copy_sql) as copy:
                        for row in reader:
                            if row.get("Product Status") != "Active" or row.get("Instrument Status") != "Active":
                                continue
                            isin = (row.get("ISIN") or "").strip().upper()
                            name = (row.get("Instrument") or "").strip()
                            if not isin or not name:
                                continue
                            copy.write_row((
                                name,
                                isin,
                                (row.get("WKN") or "").strip() or None,
                                clean_int(row.get("Product ID") or ""),
                                clean_int(row.get("Instrument ID") or ""),
                                clean_int(row.get("Underlying") or ""),
                                (row.get("Instrument Type") or "").strip() or None,
                                clean_int(row.get("Security Sub Type") or ""),
                                (row.get("Warrant Type") or "").strip() or None,
                                (row.get("Specialist") or "").strip() or None,
                                (row.get("Currency") or "").strip() or None,
                                clean_date(row.get("Issue Date") or ""),
                                clean_date(row.get("Maturity Date") or ""),
                                clean_date(row.get("First Trading Date") or ""),
                                clean_date(row.get("Last Trading Date") or ""),
                                (row.get("MIC Code") or "").strip() or None,
                                source_market,
                                source_date,
                                csv_name,
                            ))
                            imported += 1

                    cursor.execute("CREATE UNIQUE INDEX db_certificates_next_isin ON deutsche_boerse_certificates_next (isin)")
                    cursor.execute("CREATE INDEX db_certificates_next_wkn ON deutsche_boerse_certificates_next (wkn)")
                    cursor.execute("CREATE INDEX db_certificates_next_underlying ON deutsche_boerse_certificates_next (underlying_code)")
                    cursor.execute("CREATE INDEX db_certificates_next_maturity ON deutsche_boerse_certificates_next (maturity_date)")
                    cursor.execute("CREATE INDEX db_certificates_next_type ON deutsche_boerse_certificates_next (warrant_type)")
                    cursor.execute(
                        "CREATE INDEX db_certificates_next_search ON deutsche_boerse_certificates_next "
                        "USING gin (to_tsvector('simple', coalesce(instrument_name,'') || ' ' || coalesce(isin,'') || ' ' || coalesce(wkn,'') || ' ' || coalesce(specialist,'')))"
                    )
                    cursor.execute("DROP TABLE IF EXISTS deutsche_boerse_certificates_previous")
                    cursor.execute(
                        """
                        DO $$ BEGIN
                            IF to_regclass('public.deutsche_boerse_certificates') IS NOT NULL THEN
                                ALTER TABLE deutsche_boerse_certificates RENAME TO deutsche_boerse_certificates_previous;
                            END IF;
                        END $$
                        """
                    )
                    cursor.execute("ALTER TABLE deutsche_boerse_certificates_next SET LOGGED")
                    cursor.execute("ALTER TABLE deutsche_boerse_certificates_next RENAME TO deutsche_boerse_certificates")
                    cursor.execute("DROP TABLE IF EXISTS deutsche_boerse_certificates_previous")
                    cursor.execute("ALTER INDEX db_certificates_next_isin RENAME TO db_certificates_isin")
                    cursor.execute("ALTER INDEX db_certificates_next_wkn RENAME TO db_certificates_wkn")
                    cursor.execute("ALTER INDEX db_certificates_next_underlying RENAME TO db_certificates_underlying")
                    cursor.execute("ALTER INDEX db_certificates_next_maturity RENAME TO db_certificates_maturity")
                    cursor.execute("ALTER INDEX db_certificates_next_type RENAME TO db_certificates_type")
                    cursor.execute("ALTER INDEX db_certificates_next_search RENAME TO db_certificates_search")
                connection.commit()

    print(f"Published {imported:,} active products from {csv_name} ({source_date}).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
