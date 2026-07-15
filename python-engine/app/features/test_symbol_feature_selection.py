from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

import numpy as np

from app.features.feature_importance import (
    feature_importance_report,
)
from app.features.feature_selector import FeatureSelector
from app.training.dataset_builder import DatasetBuilder
from app.training.ensemble_trainer import EnsembleTrainer
from app.training.strategy_manager import strategy_manager
from app.training.train_test_split import TrainTestSplit


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Testet ein Symbol mit AKI-PULSE oder "
            "AKI-HORIZON inklusive Feature-Selektion."
        )
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

    parser.add_argument(
        "--max-features",
        type=int,
        default=50,
    )

    parser.add_argument(
        "--min-importance",
        type=float,
        default=0.001,
    )

    parser.add_argument(
        "--correlation-threshold",
        type=float,
        default=0.98,
    )

    return parser.parse_args()


def direction_from_value(
    value: float,
) -> str:
    if value > 0:
        return "long"

    if value < 0:
        return "short"

    return "neutral"


def model_from_result(result):
    training_result = (
        result.winner_training_result
    )

    if hasattr(
        training_result,
        "model",
    ):
        return training_result.model

    if isinstance(
        training_result,
        dict,
    ) and "model" in training_result:
        return training_result["model"]

    raise RuntimeError(
        "Das Champion-Trainingsergebnis enthält "
        "kein zugängliches Modell."
    )


def predict_latest(
    result,
    dataframe,
) -> float:
    model = model_from_result(result)

    prediction = result.winner_adapter.predict(
        model,
        dataframe.tail(1),
    )

    return float(
        np.asarray(
            prediction,
            dtype=float,
        ).reshape(-1)[0]
    )


def train_ensemble(
    *,
    x_train,
    y_train,
    x_validation,
    y_validation,
):
    trainer = EnsembleTrainer()

    return trainer.train(
        x_train=x_train,
        y_train=y_train,
        x_validation=x_validation,
        y_validation=y_validation,
    )


def main() -> int:
    args = parse_args()

    strategy = strategy_manager.get(
        args.alias
    )

    builder = DatasetBuilder()

    print("=" * 72)
    print("AKI SYMBOL TEST · FEATURE SELECTION")
    print("=" * 72)
    print(f"Symbol: {args.symbol}")
    print(f"Alias: {strategy.alias}")
    print(f"Timeframe: {strategy.interval}")
    print(
        "Training window: "
        f"{strategy.training_days} Tage"
    )
    print(
        "Prediction horizon: "
        f"{strategy.prediction_minutes} Minuten"
    )
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

    baseline_result = train_ensemble(
        x_train=x_train,
        y_train=y_train,
        x_validation=x_validation,
        y_validation=y_validation,
    )

    baseline_model = model_from_result(
        baseline_result
    )

    selector = FeatureSelector(
        max_features=args.max_features,
        min_importance=args.min_importance,
        correlation_threshold=(
            args.correlation_threshold
        ),
    )

    selection = selector.select(
        model=baseline_model,
        dataframe=x_train,
        feature_names=list(
            x_train.columns
        ),
    )

    selected_train = selector.transform(
        dataframe=x_train,
        selection=selection,
    )

    selected_validation = selector.transform(
        dataframe=x_validation,
        selection=selection,
    )

    selected_result = train_ensemble(
        x_train=selected_train,
        y_train=y_train,
        x_validation=selected_validation,
        y_validation=y_validation,
    )

    latest_prediction = predict_latest(
        selected_result,
        selected_validation,
    )

    reports_directory = (
        Path("storage")
        / "reports"
        / strategy.alias.lower()
        / args.symbol.upper()
    )

    reports_directory.mkdir(
        parents=True,
        exist_ok=True,
    )

    importance_path = (
        reports_directory
        / "feature_importance.json"
    )

    feature_importance_report.save_json(
        model=model_from_result(
            selected_result
        ),
        feature_names=list(
            selected_train.columns
        ),
        path=importance_path,
        model_alias=strategy.alias,
        algorithm=(
            selected_result.winner_algorithm
        ),
        symbol=args.symbol.upper(),
        timeframe=strategy.interval,
    )

    output = {
        "symbol": args.symbol.upper(),
        "alias": strategy.alias,
        "scope": strategy.scope.value,
        "timeframe": strategy.interval,
        "training_window_days": (
            strategy.training_days
        ),
        "prediction_horizon_minutes": (
            strategy.prediction_minutes
        ),
        "rows_total": len(features),
        "rows_train": len(x_train),
        "rows_validation": len(
            x_validation
        ),
        "features_before_selection": len(
            x_train.columns
        ),
        "features_after_selection": (
            selection.selected_count
        ),
        "removed_features": (
            selection.removed_count
        ),
        "baseline": {
            "winner": "AKI-PRIME",
            "winner_algorithm": (
                baseline_result.winner_algorithm
            ),
            "runner_up_algorithm": (
                baseline_result.runner_up_algorithm
            ),
            "metrics": (
                baseline_result.winner_metrics
            ),
        },
        "selected": {
            "winner": "AKI-PRIME",
            "winner_algorithm": (
                selected_result.winner_algorithm
            ),
            "runner_up_algorithm": (
                selected_result.runner_up_algorithm
            ),
            "metrics": (
                selected_result.winner_metrics
            ),
            "prediction": latest_prediction,
            "direction": direction_from_value(
                latest_prediction
            ),
        },
        "selected_features": (
            selection.selected_features
        ),
        "feature_importance_report": str(
            importance_path
        ),
        "candidates": (
            selected_result.candidates
        ),
    }

    print(
        json.dumps(
            output,
            indent=2,
            default=str,
        )
    )

    return 0


if __name__ == "__main__":
    sys.exit(main())
