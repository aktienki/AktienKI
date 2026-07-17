from __future__ import annotations

from datetime import datetime, timezone

import yfinance as yf
from sqlalchemy import text


ASSETS = {
    "^GSPC": "INDEX",
    "^IXIC": "INDEX",
    "^GDAXI": "INDEX",
    "^VIX": "VOLATILITY",
    "GC=F": "COMMODITY",
    "CL=F": "COMMODITY",
    "EURUSD=X": "FOREX",
    "^TNX": "BOND",
}


class MarketAssetCollector:
    def __init__(self, session):
        self.session = session

    def run(self):
        now = datetime.now(timezone.utc)

        for symbol, category in ASSETS.items():
            try:
                ticker = yf.Ticker(symbol)
                hist = ticker.history(period="2d", auto_adjust=False)

                if hist.empty or len(hist) < 2:
                    print(f"⚠️ Keine Daten für {symbol}")
                    continue

                price = float(hist["Close"].iloc[-1])
                previous = float(hist["Close"].iloc[-2])

                if previous == 0:
                    continue

                change = ((price - previous) / previous) * 100.0

                self.session.execute(
                    text("""
                        INSERT INTO market_assets (
                            symbol,
                            category,
                            price,
                            change_percent,
                            observed_at,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            :symbol,
                            :category,
                            :price,
                            :change,
                            :observed_at,
                            :created_at,
                            :updated_at
                        )
                    """),
                    {
                        "symbol": symbol,
                        "category": category,
                        "price": price,
                        "change": change,
                        "observed_at": now,
                        "created_at": now,
                        "updated_at": now,
                    },
                )

                print(f"✓ {symbol}: {price:.2f} ({change:+.2f}%)")

            except Exception as e:
                print(f"❌ Fehler bei {symbol}: {e}")