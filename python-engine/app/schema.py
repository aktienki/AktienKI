from __future__ import annotations

"""
SQLAlchemy-Core-Schema für die Python-Engine.

Quelle:
Laravel-Migrationen aus database/migrations.

Wichtig:
- Dieses Modul bildet nur die Tabellen ab, die von der Python-Engine
  aktuell direkt über app.database.schema importiert werden.
- Laravel bleibt für Migrationen und Änderungen die führende Instanz.
- Nach Änderungen an den Migrationen muss diese Datei synchronisiert
  und mit tests/test_database_schema.py geprüft werden.
"""

from sqlalchemy import (
    BigInteger,
    Boolean,
    Column,
    DateTime,
    ForeignKey,
    JSON,
    MetaData,
    Numeric,
    String,
    Table,
    Text,
    UniqueConstraint,
)

metadata = MetaData()


exchanges = Table(
    "exchanges",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("code", String(20), nullable=False),
    Column("mic", String(20), nullable=True),
    Column("name", String(255), nullable=False),
    Column("country", String(2), nullable=False),
    Column("currency", String(3), nullable=False),
    Column("timezone", String(255), nullable=False),
    Column("is_active", Boolean, nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=True),
    Column("updated_at", DateTime(timezone=True), nullable=True),
)


instruments = Table(
    "instruments",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column(
        "exchange_id",
        BigInteger,
        ForeignKey("exchanges.id", ondelete="SET NULL"),
        nullable=True,
    ),
    Column("type", String(20), nullable=False),
    Column("symbol", String(30), nullable=False),
    Column("provider_symbol", String(60), nullable=True),
    Column("isin", String(20), nullable=True),
    Column("name", String(255), nullable=False),
    Column("short_name", String(255), nullable=True),
    Column("country", String(2), nullable=True),
    Column("currency", String(3), nullable=True),
    Column("sector", String(255), nullable=True),
    Column("industry", String(255), nullable=True),
    Column("market_cap", Numeric(24, 2), nullable=True),
    Column("is_active", Boolean, nullable=False),
    Column("is_tradeable", Boolean, nullable=False),
    Column("meta", JSON, nullable=True),
    Column("created_at", DateTime(timezone=True), nullable=True),
    Column("updated_at", DateTime(timezone=True), nullable=True),
    Column("deleted_at", DateTime(timezone=True), nullable=True),
    UniqueConstraint(
        "exchange_id",
        "symbol",
        "type",
        name="instruments_exchange_id_symbol_type_unique",
    ),
)


price_bars = Table(
    "price_bars",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column(
        "instrument_id",
        BigInteger,
        ForeignKey("instruments.id", ondelete="CASCADE"),
        nullable=False,
    ),
    Column("interval", String(10), nullable=False),
    Column("bar_time", DateTime(timezone=True), nullable=False),
    Column("open", Numeric(24, 10), nullable=False),
    Column("high", Numeric(24, 10), nullable=False),
    Column("low", Numeric(24, 10), nullable=False),
    Column("close", Numeric(24, 10), nullable=False),
    Column("adjusted_close", Numeric(24, 10), nullable=True),
    Column("volume", Numeric(28, 4), nullable=True),
    Column("source", String(30), nullable=True),
    Column("created_at", DateTime(timezone=True), nullable=True),
    Column("updated_at", DateTime(timezone=True), nullable=True),
    UniqueConstraint(
        "instrument_id",
        "interval",
        "bar_time",
        name="price_bars_instrument_id_interval_bar_time_unique",
    ),
)


# Stand der aktiven Migration:
# 2026_07_13_000001_create_market_snapshots_table.php
#
# Hinweis:
# MarketSnapshotRepository aus dem hochgeladenen Projekt verwendet noch
# die älteren Felder "market_data" und "feature_data". Dieses Repository
# muss vor der nächsten Nutzung an diese Migration angepasst werden.
market_snapshots = Table(
    "market_snapshots",
    metadata,
    Column("id", BigInteger, primary_key=True),
    Column("snapshot_time", DateTime(timezone=True), nullable=False),
    Column("market_score", Numeric(5, 2), nullable=True),
    Column("risk_mode", String(20), nullable=False),
    Column("market_trend", String(20), nullable=False),
    Column("volatility", Numeric(10, 4), nullable=True),
    Column("breadth_score", Numeric(5, 2), nullable=True),
    Column("buy_signals", BigInteger, nullable=False),
    Column("sell_signals", BigInteger, nullable=False),
    Column("hold_signals", BigInteger, nullable=False),
    Column("winning_sectors", JSON, nullable=True),
    Column("losing_sectors", JSON, nullable=True),
    Column("metadata", JSON, nullable=True),
    Column("created_at", DateTime(timezone=True), nullable=True),
    Column("updated_at", DateTime(timezone=True), nullable=True),
)
