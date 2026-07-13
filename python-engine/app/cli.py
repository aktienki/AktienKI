import argparse
import json
import logging
from pathlib import Path

from app.core.engine import Engine
from app.training.factory import ModelFactory


def load_json_file(path: str | None) -> dict:
    if path is None:
        return {}

    file_path = Path(path)
    if not file_path.exists():
        raise FileNotFoundError(path)

    return json.loads(file_path.read_text(encoding="utf-8"))


def main():
    parser = argparse.ArgumentParser(
        description="AktienKI Engine"
    )

    parser.add_argument(
        "action",
        choices=[
            "import-market",
            "import-cross-assets",
            "calculate-indicators",
            "build-features",
            "train",
            "train-strategy",
            "run-experiment",
            "predict",
            "predict-strategy",
            "validate-predictions",
            "daily-run",
            "list-models",
        ],
    )

    parser.add_argument("--interval", default="1d")
    parser.add_argument("--period", default="10y")
    parser.add_argument(
        "--types",
        nargs="+",
        default=["stock", "etf", "index", "forex", "macro"],
    )
    parser.add_argument("--limit", type=int)
    parser.add_argument("--symbol")
    parser.add_argument("--instrument-id", type=int)
    parser.add_argument("--full", action="store_true")
    parser.add_argument("--target", default="target_return_5d")
    parser.add_argument("--feature-version", default="1.0.0")
    parser.add_argument("--algorithm", default="xgboost")
    parser.add_argument("--algorithms", nargs="+")
    parser.add_argument("--strategy")
    parser.add_argument("--horizon-days", type=int, default=5)
    parser.add_argument(
        "--continue-on-error",
        action="store_true",
    )
    parser.add_argument("--search-space-file")
    parser.add_argument("--selection-rules-file")
    parser.add_argument("--experiment-name")
    parser.add_argument("--max-variants", type=int, default=50)

    args = parser.parse_args()

    if args.action == "list-models":
        print(json.dumps(
            {"models": ModelFactory.available_models()},
            indent=2,
        ))
        return 0

    engine = Engine()

    try:
        if args.action == "import-market":
            result = engine.import_market(
                interval=args.interval,
                period=args.period,
                types=args.types,
                limit=args.limit,
                symbol=args.symbol,
                instrument_id=args.instrument_id,
                full=args.full,
            )

        elif args.action == "import-cross-assets":
            if not args.strategy:
                raise ValueError(
                    "--strategy ist erforderlich."
                )

            result = engine.import_cross_assets(
                strategy_code=args.strategy,
                full=args.full,
            )

        elif args.action == "calculate-indicators":
            result = engine.calculate_indicators(
                interval=args.interval,
                types=args.types,
                limit=args.limit,
                symbol=args.symbol,
                instrument_id=args.instrument_id,
            )

        elif args.action == "build-features":
            result = engine.build_features(
                interval=args.interval,
                types=args.types,
                limit=args.limit,
                symbol=args.symbol,
                instrument_id=args.instrument_id,
            )

        elif args.action == "train":
            if args.instrument_id is None:
                raise ValueError(
                    "--instrument-id ist erforderlich."
                )

            result = engine.train(
                instrument_id=args.instrument_id,
                algorithm=args.algorithm,
                interval=args.interval,
                feature_version=args.feature_version,
                target_name=args.target,
            )

        elif args.action == "train-strategy":
            if args.instrument_id is None:
                raise ValueError(
                    "--instrument-id ist erforderlich."
                )
            if not args.strategy:
                raise ValueError(
                    "--strategy ist erforderlich."
                )

            result = engine.train_strategy(
                strategy_code=args.strategy,
                instrument_id=args.instrument_id,
                algorithm=args.algorithm,
            )

        elif args.action == "run-experiment":
            if args.instrument_id is None:
                raise ValueError(
                    "--instrument-id ist erforderlich."
                )
            if not args.strategy:
                raise ValueError(
                    "--strategy ist erforderlich."
                )
            if not args.search_space_file:
                raise ValueError(
                    "--search-space-file ist erforderlich."
                )

            result = engine.run_experiment(
                strategy_code=args.strategy,
                instrument_id=args.instrument_id,
                search_space=load_json_file(
                    args.search_space_file
                ),
                algorithms=(
                    args.algorithms or ["xgboost"]
                ),
                name=args.experiment_name,
                selection_rules=load_json_file(
                    args.selection_rules_file
                ),
                max_variants=args.max_variants,
            )

        elif args.action == "predict":
            if args.instrument_id is None:
                raise ValueError(
                    "--instrument-id ist erforderlich."
                )

            result = engine.predict(
                instrument_id=args.instrument_id,
                algorithm=args.algorithm,
                target_name=args.target,
                interval=args.interval,
            )

        elif args.action == "predict-strategy":
            if args.instrument_id is None:
                raise ValueError(
                    "--instrument-id ist erforderlich."
                )
            if not args.strategy:
                raise ValueError(
                    "--strategy ist erforderlich."
                )

            result = engine.predict_strategy(
                strategy_code=args.strategy,
                instrument_id=args.instrument_id,
                algorithm=args.algorithm,
            )

        elif args.action == "validate-predictions":
            result = engine.validate_predictions(
                horizon_days=args.horizon_days,
                limit=args.limit,
            )

        else:
            if not args.symbol:
                raise ValueError(
                    "--symbol ist für daily-run erforderlich."
                )
            if not args.strategy:
                raise ValueError(
                    "--strategy ist für daily-run erforderlich."
                )

            result = engine.daily_run(
                symbol=args.symbol,
                strategy_code=args.strategy,
                algorithm=args.algorithm,
                interval=args.interval,
                validation_horizon_days=args.horizon_days,
                continue_on_error=args.continue_on_error,
            )

    except Exception:
        logging.exception(
            "AktienKI Engine konnte nicht ausgeführt werden."
        )
        return 1

    print(json.dumps(result, indent=2, default=str))
    return 0
