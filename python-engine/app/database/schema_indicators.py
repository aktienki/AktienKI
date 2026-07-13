from sqlalchemy import (
    BigInteger,
    Column,
    DateTime,
    ForeignKey,
    MetaData,
    Numeric,
    String,
    Table,
    UniqueConstraint,
)

metadata = MetaData()

technical_indicators = Table(
    "technical_indicators",
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

    Column("sma_20", Numeric(24, 10)),
    Column("sma_50", Numeric(24, 10)),
    Column("sma_200", Numeric(24, 10)),

    Column("ema_12", Numeric(24, 10)),
    Column("ema_20", Numeric(24, 10)),
    Column("ema_26", Numeric(24, 10)),
    Column("ema_50", Numeric(24, 10)),
    Column("ema_200", Numeric(24, 10)),

    Column("rsi_14", Numeric(16, 10)),

    Column("macd", Numeric(24, 10)),
    Column("macd_signal", Numeric(24, 10)),
    Column("macd_histogram", Numeric(24, 10)),

    Column("atr_14", Numeric(24, 10)),
    Column("adx_14", Numeric(16, 10)),

    Column("bollinger_upper", Numeric(24, 10)),
    Column("bollinger_middle", Numeric(24, 10)),
    Column("bollinger_lower", Numeric(24, 10)),
    Column("bollinger_width", Numeric(16, 10)),

    Column("stochastic_k", Numeric(16, 10)),
    Column("stochastic_d", Numeric(16, 10)),

    Column("roc_12", Numeric(16, 10)),
    Column("momentum_10", Numeric(24, 10)),
    Column("volatility_20", Numeric(16, 10)),
    Column("volume_sma_20", Numeric(28, 4)),

    Column("calculation_version", String(32), nullable=False),
    Column("created_at", DateTime(timezone=True), nullable=False),
    Column("updated_at", DateTime(timezone=True), nullable=False),

    UniqueConstraint(
        "instrument_id",
        "interval",
        "bar_time",
        name="technical_indicators_instrument_interval_time_unique",
    ),
)
