from app.enums.model_scope import ModelScope

from app.providers.hourly_market_provider import (
    HourlyMarketProvider,
)

from app.providers.daily_market_provider import (
    DailyMarketProvider,
)


class ProviderFactory:

    @staticmethod
    def create(scope):

        if scope == ModelScope.SHORT_TERM:

            return HourlyMarketProvider()

        if scope == ModelScope.LONG_TERM:

            return DailyMarketProvider()

        if scope == ModelScope.MARKET:

            return DailyMarketProvider()

        raise RuntimeError(

            f"Unknown scope: {scope}"

        )