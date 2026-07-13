from market.services.import_service import ImportService

class HistoryImportPipeline:

    def __init__(self):
        self.importer = ImportService()

    def run(self, symbol: str, interval: str = "1d"):
        return self.importer.download(symbol)
