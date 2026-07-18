from __future__ import annotations

import argparse
import json
import sys

import numpy as np
import pandas as pd

from app.features import FeatureStore
from app.features.target_builder import TargetBuilder
from app.providers.provider_factory import ProviderFactory
from app.training.multi_target_training_engine import (
    MultiTargetTrainingEngine,
)
from app.training.strategy_manager import strategy_manager


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Testet das AKI Multi-Target-Training."
    )

    parser.add_argument(
        "symbol",
        nargs="?",
        default="NVDA",
    )

    parser.add_argument(
        "--alias",
        default="AKI-PULSE",
        choices=[
            "AKI-PULSE",
            "AKI-HORIZON",
        ],
    )

    return parser.parse_args()


def horizon_periods(
    alias: str,
) -> int:
    if alias == "AKI-PULSE":
        return 24

    if alias == "AKI-HORIZON":
        return 20

    raise ValueError(
        f"Kein Horizon-Mapping für {alias}"
    )


def main() -> int:
    args = parse_args()

    symbol = args.symbol.upper()
    strategy = strategy_manager.get(
        args.alias
    )

    provider = ProviderFactory.create(
        strategy.scope
    )

    dataframe = provider.load(
        symbol=symbol,
        days=strategy.training_days,
    )

    feature_store = FeatureStore()

    dataframe = feature_store.transform(
        dataframe
    )

    dataframe = TargetBuilder.build(
        dataframe,
        horizon=horizon_periods(
            strategy.alias
        ),
    )

    dataframe = dataframe.replace(
        [np.inf, -np.inf],
        np.nan,
    )

    feature_names = feature_store.feature_columns(
        dataframe
    )

    engine = MultiTargetTrainingEngine()

    result = engine.train(
        dataframe=dataframe,
        feature_names=feature_names,
    )

    targets = {}

    for target_name, item in result.targets.items():
        targets[target_name] = {
            "winner_algorithm": (
                item.training_result.winner_algorithm
            ),
            "runner_up_algorithm": (
                item.training_result.runner_up_algorithm
            ),
            "prediction": item.prediction,
            "metrics": (
                item.training_result.winner_metrics
            ),
        }

    output = {
        "symbol": symbol,
        "alias": strategy.alias,
        "scope": strategy.scope.value,
        "timeframe": strategy.interval,
        "training_window_days": (
            strategy.training_days
        ),
        "prediction_horizon_minutes": (
            strategy.prediction_minutes
        ),
        "rows": len(dataframe),
        "feature_count": len(feature_names),
        "decision": result.decision_payload,
        "targets": targets,
    }

    print(
        json.dumps(
            output,
            indent=2,
            ensure_ascii=False,
            default=str,
        )
    )

    return 0


if __name__ == "__main__":
    sys.exit(main())
