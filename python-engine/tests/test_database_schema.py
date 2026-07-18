from __future__ import annotations

from app.database.schema import instruments, market_snapshots, price_bars


def test_instruments_matches_current_migration():
    assert set(instruments.c.keys()) == {
        "id",
        "exchange_id",
        "type",
        "symbol",
        "provider_symbol",
        "isin",
        "name",
        "short_name",
        "country",
        "currency",
        "sector",
        "industry",
        "market_cap",
        "is_active",
        "is_tradeable",
        "meta",
        "created_at",
        "updated_at",
        "deleted_at",
    }


def test_price_bars_matches_current_migration():
    assert set(price_bars.c.keys()) == {
        "id",
        "instrument_id",
        "interval",
        "bar_time",
        "open",
        "high",
        "low",
        "close",
        "adjusted_close",
        "volume",
        "source",
        "created_at",
        "updated_at",
    }


def test_market_snapshots_matches_current_migration():
    assert set(market_snapshots.c.keys()) == {
        "id",
        "snapshot_time",
        "market_score",
        "risk_mode",
        "market_trend",
        "volatility",
        "breadth_score",
        "buy_signals",
        "sell_signals",
        "hold_signals",
        "winning_sectors",
        "losing_sectors",
        "metadata",
        "created_at",
        "updated_at",
    }
