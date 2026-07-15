from app.enums.model_scope import ModelScope

from app.providers.hourly_market_provider import HourlyMarketProvider
from app.providers.daily_market_provider import DailyMarketProvider


PROVIDER_REGISTRY = {

    ModelScope.SHORT_TERM: HourlyMarketProvider,

    ModelScope.LONG_TERM: DailyMarketProvider,

    ModelScope.MARKET: DailyMarketProvider,

}