from __future__ import annotations

import numpy as np
import pandas as pd

from app.features.cross_asset_feature_builder import CrossAssetFeatureBuilder
from app.features.market_feature_builder import MarketFeatureBuilder
from app.features.momentum_feature_builder import MomentumFeatureBuilder
from app.features.price_feature_builder import PriceFeatureBuilder
from app.features.target_builder import TargetBuilder
from app.features.trend_feature_builder import TrendFeatureBuilder
from app.features.volatility_feature_builder import VolatilityFeatureBuilder
from app.features.volume_feature_builder import VolumeFeatureBuilder


def make_frame(rows: int = 700) -> pd.DataFrame:
    rng = np.random.default_rng(42)
    index = pd.date_range("2021-01-01", periods=rows, freq="D", tz="UTC")

    returns = rng.normal(0.0005, 0.012, rows)
    close = 100 * np.exp(np.cumsum(returns))
    open_ = close * (1 + rng.normal(0, 0.002, rows))
    high = np.maximum(open_, close) * (1 + rng.uniform(0.001, 0.015, rows))
    low = np.minimum(open_, close) * (1 - rng.uniform(0.001, 0.015, rows))
    volume = rng.integers(500_000, 5_000_000, rows).astype(float)

    frame = pd.DataFrame(
        {
            "Open": open_,
            "High": high,
            "Low": low,
            "Close": close,
            "Adj Close": close,
            "Volume": volume,
        },
        index=index,
    )

    # Markt- und Cross-Asset-Reihen für den Integrationstest.
    frame["market_sp500_close"] = 4000 * np.exp(np.cumsum(rng.normal(0.0003, 0.009, rows)))
    frame["market_nasdaq_close"] = 13000 * np.exp(np.cumsum(rng.normal(0.0004, 0.012, rows)))
    frame["market_vix_close"] = np.maximum(10, 20 + rng.normal(0, 2, rows)).astype(float)

    frame["cross_sp500_close"] = frame["market_sp500_close"]
    frame["cross_nasdaq_close"] = frame["market_nasdaq_close"]
    frame["cross_vix_close"] = frame["market_vix_close"]
    frame["cross_gold_close"] = 1800 * np.exp(np.cumsum(rng.normal(0.0001, 0.006, rows)))
    frame["cross_oil_close"] = 70 * np.exp(np.cumsum(rng.normal(0.0001, 0.018, rows)))
    frame["cross_dxy_close"] = 100 * np.exp(np.cumsum(rng.normal(0.0, 0.003, rows)))
    frame["cross_us2y_close"] = 3.0 + rng.normal(0, 0.08, rows)
    frame["cross_us10y_close"] = 4.0 + rng.normal(0, 0.08, rows)

    return frame


def test_complete_modular_feature_pipeline() -> None:
    frame = make_frame()

    builders = (
        PriceFeatureBuilder(),
        TrendFeatureBuilder(),
        MomentumFeatureBuilder(),
        VolatilityFeatureBuilder(),
        VolumeFeatureBuilder(),
        MarketFeatureBuilder(),
        CrossAssetFeatureBuilder(),
    )

    result = frame
    for builder in builders:
        result = builder.transform(result)

    result = TargetBuilder.build_multi_horizon(result)

    assert len(result) == len(frame)
    assert result.columns.is_unique
    assert "target_return_1d" in result.columns
    assert "target_return_5d" in result.columns
    assert "target_return_20d" in result.columns
    assert "target_return_60d" in result.columns
    assert "target_short_return_5d" in result.columns
    assert "target_expected_return_5d" in result.columns
    assert "market_risk_on_score" in result.columns
    assert "cross_risk_on_score" in result.columns
    assert "trend_macd" in result.columns
    assert "momentum_rsi_14" in result.columns
    assert "volatility_atr_14" in result.columns
    assert "volume_obv" in result.columns

    numeric = result.select_dtypes(include=[np.number])
    assert not np.isinf(numeric.to_numpy()).any()

    usable = result.dropna(subset=["target_return_60d"])
    assert len(usable) >= 500
    assert len(result.columns) >= 150


def test_target_builder_has_no_future_values_in_last_horizon_rows() -> None:
    frame = make_frame(rows=200)
    result = TargetBuilder.build_multi_horizon(frame)

    assert result["target_return_60d"].tail(60).isna().all()
    assert result["target_max_gain_60d"].tail(60).isna().all()
    assert result["target_max_loss_60d"].tail(60).isna().all()


def test_pipeline_is_deterministic() -> None:
    frame = make_frame(rows=400)

    first = PriceFeatureBuilder().transform(frame)
    first = TrendFeatureBuilder().transform(first)

    second = PriceFeatureBuilder().transform(frame)
    second = TrendFeatureBuilder().transform(second)

    pd.testing.assert_frame_equal(first, second)
