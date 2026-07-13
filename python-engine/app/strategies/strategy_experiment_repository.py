from __future__ import annotations

import json
from uuid import uuid4

from sqlalchemy import text


class StrategyExperimentRepository:
    def __init__(self, session):
        self.session = session

    def create_experiment(
        self,
        *,
        strategy_profile_id: int,
        instrument_id: int,
        name: str,
        search_space: dict,
        algorithms: list[str],
        selection_rules: dict,
        variants_total: int,
    ) -> tuple[int, str]:
        public_id = str(uuid4())

        experiment_id = int(
            self.session.execute(
                text(
                    """
                    INSERT INTO strategy_experiments (
                        strategy_profile_id,
                        instrument_id,
                        public_id,
                        name,
                        status,
                        search_space,
                        algorithms,
                        selection_rules,
                        variants_total,
                        variants_completed,
                        variants_failed,
                        started_at,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :strategy_profile_id,
                        :instrument_id,
                        :public_id,
                        :name,
                        'running',
                        CAST(:search_space AS jsonb),
                        CAST(:algorithms AS jsonb),
                        CAST(:selection_rules AS jsonb),
                        :variants_total,
                        0,
                        0,
                        NOW(),
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "strategy_profile_id": strategy_profile_id,
                    "instrument_id": instrument_id,
                    "public_id": public_id,
                    "name": name,
                    "search_space": json.dumps(search_space),
                    "algorithms": json.dumps(algorithms),
                    "selection_rules": json.dumps(selection_rules),
                    "variants_total": variants_total,
                },
            ).scalar_one()
        )

        return experiment_id, public_id

    def create_variant(
        self,
        *,
        experiment_id: int,
        variant_code: str,
        resolved_configuration: dict,
        configuration_hash: str,
    ) -> int:
        return int(
            self.session.execute(
                text(
                    """
                    INSERT INTO strategy_experiment_variants (
                        strategy_experiment_id,
                        variant_code,
                        status,
                        resolved_configuration,
                        configuration_hash,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :experiment_id,
                        :variant_code,
                        'pending',
                        CAST(:resolved_configuration AS jsonb),
                        :configuration_hash,
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    "experiment_id": experiment_id,
                    "variant_code": variant_code,
                    "resolved_configuration": json.dumps(
                        resolved_configuration
                    ),
                    "configuration_hash": configuration_hash,
                },
            ).scalar_one()
        )

    def start_variant(self, variant_id: int) -> None:
        self.session.execute(
            text(
                """
                UPDATE strategy_experiment_variants
                SET status='running',
                    started_at=NOW(),
                    updated_at=NOW()
                WHERE id=:variant_id
                """
            ),
            {"variant_id": variant_id},
        )

    def complete_variant(self, variant_id: int) -> None:
        self.session.execute(
            text(
                """
                UPDATE strategy_experiment_variants
                SET status='completed',
                    finished_at=NOW(),
                    updated_at=NOW()
                WHERE id=:variant_id
                """
            ),
            {"variant_id": variant_id},
        )

    def fail_variant(
        self,
        *,
        variant_id: int,
        error_message: str,
    ) -> None:
        self.session.execute(
            text(
                """
                UPDATE strategy_experiment_variants
                SET status='failed',
                    error_message=:error_message,
                    finished_at=NOW(),
                    updated_at=NOW()
                WHERE id=:variant_id
                """
            ),
            {
                "variant_id": variant_id,
                "error_message": error_message[:10000],
            },
        )

    def save_result(
        self,
        *,
        variant_id: int,
        trained_model_id: int | None,
        algorithm: str,
        metrics: dict,
        stability_score: float,
        selection_score: float,
        metadata: dict,
    ) -> int:
        validation = metrics.get("validation", {})
        test = metrics.get("test", {})
        feature_importance = metrics.get(
            "feature_importance",
            {},
        )

        return int(
            self.session.execute(
                text(
                    """
                    INSERT INTO strategy_experiment_results (
                        strategy_experiment_variant_id,
                        trained_model_id,
                        algorithm,
                        status,
                        validation_mae,
                        validation_rmse,
                        validation_r2,
                        validation_direction_accuracy,
                        test_mae,
                        test_rmse,
                        test_r2,
                        test_direction_accuracy,
                        stability_score,
                        selection_score,
                        metrics,
                        feature_importance,
                        metadata,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :variant_id,
                        :trained_model_id,
                        :algorithm,
                        'completed',
                        :validation_mae,
                        :validation_rmse,
                        :validation_r2,
                        :validation_direction_accuracy,
                        :test_mae,
                        :test_rmse,
                        :test_r2,
                        :test_direction_accuracy,
                        :stability_score,
                        :selection_score,
                        CAST(:metrics AS jsonb),
                        CAST(:feature_importance AS jsonb),
                        CAST(:metadata AS jsonb),
                        NOW(),
                        NOW()
                    )
                    ON CONFLICT (
                        strategy_experiment_variant_id,
                        algorithm
                    )
                    DO UPDATE SET
                        trained_model_id=EXCLUDED.trained_model_id,
                        validation_mae=EXCLUDED.validation_mae,
                        validation_rmse=EXCLUDED.validation_rmse,
                        validation_r2=EXCLUDED.validation_r2,
                        validation_direction_accuracy=EXCLUDED.validation_direction_accuracy,
                        test_mae=EXCLUDED.test_mae,
                        test_rmse=EXCLUDED.test_rmse,
                        test_r2=EXCLUDED.test_r2,
                        test_direction_accuracy=EXCLUDED.test_direction_accuracy,
                        stability_score=EXCLUDED.stability_score,
                        selection_score=EXCLUDED.selection_score,
                        metrics=EXCLUDED.metrics,
                        feature_importance=EXCLUDED.feature_importance,
                        metadata=EXCLUDED.metadata,
                        updated_at=NOW()
                    RETURNING id
                    """
                ),
                {
                    "variant_id": variant_id,
                    "trained_model_id": trained_model_id,
                    "algorithm": algorithm,
                    "validation_mae": validation.get("mae"),
                    "validation_rmse": validation.get("rmse"),
                    "validation_r2": validation.get("r2"),
                    "validation_direction_accuracy": validation.get(
                        "direction_accuracy"
                    ),
                    "test_mae": test.get("mae"),
                    "test_rmse": test.get("rmse"),
                    "test_r2": test.get("r2"),
                    "test_direction_accuracy": test.get(
                        "direction_accuracy"
                    ),
                    "stability_score": stability_score,
                    "selection_score": selection_score,
                    "metrics": json.dumps(metrics),
                    "feature_importance": json.dumps(
                        feature_importance
                    ),
                    "metadata": json.dumps(metadata),
                },
            ).scalar_one()
        )

    def finish_experiment(
        self,
        *,
        experiment_id: int,
        completed: int,
        failed: int,
    ) -> None:
        self.session.execute(
            text(
                """
                UPDATE strategy_experiments
                SET status=:status,
                    variants_completed=:completed,
                    variants_failed=:failed,
                    finished_at=NOW(),
                    updated_at=NOW()
                WHERE id=:experiment_id
                """
            ),
            {
                "experiment_id": experiment_id,
                "completed": completed,
                "failed": failed,
                "status": (
                    "completed"
                    if failed == 0
                    else "completed_with_errors"
                ),
            },
        )
