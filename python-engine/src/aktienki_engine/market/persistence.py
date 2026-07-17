from __future__ import annotations
import json

def persist_snapshot(connection, snapshot:dict)->int:
    with connection:
      with connection.cursor() as cur:
        cur.execute("""INSERT INTO market_snapshots(snapshot_time,market_score,risk_mode,market_trend,volatility,breadth_score,buy_signals,sell_signals,hold_signals,winning_sectors,losing_sectors,metadata,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb,%s::jsonb,%s::jsonb,now(),now()) ON CONFLICT(snapshot_time) DO UPDATE SET market_score=EXCLUDED.market_score,risk_mode=EXCLUDED.risk_mode,market_trend=EXCLUDED.market_trend,volatility=EXCLUDED.volatility,breadth_score=EXCLUDED.breadth_score,buy_signals=EXCLUDED.buy_signals,sell_signals=EXCLUDED.sell_signals,hold_signals=EXCLUDED.hold_signals,winning_sectors=EXCLUDED.winning_sectors,losing_sectors=EXCLUDED.losing_sectors,updated_at=now() RETURNING id""",(snapshot['snapshot_time'],snapshot['market_score'],snapshot['risk_mode'],snapshot['market_trend'],snapshot.get('volatility'),snapshot['breadth_score'],snapshot['buy_signals'],snapshot['sell_signals'],snapshot['hold_signals'],json.dumps(snapshot['winning_sectors']),json.dumps(snapshot['losing_sectors']),json.dumps({'source':'aktienki-engine'})))
        sid=cur.fetchone()[0]
        cur.execute('DELETE FROM market_assets WHERE market_snapshot_id=%s',(sid,)); cur.execute('DELETE FROM sector_snapshots WHERE market_snapshot_id=%s',(sid,)); cur.execute('DELETE FROM market_statistics WHERE market_snapshot_id=%s',(sid,))
        for a in snapshot['assets']:
          cur.execute("""INSERT INTO market_assets(market_snapshot_id,symbol,name,category,price,change_percent,volume,signal,trend,score,observed_at,metadata,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s::jsonb,now(),now())""",(sid,a['symbol'],a['name'],a['category'],a.get('price'),a.get('change_percent'),a.get('volume'),a.get('signal'),a.get('trend'),a.get('score'),a.get('observed_at'),json.dumps({'weight':a.get('weight')})))
        for s in snapshot['sectors']:
          cur.execute("""INSERT INTO sector_snapshots(market_snapshot_id,sector,average_return,average_score,buy_ratio,sell_ratio,trend,rank,companies_count,metadata,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,%s,%s,%s,%s,'{}',now(),now())""",(sid,s['sector'],s['average_return'],s['average_score'],s['buy_ratio'],s['sell_ratio'],s['trend'],s['rank'],s['companies_count']))
        st=snapshot['statistics']; cur.execute("""INSERT INTO market_statistics(market_snapshot_id,companies_total,buy_count,sell_count,hold_count,metadata,created_at,updated_at) VALUES(%s,%s,%s,%s,%s,'{}',now(),now())""",(sid,st['companies_total'],st['buy_count'],st['sell_count'],st['hold_count']))
        return sid
