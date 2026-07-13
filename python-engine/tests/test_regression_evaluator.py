import numpy as np

from app.training.evaluator import RegressionEvaluator


def test_evaluator_calculates_direction_accuracy() -> None:
    actual = np.array([0.05, -0.03, 0.01, -0.02])
    predicted = np.array([0.04, -0.01, -0.01, -0.03])

    metrics = RegressionEvaluator.evaluate(actual, predicted)

    assert metrics["direction_accuracy"] == 0.75
    assert metrics["mae"] >= 0
    assert metrics["rmse"] >= 0
