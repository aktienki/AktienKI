from app.experiments.selector import ExperimentSelector


def test_selector_rewards_stability_and_direction():
    result = ExperimentSelector().score(
        validation_metrics={
            "direction_accuracy": 0.62,
            "rmse": 0.02,
            "r2": 0.20,
        },
        test_metrics={
            "direction_accuracy": 0.61,
            "rmse": 0.021,
            "r2": 0.18,
        },
    )

    assert result["stability_score"] > 0.8
    assert result["selection_score"] > 0.4
