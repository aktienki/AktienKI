from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

import numpy as np

from app.features.feature_importance import (
    feature_importance_report,
)
from app.features.feature_selector import FeatureSelector
from app.training.dataset_builder import DatasetBuilder
from app.training.ensemble_trainer import EnsembleTrainer
from app.training.model_lifecycle_manager import (
    ModelLifecycleManager,
)
from app.training.model_quality_gate import ModelQualityGate
from app.training.strategy_manager import strategy_manager
from app.training.train_test_split import TrainTestSplit


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Erweiterter Einzeltest für AKI-PULSE, AKI-HORIZON "
            "oder AKI-CLIMATE."
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


def model_from_training_result(training_result):
    if hasattr(training_result, "model"):
        return training_result.model

    if isinstance(training_result, dict) and "model" in training_result:
        return training_result["model"]

    raise RuntimeError(
        "Das Trainingsergebnis enthält kein zugängliches Modell."
    )


def latest_prediction(result, dataframe) -> float:
    model = model_from_training_result(
        result.winner_training_result
    )

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


def direction_from_prediction(value: float) -> str:
    if value > 0:
        return "long"

    if value < 0:
        return "short"

    return "neutral"


def main() -> int:
    args = parse_args()

    symbol = args.symbol.upper()
    strategy = strategy_manager.get(args.alias)

    builder = DatasetBuilder()

    features, target = builder.build(
        symbol=symbol,
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

    baseline = EnsembleTrainer().train(
        x_train=x_train,
        y_train=y_train,
        x_validation=x_validation,
        y_validation=y_validation,
    )

    baseline_model = model_from_training_result(
        baseline.winner_training_result
    )

    selector = FeatureSelector(
        max_features=args.max_features,
        min_importance=args.min_importance,
        correlation_threshold=args.correlation_threshold,
    )

    selection = selector.select(
        model=baseline_model,
        dataframe=x_train,
        feature_names=list(x_train.columns),
    )

    selected_train = selector.transform(
        dataframe=x_train,
        selection=selection,
    )

    selected_validation = selector.transform(
        dataframe=x_validation,
        selection=selection,
    )

    selected = EnsembleTrainer().train(
        x_train=selected_train,
        y_train=y_train,
        x_validation=selected_validation,
        y_validation=y_validation,
    )

    prediction = latest_prediction(
        selected,
        selected_validation,
    )

    quality_gate = ModelQualityGate()
    quality_result = quality_gate.evaluate(
        selected.winner_metrics
    )

    lifecycle = ModelLifecycleManager(
        quality_gate=quality_gate
    )

    version = datetime.now(
        timezone.utc
    ).strftime("%Y%m%dT%H%M%SZ")

    lifecycle_result = lifecycle.evaluate_candidate(
        alias=strategy.alias,
        algorithm=selected.winner_algorithm,
        version=version,
        metrics=selected.winner_metrics,
        feature_count=len(selected_train.columns),
    )

    report_directory = (
        Path("storage")
        / "reports"
        / strategy.alias.lower()
        / symbol
    )

    report_directory.mkdir(
        parents=True,
        exist_ok=True,
    )

    feature_report_path = (
        report_directory
        / "feature_importance.json"
    )

    feature_importance_report.save_json(
        model=model_from_training_result(
            selected.winner_training_result
        ),
        feature_names=list(selected_train.columns),
        path=feature_report_path,
        model_alias=strategy.alias,
        algorithm=selected.winner_algorithm,
        symbol=symbol,
        timeframe=strategy.interval,
    )

    output = {
        "symbol": symbol,
        "alias": strategy.alias,
        "scope": strategy.scope.value,
        "timeframe": strategy.interval,
        "training_window_days": strategy.training_days,
        "prediction_horizon_minutes": (
            strategy.prediction_minutes
        ),
        "rows_total": len(features),
        "rows_train": len(x_train),
        "rows_validation": len(x_validation),
        "features_before_selection": len(x_train.columns),
        "features_after_selection": len(
            selected_train.columns
        ),
        "selected_features": selection.selected_features,
        "baseline": {
            "winner_algorithm": baseline.winner_algorithm,
            "runner_up_algorithm": baseline.runner_up_algorithm,
            "metrics": baseline.winner_metrics,
        },
        "selected": {
            "winner": "AKI-PRIME",
            "winner_algorithm": selected.winner_algorithm,
            "runner_up_algorithm": selected.runner_up_algorithm,
            "metrics": selected.winner_metrics,
            "prediction": prediction,
            "direction": direction_from_prediction(
                prediction
            ),
        },
        "quality_gate": {
            "accepted": quality_result.accepted,
            "status": quality_result.status,
            "reasons": quality_result.reasons,
        },
        "lifecycle": {
            "promote": lifecycle_result.promote,
            "retrain": lifecycle_result.retrain,
            "drift_detected": (
                lifecycle_result.drift_detected
            ),
            "status": lifecycle_result.status,
            "reasons": lifecycle_result.reasons,
            "current_version": (
                lifecycle_result.current_version
            ),
            "previous_version": (
                lifecycle_result.previous_version
            ),
        },
        "feature_importance_report": str(
            feature_report_path
        ),
        "candidates": selected.candidates,
    }

    report_path = (
        report_directory
        / "advanced_symbol_test.json"
    )

    report_path.write_text(
        json.dumps(
            output,
            indent=2,
            ensure_ascii=False,
            default=str,
        ),
        encoding="utf-8",
    )

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
