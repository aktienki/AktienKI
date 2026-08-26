from __future__ import annotations

import argparse
from bisect import bisect_right
from collections import defaultdict
from datetime import date
import json
from pathlib import Path
import statistics
import sys


ENGINE = Path('/Users/silviotaubert/Downloads/python-engine')
sys.path.insert(0, str(ENGINE))

from app.database.connection import Database


def load_ensemble(paths: list[Path]):
    values: dict[tuple[str, date], dict[str, list[float]]] = defaultdict(
        lambda: {'p20': [], 'p60': []}
    )
    for path in paths:
        report = json.loads(path.read_text(encoding='utf-8'))
        for fold in report['walk_forward']:
            for row in fold['metrics']['_point_in_time']:
                key = (row['sector'], date.fromisoformat(row['date']))
                values[key]['p20'].append(float(row['probability_20d']))
                values[key]['p60'].append(float(row['probability_60d']))
    ensemble = {
        key: {
            'p20': statistics.fmean(item['p20']),
            'p60': statistics.fmean(item['p60']),
            'seeds': len(item['p20']),
        }
        for key, item in values.items()
        if len(item['p20']) == len(paths)
    }
    dates = defaultdict(list)
    for sector, day in ensemble:
        dates[sector].append(day)
    for sector in dates:
        dates[sector].sort()
    return ensemble, dates


def latest_signal(ensemble, dates, sector: str, entry_date: date):
    available = dates.get(sector, [])
    index = bisect_right(available, entry_date) - 1
    if index < 0:
        return None
    signal_date = available[index]
    if (entry_date - signal_date).days > 4:
        return None
    return signal_date, ensemble[(sector, signal_date)]


def metrics(rows: list[dict]) -> dict:
    returns = [float(row['net_return']) for row in rows]
    wins = [value for value in returns if value > 0]
    losses = [value for value in returns if value < 0]
    return {
        'trades': len(rows),
        'instruments': len({int(row['instrument_id']) for row in rows}),
        'hit_rate_percent': 100 * len(wins) / len(rows) if rows else None,
        'average_net_return_percent': 100 * statistics.fmean(returns) if rows else None,
        'median_net_return_percent': 100 * statistics.median(returns) if rows else None,
        'profit_factor': sum(wins) / abs(sum(losses)) if losses else None,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--run-id', type=int, default=347)
    parser.add_argument('--report', action='append', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()

    ensemble, dates = load_ensemble(args.report)
    with Database() as database:
        trades = database.fetch_all(
            """SELECT trade.instrument_id, trade.entry_date, trade.net_return,
                      instrument.symbol, instrument.sector
                 FROM backtest_trades trade
                 JOIN instruments instrument ON instrument.id=trade.instrument_id
                WHERE trade.backtest_run_id=%s AND trade.horizon_days=20
                  AND UPPER(trade.signal)='BUY' AND trade.net_return IS NOT NULL
                ORDER BY trade.entry_date, trade.id""",
            (args.run_id,),
        )

    groups: dict[str, list[dict]] = defaultdict(list)
    matched = []
    for row in trades:
        sector = str(row.get('sector') or '')
        found = latest_signal(ensemble, dates, sector, row['entry_date'])
        if found is None:
            continue
        signal_date, signal = found
        value = dict(row)
        value.update(signal_date=signal_date, **signal)
        matched.append(value)
        d20 = signal['p20'] >= .5
        d60 = signal['p60'] >= .5
        groups['aligned' if d20 == d60 else 'mixed'].append(value)
        groups['bullish_consensus' if d20 and d60 else 'not_bullish_consensus'].append(value)
        groups['bearish_consensus' if not d20 and not d60 else 'not_bearish_consensus'].append(value)

    result = {
        'backtest_run_id': args.run_id,
        'source_trades': len(trades),
        'matched_point_in_time_trades': len(matched),
        'coverage_percent': 100 * len(matched) / len(trades) if trades else 0,
        'seeds': len(args.report),
        'all_matched': metrics(matched),
        'groups': {name: metrics(rows) for name, rows in sorted(groups.items())},
        'yearly': {
            str(year): {
                'all': metrics([row for row in matched if row['entry_date'].year == year]),
                'aligned': metrics([
                    row for row in groups['aligned'] if row['entry_date'].year == year
                ]),
                'mixed': metrics([
                    row for row in groups['mixed'] if row['entry_date'].year == year
                ]),
                'bullish_consensus': metrics([
                    row for row in groups['bullish_consensus'] if row['entry_date'].year == year
                ]),
            }
            for year in sorted({row['entry_date'].year for row in matched})
        },
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, indent=2), encoding='utf-8')
    print(json.dumps(result, indent=2))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
