from sqlalchemy import MetaData, Table, Column, BigInteger, String, Boolean, DateTime, Numeric, ForeignKey, UniqueConstraint

metadata = MetaData()

instruments = Table(
    "instruments", metadata,
    Column("id", BigInteger, primary_key=True),
    Column("exchange_id", BigInteger),
    Column("type", String(20), nullable=False),
    Column("symbol", String(30), nullable=False),
    Column("provider_symbol", String(60)),
    Column("name", String(255), nullable=False),
    Column("is_active", Boolean, nullable=False),
)

price_bars = Table(
    "price_bars", metadata,
    Column("id", BigInteger, primary_key=True),
    Column("instrument_id", BigInteger, ForeignKey("instruments.id", ondelete="CASCADE"), nullable=False),
    Column("interval", String(10), nullable=False),
    Column("bar_time", DateTime(timezone=True), nullable=False),
    Column("open", Numeric(24,10), nullable=False),
    Column("high", Numeric(24,10), nullable=False),
    Column("low", Numeric(24,10), nullable=False),
    Column("close", Numeric(24,10), nullable=False),
    Column("adjusted_close", Numeric(24,10)),
    Column("volume", Numeric(28,4)),
    Column("source", String(30)),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("updated_at", DateTime(timezone=True), nullable=False),
    UniqueConstraint("instrument_id", "interval", "bar_time", name="price_bars_instrument_id_interval_bar_time_unique"),
)
