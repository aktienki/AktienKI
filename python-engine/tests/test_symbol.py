from __future__ import annotations

import argparse
import json
import sys

import numpy as np

from app.training.dataset_builder import DatasetBuilder
from app.training.ensemble_trainer import EnsembleTrainer
from app.training.strategy_manager import strategy_manager
from app.training.train_test_split import TrainTestSplit


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Testet ein einzelnes Symbol mit AKI-PULSE."
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
            "AKI-CLIMATE",
        ],
    )
    return parser.parse_args()


def direction_from_value(value: float) -> str:
    if value > 0:
        return "long"
    if value < 0:
        return "short"
    return "neutral"


def main() -> int:
    args = parse_args()

    strategy = strategy_manager.get(args.alias)
    builder = DatasetBuilder()

    print("=" * 64)
    print("AKI SYMBOL TEST")
    print("=" * 64)
    print(f"Symbol: {args.symbol}")
    print(f"Alias: {strategy.alias}")
    print(f"Timeframe: {strategy.interval}")
    print(f"Training window: {strategy.training_days} Tage")
    print(f"Prediction horizon: {strategy.prediction_minutes} Minuten")
    print()

    features, target = builder.build(
        symbol=args.symbol,
        strategy=strategy,
    )

    (
        x_train,
        y_train,
        x_validation,
        y_validation,
    ) = TrainTestSplit.split(
        features,
        target,
    )

    ensemble = EnsembleTrainer()
    result = ensemble.train(
        x_train=x_train,
        y_train=y_train,
        x_validation=x_validation,
        y_validation=y_validation,
    )

    latest_features = x_validation.tail(1)
    latest_prediction = float(
        np.asarray(
            result.winner_adapter.predict(
                result.winner_training_result.model,
                latest_features,
            )
        )[0]
    )

    output = {
        "symbol": args.symbol,
        "alias": strategy.alias,
        "scope": strategy.scope.value,
        "timeframe": strategy.interval,
        "training_window_days": strategy.training_days,
        "prediction_horizon_minutes": strategy.prediction_minutes,
        "rows_total": len(features),
        "rows_train": len(x_train),
        "rows_validation": len(x_validation),
        "winner": "AKI-PRIME",
        "winner_algorithm": result.winner_algorithm,
        "runner_up_algorithm": result.runner_up_algorithm,
        "prediction": latest_prediction,
        "direction": direction_from_value(latest_prediction),
        "ensemble_score": result.winner_metrics.get("ensemble_score"),
        "direction_accuracy": result.winner_metrics.get(
            "direction_accuracy"
        ),
        "rmse": result.winner_metrics.get("rmse"),
        "r2": result.winner_metrics.get("r2"),
        "candidates": result.candidates,
    }

    print(json.dumps(output, indent=2, default=str))
    return 0


if __name__ == "__main__":
    sys.exit(main())
