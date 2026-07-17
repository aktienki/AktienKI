from __future__ import annotations
from dataclasses import asdict
from datetime import datetime, timezone
import math
import pandas as pd
import yfinance as yf
from .config import MARKET_INSTRUMENTS

def _number(value):
    try:
        value=float(value); return None if math.isnan(value) or math.isinf(value) else value
    except (TypeError,ValueError): return None

def collect_market_assets(period:str='6mo', interval:str='1d')->list[dict]:
    result=[]
    for item in MARKET_INSTRUMENTS:
        frame=yf.download(item.symbol,period=period,interval=interval,auto_adjust=False,progress=False,threads=False)
        if frame.empty: continue
        if isinstance(frame.columns,pd.MultiIndex): frame.columns=frame.columns.get_level_values(0)
        close=frame['Close'].dropna()
        if close.empty: continue
        price=float(close.iloc[-1]); previous=float(close.iloc[-2]) if len(close)>1 else price
        change=((price/previous)-1)*100 if previous else 0.0
        ma20=float(close.tail(20).mean()); ma50=float(close.tail(50).mean())
        trend='BULLISH' if price>ma20>ma50 else ('BEARISH' if price<ma20<ma50 else 'NEUTRAL')
        score=max(0,min(100,50+change*4+(10 if trend=='BULLISH' else -10 if trend=='BEARISH' else 0)))
        signal='BUY' if score>=65 else ('SELL' if score<=35 else 'HOLD')
        result.append({**asdict(item),'price':_number(price),'change_percent':_number(change),'volume':_number(frame['Volume'].iloc[-1]) if 'Volume' in frame else None,'trend':trend,'signal':signal,'score':round(score,2),'observed_at':datetime.now(timezone.utc).isoformat()})
    return result
