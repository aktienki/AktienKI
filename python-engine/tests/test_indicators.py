import numpy as np
import pandas as pd

from app.features.indicators import IndicatorCalculator


def price_frame(rows: int = 260) -> pd.DataFrame:
    index = pd.date_range(
        "2025-01-01",
        periods=rows,
        freq="D",
        tz="UTC",
    )

    close = pd.Series(
        np.linspace(100, 180, rows),
        index=index,
    )

    return pd.DataFrame(
        {
            "open": close - 1,
            "high": close + 2,
            "low": close - 2,
            "close": close,
            "adjusted_close": close,
            "volume": np.linspace(1_000_000, 1_500_000, rows),
        },
        index=index,
    )


def test_indicator_columns_exist() -> None:
    result = IndicatorCalculator.calculate(price_frame())

    expected = {
        "rsi_14",
        "macd",
        "macd_signal",
        "atr_14",
        "adx_14",
        "bollinger_upper",
        "stochastic_k",
        "roc_12",
        "volatility_20",
        "ema_200",
        "sma_200",
    }

    assert expected.issubset(set(result.columns))


def test_long_history_produces_sma_200() -> None:
    result = IndicatorCalculator.calculate(price_frame())

    assert not pd.isna(result.iloc[-1]["sma_200"])
    assert not pd.isna(result.iloc[-1]["ema_200"])
