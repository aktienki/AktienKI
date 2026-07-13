import numpy as np
import pandas as pd

from app.features.feature_builder import FeatureBuilder


def test_targets_are_calculated() -> None:
    rows = 40
    index = pd.date_range(
        "2026-01-01",
        periods=rows,
        freq="D",
        tz="UTC",
    )

    frame = pd.DataFrame(
        {
            "close": np.linspace(100, 140, rows),
            "volume": np.linspace(
                1_000_000,
                1_200_000,
                rows,
            ),
            "rsi_14": 50,
            "ema_20": 100,
            "ema_50": 100,
            "ema_200": 100,
            "macd": 1,
            "atr_14": 2,
            "volatility_20": 20,
        },
        index=index,
    )

    result = FeatureBuilder().build(frame)

    assert "target_return_5d" in result.columns
    assert result.iloc[0]["target_return_5d"] > 0
    assert pd.isna(result.iloc[-1]["target_return_5d"])
