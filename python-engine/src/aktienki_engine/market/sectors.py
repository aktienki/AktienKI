from __future__ import annotations
from collections import defaultdict

def calculate_sectors(predictions:list[dict])->list[dict]:
    groups=defaultdict(list)
    for row in predictions:
        if row.get('sector'): groups[row['sector']].append(row)
    out=[]
    for sector,rows in groups.items():
        n=len(rows); scores=[float(r.get('prediction_score') or r.get('score') or 50) for r in rows]; returns=[float(r.get('predicted_return') or 0) for r in rows]
        buy=sum(str(r.get('signal','')).upper()=='BUY' for r in rows); sell=sum(str(r.get('signal','')).upper()=='SELL' for r in rows)
        avg_ret=sum(returns)/n; avg_score=sum(scores)/n
        out.append({'sector':sector,'average_return':avg_ret,'average_score':avg_score,'buy_ratio':buy/n,'sell_ratio':sell/n,'trend':'BULLISH' if avg_ret>0.005 else ('BEARISH' if avg_ret<-0.005 else 'NEUTRAL'),'companies_count':n})
    out.sort(key=lambda x:(x['average_score'],x['average_return']),reverse=True)
    for rank,row in enumerate(out,1): row['rank']=rank
    return out
