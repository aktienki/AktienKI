from __future__ import annotations

import argparse
from dataclasses import dataclass
from datetime import datetime, timezone
import os
from pathlib import Path
import shutil
import subprocess
import sys
from typing import Mapping, Sequence

from dotenv import dotenv_values
import psycopg
from psycopg import sql


VERIFY_TABLES = (
    "instruments",
    "instrument_fundamentals",
    "predictions",
    "trained_models",
    "training_runs",
    "migrations",
)


@dataclass(frozen=True, slots=True)
class DatabaseTarget:
    host: str
    port: int
    database: str
    user: str
    password: str

    @property
    def label(self) -> str:
        return f"{self.user}@{self.host}:{self.port}/{self.database}"

    @property
    def connection_kwargs(self) -> dict[str, object]:
        return {
            "host": self.host,
            "port": self.port,
            "dbname": self.database,
            "user": self.user,
            "password": self.password,
            "connect_timeout": 10,
        }


def _first(values: Mapping[str, str | None], *names: str) -> str | None:
    for name in names:
        value = values.get(name)
        if value is not None and str(value).strip():
            return str(value).strip()
    return None


def target_from_env(
    path: Path,
    *,
    prefix: str = "",
) -> DatabaseTarget:
    if not path.is_file():
        raise ValueError(f"Konfigurationsdatei fehlt: {path}")
    values = {
        str(key): None if value is None else str(value)
        for key, value in dotenv_values(path).items()
    }
    name = lambda key: f"{prefix}{key}"  # noqa: E731
    host = _first(values, name("DB_HOST"))
    port = _first(values, name("DB_PORT"))
    database = _first(values, name("DB_NAME"), name("DB_DATABASE"))
    user = _first(values, name("DB_USER"), name("DB_USERNAME"))
    password = _first(values, name("DB_PASSWORD")) or ""
    missing = [
        label
        for label, value in (
            ("DB_HOST", host),
            ("DB_PORT", port),
            ("DB_NAME/DB_DATABASE", database),
            ("DB_USER/DB_USERNAME", user),
        )
        if value is None
    ]
    if missing:
        raise ValueError(
            f"{path}: fehlende Datenbankwerte: {', '.join(missing)}"
        )
    try:
        numeric_port = int(str(port))
    except ValueError as exc:
        raise ValueError(f"{path}: DB_PORT ist ungültig") from exc
    if not 1 <= numeric_port <= 65535:
        raise ValueError(f"{path}: DB_PORT liegt außerhalb 1..65535")
    return DatabaseTarget(
        host=str(host),
        port=numeric_port,
        database=str(database),
        user=str(user),
        password=password,
    )


def _tool(name: str) -> str:
    executable = shutil.which(name)
    if executable is None:
        raise RuntimeError(f"Benötigtes PostgreSQL-Programm fehlt: {name}")
    return executable


def _run(
    command: Sequence[str],
    *,
    target: DatabaseTarget,
) -> None:
    environment = os.environ.copy()
    environment["PGPASSWORD"] = target.password
    subprocess.run(
        list(command),
        env=environment,
        check=True,
        stdin=subprocess.DEVNULL,
    )


def dump_command(
    target: DatabaseTarget,
    output: Path,
    *,
    exclude_price_data: bool,
) -> list[str]:
    command = [
        _tool("pg_dump"),
        "--format=custom",
        "--compress=9",
        "--no-owner",
        "--no-privileges",
        f"--host={target.host}",
        f"--port={target.port}",
        f"--username={target.user}",
        f"--dbname={target.database}",
        f"--file={output}",
    ]
    if exclude_price_data:
        command.append("--exclude-table-data=public.price_bars")
    return command


def restore_command(
    target: DatabaseTarget,
    dump: Path,
    *,
    replace: bool,
) -> list[str]:
    command = [
        _tool("pg_restore"),
        "--no-owner",
        "--no-privileges",
        "--single-transaction",
        "--exit-on-error",
        f"--host={target.host}",
        f"--port={target.port}",
        f"--username={target.user}",
        f"--dbname={target.database}",
    ]
    if replace:
        command.extend(("--clean", "--if-exists"))
    command.append(str(dump))
    return command


def _connect(target: DatabaseTarget):
    try:
        return psycopg.connect(**target.connection_kwargs)
    except psycopg.Error as exc:
        raise RuntimeError(
            f"Datenbankverbindung nicht erreichbar: {target.label}: {exc}"
        ) from exc


def database_counts(target: DatabaseTarget) -> dict[str, int]:
    with _connect(target) as connection, connection.cursor() as cursor:
        cursor.execute(
            """SELECT table_name
               FROM information_schema.tables
               WHERE table_schema='public' AND table_type='BASE TABLE'"""
        )
        existing = {str(row[0]) for row in cursor.fetchall()}
        counts: dict[str, int] = {}
        for table in VERIFY_TABLES:
            if table not in existing:
                counts[table] = -1
                continue
            cursor.execute(
                sql.SQL("SELECT COUNT(*) FROM {}").format(
                    sql.Identifier(table)
                )
            )
            counts[table] = int(cursor.fetchone()[0])
        if "price_bars" in existing:
            cursor.execute("SELECT COUNT(*) FROM price_bars")
            counts["price_bars"] = int(cursor.fetchone()[0])
        else:
            counts["price_bars"] = -1
        return counts


