from __future__ import annotations

import json
import logging
import os
import sys
from pathlib import Path
from urllib.parse import quote_plus

from dotenv import load_dotenv
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

PROJECT_ROOT = Path(__file__).resolve().parent
if str(PROJECT_ROOT) not in sys.path:
    sys.path.insert(0, str(PROJECT_ROOT))

from app.market_assets.collector import MarketAssetCollector
from app.market_intelligence import MarketIntelligenceService


def load_environment() -> Path | None:
    candidates = (
        PROJECT_ROOT / ".env",
        PROJECT_ROOT.parent / "laravel" / ".env",
    )

    loaded_from = None
    for candidate in candidates:
        if candidate.is_file():
            load_dotenv(candidate, override=False)
            loaded_from = candidate

    return loaded_from


def build_database_url() -> str:
    explicit_url = os.getenv("DATABASE_URL") or os.getenv("DB_URL")
    if explicit_url:
        if explicit_url.startswith("postgres://"):
            return "postgresql+psycopg2://" + explicit_url[len("postgres://"):]
        if explicit_url.startswith("postgresql://"):
            return "postgresql+psycopg2://" + explicit_url[len("postgresql://"):]
        return explicit_url

    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "5432")
    database = os.getenv("DB_DATABASE")
    username = os.getenv("DB_USERNAME")
    password = os.getenv("DB_PASSWORD", "")

    auth = quote_plus(username)
    if password:
        auth += ":" + quote_plus(password)

    return f"postgresql+psycopg2://{auth}@{host}:{port}/{database}"


def configure_logging() -> None:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )

    # yfinance ruhig stellen
    logging.getLogger("yfinance").setLevel(logging.WARNING)
    logging.getLogger("peewee").setLevel(logging.WARNING)


def main() -> int:
    configure_logging()

    env = load_environment()
    if env:
        logging.info("Konfiguration geladen aus %s", env)

    engine = create_engine(build_database_url(), pool_pre_ping=True, future=True)
    session_factory = sessionmaker(bind=engine, autoflush=False, expire_on_commit=False)

    with session_factory() as session:
        MarketAssetCollector(session).run()
        session.commit()

    result = MarketIntelligenceService(session_factory).run()

    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())