from market.providers.yahoo_provider import YahooProvider

class ImportService:

    def __init__(self):
        self.provider=YahooProvider()

    def download(self,symbol:str):
        return self.provider.history(symbol)
