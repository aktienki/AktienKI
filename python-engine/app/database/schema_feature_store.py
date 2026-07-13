from sqlalchemy import (
    BigInteger,
    Column,
    DateTime,
    ForeignKey,
    MetaData,
    Numeric,
    SmallInteger,
    String,
    Table,
    UniqueConstraint,
)

metadata = MetaData()

feature_store = Table(
    "feature_store",
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

    Column("close", Numeric(24, 10), nullable=False),
    Column("volume", Numeric(28, 4)),

    Column("rsi_14", Numeric(16, 10)),
    Column("ema_20", Numeric(24, 10)),
    Column("ema_50", Numeric(24, 10)),
    Column("ema_200", Numeric(24, 10)),
    Column("macd", Numeric(24, 10)),
    Column("atr_14", Numeric(24, 10)),
    Column("volatility_20", Numeric(16, 10)),

    Column("target_return_1d", Numeric(16, 10)),
    Column("target_return_5d", Numeric(16, 10)),
    Column("target_return_20d", Numeric(16, 10)),
    Column("target_direction", SmallInteger),

    Column("feature_version", String(20), nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("updated_at", DateTime(timezone=True), nullable=False),

    UniqueConstraint(
        "instrument_id",
        "interval",
        "bar_time",
        name="feature_store_instrument_interval_time_unique",
    ),
)
