from __future__ import annotations

def calculate_breadth(predictions:list[dict])->dict:
    valid=[p for p in predictions if p.get('signal')]
    total=len(valid); buy=sum(str(p['signal']).upper()=='BUY' for p in valid); sell=sum(str(p['signal']).upper()=='SELL' for p in valid); hold=total-buy-sell
    score=50.0 if total==0 else max(0,min(100,50+50*((buy-sell)/total)))
    return {'companies_total':total,'buy_count':buy,'sell_count':sell,'hold_count':hold,'breadth_score':round(score,2)}
