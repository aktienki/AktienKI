from dataclasses import dataclass
@dataclass(frozen=True)
class MarketInstrument:
    symbol: str; name: str; category: str; weight: float = 1.0
MARKET_INSTRUMENTS=(MarketInstrument('^GSPC','S&P 500','index',1.25),MarketInstrument('^IXIC','NASDAQ','index',1.25),MarketInstrument('^GDAXI','DAX','index',1.0),MarketInstrument('^VIX','VIX','volatility',1.25),MarketInstrument('GC=F','Gold','commodity',0.75),MarketInstrument('CL=F','Öl WTI','commodity',0.75),MarketInstrument('EURUSD=X','EUR/USD','fx',0.75),MarketInstrument('^TNX','US 10Y','rates',1.0),MarketInstrument('BTC-USD','Bitcoin','crypto',0.5),MarketInstrument('DX-Y.NYB','US Dollar Index','fx',0.75))
