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

from app.market_intelligence import MarketIntelligenceService


def load_environment() -> Path | None:
    """Load Python-specific settings first, then Laravel's .env as fallback."""
    candidates = (
        PROJECT_ROOT / ".env",
        PROJECT_ROOT.parent / "laravel" / ".env",
    )
    loaded_from: Path | None = None
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

    connection = os.getenv("DB_CONNECTION", "pgsql").lower()
    if connection not in {"pgsql", "postgres", "postgresql"}:
        raise RuntimeError(
            f"Nicht unterstützte DB_CONNECTION={connection!r}. "
            "Market Intelligence benötigt PostgreSQL."
        )

    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "5432")
    database = os.getenv("DB_DATABASE")
    username = os.getenv("DB_USERNAME")
    password = os.getenv("DB_PASSWORD", "")

    missing = [
        name for name, value in {
            "DB_DATABASE": database,
            "DB_USERNAME": username,
        }.items() if not value
    ]
    if missing:
        raise RuntimeError(
            "Fehlende Datenbankvariablen: " + ", ".join(missing) + ". "
            "Bitte python-engine/.env oder laravel/.env prüfen."
        )

    user_encoded = quote_plus(str(username))
    password_encoded = quote_plus(str(password))
    auth = user_encoded if password == "" else f"{user_encoded}:{password_encoded}"
    return f"postgresql+psycopg2://{auth}@{host}:{port}/{database}"


def main() -> int:
    env_file = load_environment()
    log_level = os.getenv("LOG_LEVEL", "INFO").upper()
    logging.basicConfig(
        level=getattr(logging, log_level, logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )

    database_url = build_database_url()
    engine = create_engine(database_url, pool_pre_ping=True, future=True)
    session_factory = sessionmaker(bind=engine, autoflush=False, expire_on_commit=False)

    if env_file:
        logging.info("Konfiguration geladen aus %s", env_file)
    else:
        logging.warning("Keine .env-Datei gefunden; verwende Prozess-Umgebungsvariablen.")

    service = MarketIntelligenceService(session_factory)
    result = service.run()
    print(json.dumps(result, indent=2, ensure_ascii=False, default=str))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        logging.exception("Market Intelligence konnte nicht ausgeführt werden: %s", exc)
        raise SystemExit(1) from exc
