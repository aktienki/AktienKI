import logging

logger = logging.getLogger("market_import")

def log_success(symbol: str):
    logger.info("Imported %s", symbol)

def log_failure(symbol: str, error: str):
    logger.error("Failed %s: %s", symbol, error)
