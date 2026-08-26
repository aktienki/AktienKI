from __future__ import annotations

import argparse
from collections import defaultdict
from datetime import datetime, timezone
import json
from pathlib import Path
import sys

ENGINE = Path('/Users/silviotaubert/Downloads/python-engine')
sys.path.insert(0, str(ENGINE))

import numpy as np
import scripts.train_sector_deep_learning as trainer
import app.sector_deep_learning as sector_module
from app.cli.main import _import
from app.config.settings import settings
from app.database.connection import Database
from app.repositories.instrument_repository import InstrumentRepository
from app.sector_deep_learning import add_cross_sector_context, aggregate_sector_history, build_sector_samples, relative_sector_targets

HORIZONS = (20, 60)
REGIMES = ('bull_calm', 'bull_volatile', 'sideways', 'stress')


def regime(sample, volatility_threshold: float) -> str:
    market_return_20d = float(sample.sequence[-1][13])
    market_volatility_20d = float(sample.sequence[-1][14])
    if market_return_20d < -.02:
        return 'stress'
    if market_return_20d > .02:
        return 'bull_volatile' if market_volatility_20d > volatility_threshold else 'bull_calm'
    return 'sideways'


def combine(evaluations: list[dict]) -> dict:
    result = {'_point_in_time': []}
    for horizon in HORIZONS:
        key = str(horizon)
        total = sum(int(value[key]['samples']) for value in evaluations)
        result[key] = {
            name: sum(int(value[key]['samples']) * float(value[key][name]) for value in evaluations) / total
            for name in ('direction_accuracy', 'mae', 'momentum_baseline_accuracy', 'always_up_baseline_accuracy', 'lift_vs_best_baseline')
        }
        result[key]['samples'] = total
    for value in evaluations:
        result['_point_in_time'].extend(value['_point_in_time'])
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--years', type=int, default=10)
    parser.add_argument('--minimum-members', type=int, default=5)
    parser.add_argument('--max-members-per-sector', type=int, default=25)
    parser.add_argument('--sequence-length', type=int, default=60)
    parser.add_argument('--fold-epochs', type=int, default=10)
    parser.add_argument('--minimum-training-years', type=int, default=5)
    parser.add_argument('--seed', type=int, default=42)
    args = parser.parse_args()

    trainer.HORIZONS = HORIZONS
    sector_module.HORIZONS = HORIZONS
    observations_by_sector = {}
    sector_report = {}
    with Database() as database:
        active = [item for item in InstrumentRepository(database).find_active() if item.asset_type == 'stock' and item.sector]
        for sector in sorted({item.sector for item in active}):
            members = [item for item in active if item.sector == sector][:args.max_members_per_sector]
            histories = {}
            for member in members:
                try:
                    histories[member.symbol] = list(_import(database, member.symbol, '1d', args.years, persist=False).bars)
                except Exception as exc:
                    print(f'DATA_SKIPPED {sector} {member.symbol} {exc}', flush=True)
            observations = aggregate_sector_history(histories, minimum_members=args.minimum_members)
            if observations:
                observations_by_sector[sector] = observations
                sector_report[sector] = {'members': len(histories), 'observations': len(observations)}

    observations_by_sector = add_cross_sector_context(observations_by_sector)
    samples = []
    for name, observations in observations_by_sector.items():
        samples.extend(build_sector_samples(name, observations, sequence_length=args.sequence_length, horizons=HORIZONS))
    samples = relative_sector_targets(samples)
    sector_ids = {name: index for index, name in enumerate(sorted(observations_by_sector))}
    years = sorted({sample.timestamp.year for sample in samples})
    folds = []
    for test_year in years[args.minimum_training_years:]:
        train = [sample for sample in samples if sample.timestamp.year < test_year]
        test = [sample for sample in samples if sample.timestamp.year == test_year]
        if not train or not test:
            continue
        volatility_threshold = float(np.quantile([float(sample.sequence[-1][14]) for sample in train], .75))
        global_model, global_mean, global_std, _ = trainer._fit(train, sector_ids, epochs=args.fold_epochs, seed=args.seed)
        global_metrics = trainer._evaluate(global_model, test, sector_ids, global_mean, global_std)
        expert_evaluations = []
        regime_counts = {}
        for index, regime_name in enumerate(REGIMES):
            regime_train = [sample for sample in train if regime(sample, volatility_threshold) == regime_name]
            regime_test = [sample for sample in test if regime(sample, volatility_threshold) == regime_name]
            regime_counts[regime_name] = {'train': len(regime_train), 'test': len(regime_test)}
            if not regime_test:
                continue
            if len(regime_train) < 200:
                evaluation = trainer._evaluate(global_model, regime_test, sector_ids, global_mean, global_std)
            else:
                model, mean, std, _ = trainer._fit(regime_train, sector_ids, epochs=args.fold_epochs, seed=args.seed + index + 1)
                evaluation = trainer._evaluate(model, regime_test, sector_ids, mean, std)
            for row in evaluation['_point_in_time']:
                row['regime'] = regime_name
            expert_evaluations.append(evaluation)
        expert_metrics = combine(expert_evaluations)
        folds.append({'test_year': test_year, 'training_samples': len(train), 'test_samples': len(test), 'volatility_threshold': volatility_threshold, 'regime_counts': regime_counts, 'global_metrics': global_metrics, 'metrics': expert_metrics})
        print(f'REGIME_FOLD_COMPLETE {test_year} samples={len(test)}', flush=True)

    report = {'version': datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'), 'method': 'causal_market_regime_experts', 'seed': args.seed, 'horizons': HORIZONS, 'regimes': REGIMES, 'years_requested': args.years, 'sectors': sector_report, 'walk_forward': folds}
    output = settings.model_path / 'experiments' / 'sector_deep_learning' / f"regime_experts_{report['version']}_seed{args.seed}.json"
    output.write_text(json.dumps(report, indent=2), encoding='utf-8')
    print(f'REPORT {output}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