def database_is_empty(target: DatabaseTarget) -> bool:
    with _connect(target) as connection, connection.cursor() as cursor:
        cursor.execute(
            """SELECT COUNT(*)
               FROM information_schema.tables
               WHERE table_schema='public' AND table_type='BASE TABLE'
                 AND table_name <> 'migrations'"""
        )
        return int(cursor.fetchone()[0]) == 0


def same_endpoint(first: DatabaseTarget, second: DatabaseTarget) -> bool:
    return (
        first.host in {"127.0.0.1", "localhost"}
        and second.host in {"127.0.0.1", "localhost"}
        and first.port == second.port
        and first.database == second.database
    ) or (
        first.host == second.host
        and first.port == second.port
        and first.database == second.database
    )


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description=(
            "Synchronisiert PostgreSQL sicher und einseitig von lokal nach "
            "remote. Ohne --apply werden nur Verbindungen und Bestände geprüft."
        )
    )
    parser.add_argument(
        "--local-env", type=Path, default=Path(".env"),
        help="Lokale Engine-.env mit DB_HOST/DB_NAME/DB_USER",
    )
    parser.add_argument(
        "--remote-env", type=Path, required=True,
        help="Remote-Konfiguration, z. B. die Laravel-.env am SSH-Tunnel",
    )
    parser.add_argument(
        "--remote-prefix", default="",
        help="Optionaler Variablenpräfix, z. B. REMOTE_",
    )
    parser.add_argument(
        "--output-dir", type=Path, default=Path("data/exports"),
    )
    parser.add_argument(
        "--apply", action="store_true",
        help="Führt Dump und Restore tatsächlich aus",
    )
    parser.add_argument(
        "--replace-remote", action="store_true",
        help="Ersetzt eine bereits befüllte Remote-Datenbank vollständig",
    )
    parser.add_argument(
        "--skip-remote-backup", action="store_true",
        help="Überspringt das Sicherheitsbackup der Remote-Datenbank",
    )
    return parser


def main() -> int:
    args = _parser().parse_args()
    local = target_from_env(args.local_env)
    remote = target_from_env(
        args.remote_env,
        prefix=args.remote_prefix,
    )
    if same_endpoint(local, remote):
        raise RuntimeError("Quelle und Ziel zeigen auf dieselbe Datenbank")

    local_counts = database_counts(local)
    remote_counts = database_counts(remote)
    print(f"Quelle: {local.label}")
    print(f"Ziel:   {remote.label}")
    print(f"Quelle Bestände: {local_counts}")
    print(f"Ziel Bestände:   {remote_counts}")

    remote_empty = database_is_empty(remote)
    if not args.apply:
        print("PRÜFMODUS: keine Daten wurden verändert.")
        print(
            "Ausführen mit --apply"
            + (" --replace-remote" if not remote_empty else "")
        )
        return 0
    if not remote_empty and not args.replace_remote:
        raise RuntimeError(
            "Remote-Datenbank ist nicht leer. Für einen vollständigen Klon "
            "ist zusätzlich --replace-remote erforderlich."
        )

    args.output_dir.mkdir(parents=True, exist_ok=True)
    timestamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    if not remote_empty and not args.skip_remote_backup:
        remote_backup = args.output_dir / f"remote_before_sync_{timestamp}.dump"
        print(f"Remote-Sicherheitsbackup: {remote_backup}")
        _run(
            dump_command(
                remote,
                remote_backup,
                exclude_price_data=False,
            ),
            target=remote,
        )

    local_dump = args.output_dir / f"local_to_remote_{timestamp}.dump"
    print(f"Lokaler Transferdump ohne price_bars: {local_dump}")
    _run(
        dump_command(local, local_dump, exclude_price_data=True),
        target=local,
    )
    _run(
        restore_command(
            remote,
            local_dump,
            replace=not remote_empty,
        ),
        target=remote,
    )

    restored = database_counts(remote)
    mismatches = {
        table: (local_counts[table], restored[table])
        for table in VERIFY_TABLES
        if local_counts[table] != restored[table]
    }
    if restored["price_bars"] != 0:
        mismatches["price_bars"] = (0, restored["price_bars"])
    print(f"Remote nach Restore: {restored}")
    if mismatches:
        raise RuntimeError(f"Verifikation fehlgeschlagen: {mismatches}")
    print("SYNC_OK: Remote-Datenbank entspricht dem lokalen Snapshot.")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, RuntimeError, subprocess.CalledProcessError) as exc:
        print(f"SYNC_FAILED: {exc}", file=sys.stderr)
        raise SystemExit(1) from exc
