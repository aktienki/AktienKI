import pytest

from app.training.adapters.xgboost_adapter import XGBoostAdapter
from app.training.factory import ModelFactory


def test_factory_returns_xgboost_adapter() -> None:
    adapter = ModelFactory.create("xgboost")

    assert isinstance(adapter, XGBoostAdapter)


def test_factory_rejects_unknown_model() -> None:
    with pytest.raises(ValueError):
        ModelFactory.create("unknown")


def test_available_models_contains_expected_models() -> None:
    models = ModelFactory.available_models()

    assert "xgboost" in models
    assert "lightgbm" in models
    assert "catboost" in models
