from __future__ import annotations
from datetime import datetime, timezone
from .breadth import calculate_breadth
from .sectors import calculate_sectors

def build_snapshot(assets:list[dict], predictions:list[dict])->dict:
    breadth=calculate_breadth(predictions); sectors=calculate_sectors(predictions)
    weighted=[]
    for a in assets:
        score=float(a.get('score') or 50); weight=float(a.get('weight') or 1)
        if a.get('symbol')=='^VIX': score=100-score
        weighted.append((score,weight))
    asset_score=sum(s*w for s,w in weighted)/sum(w for _,w in weighted) if weighted else 50
    market_score=round(0.55*asset_score+0.45*breadth['breadth_score'],2)
    vix=next((a.get('price') for a in assets if a.get('symbol')=='^VIX'),None)
    risk_mode='RISK_ON' if market_score>=62 and (vix is None or vix<22) else ('RISK_OFF' if market_score<=38 or (vix is not None and vix>=30) else 'NEUTRAL')
    trend='BULLISH' if market_score>=60 else ('BEARISH' if market_score<=40 else 'NEUTRAL')
    return {'snapshot_time':datetime.now(timezone.utc).isoformat(),'market_score':market_score,'risk_mode':risk_mode,'market_trend':trend,'volatility':vix,'breadth_score':breadth['breadth_score'],'buy_signals':breadth['buy_count'],'sell_signals':breadth['sell_count'],'hold_signals':breadth['hold_count'],'winning_sectors':[s['sector'] for s in sectors[:3]],'losing_sectors':[s['sector'] for s in sectors[-3:]],'assets':assets,'sectors':sectors,'statistics':breadth}
