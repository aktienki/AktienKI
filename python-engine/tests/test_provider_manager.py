from __future__ import annotations

import pytest

from app.providers.daily_market_provider import DailyMarketProvider
from app.providers.hourly_market_provider import HourlyMarketProvider
from app.providers.provider_manager import ProviderManager
from app.strategies.models import StrategyProfile


def make_profile(*, interval: str) -> StrategyProfile:
    return StrategyProfile(
        id=1,
        code="TEST",
        name="Test",
        target_horizon_days=5,
        interval=interval,
        history_years=3,
        version=1,
        configuration={},
        allowed_algorithms=["xgboost"],
        instruments=[],
    )


def test_daily_strategy_uses_daily_provider():
    provider = ProviderManager.from_strategy(
        make_profile(interval="1d")
    )

    assert isinstance(provider, DailyMarketProvider)


def test_hourly_strategy_uses_hourly_provider():
    provider = ProviderManager.from_strategy(
        make_profile(interval="1h")
    )

    assert isinstance(provider, HourlyMarketProvider)


def test_interval_is_normalized():
    provider = ProviderManager.from_strategy(
        make_profile(interval=" 1H ")
    )

    assert isinstance(provider, HourlyMarketProvider)


def test_unknown_interval_is_rejected():
    with pytest.raises(
        RuntimeError,
        match="Kein Provider-Scope für Intervall",
    ):
        ProviderManager.from_strategy(
            make_profile(interval="15m")
        )
