import yfinance as yf

class YahooProvider:

    def history(self,symbol:str,period:str="10y",interval:str="1d"):
        ticker=yf.Ticker(symbol)
        return ticker.history(period=period,interval=interval)

    def info(self,symbol:str):
        return yf.Ticker(symbol).info
