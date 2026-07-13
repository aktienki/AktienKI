from __future__ import annotations


class ExperimentSelector:
    def score(
        self,
        *,
        validation_metrics: dict,
        test_metrics: dict,
        rules: dict | None = None,
    ) -> dict[str, float]:
        rules = rules or {}

        validation_direction = float(
            validation_metrics.get(
                "direction_accuracy",
                0.0,
            )
        )
        test_direction = float(
            test_metrics.get(
                "direction_accuracy",
                0.0,
            )
        )

        validation_rmse = float(
            validation_metrics.get("rmse", 999.0)
        )
        test_rmse = float(
            test_metrics.get("rmse", 999.0)
        )

        direction_gap = abs(
            validation_direction - test_direction
        )

        rmse_gap = abs(validation_rmse - test_rmse)
        rmse_reference = max(
            abs(validation_rmse),
            abs(test_rmse),
            1e-9,
        )

        normalized_rmse_gap = min(
            1.0,
            rmse_gap / rmse_reference,
        )

        stability_score = max(
            0.0,
            1.0
            - direction_gap
            - normalized_rmse_gap * 0.5,
        )

        direction_weight = float(
            rules.get("direction_weight", 0.55)
        )
        stability_weight = float(
            rules.get("stability_weight", 0.30)
        )
        r2_weight = float(
            rules.get("r2_weight", 0.15)
        )

        test_r2 = max(
            0.0,
            min(1.0, float(test_metrics.get("r2", 0.0))),
        )

        selection_score = (
            test_direction * direction_weight
            + stability_score * stability_weight
            + test_r2 * r2_weight
        )

        return {
            "stability_score": round(stability_score, 6),
            "selection_score": round(selection_score, 6),
        }
