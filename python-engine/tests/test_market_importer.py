from decimal import Decimal
import pandas as pd
from app.core.market_importer import MarketImporter

def test_frame_to_bars():
    frame = pd.DataFrame(
        {"Open":[100.0], "High":[105.0], "Low":[99.0], "Close":[104.0], "Adj Close":[103.5], "Volume":[1000000]},
        index=pd.DatetimeIndex(["2026-07-09"])
    )
    bars = MarketImporter.frame_to_bars(frame, instrument_id=42, interval="1d")
    assert len(bars) == 1
    assert bars[0].close == Decimal("104.0")
    assert bars[0].bar_time.tzinfo is not None
