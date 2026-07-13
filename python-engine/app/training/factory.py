from __future__ import annotations

from app.training.adapters.catboost_adapter import CatBoostAdapter
from app.training.adapters.lightgbm_adapter import LightGBMAdapter
from app.training.adapters.xgboost_adapter import XGBoostAdapter
from app.training.base import ModelAdapter


class ModelFactory:
    _adapters: dict[str, type[ModelAdapter]] = {
        "xgboost": XGBoostAdapter,
        "lightgbm": LightGBMAdapter,
        "catboost": CatBoostAdapter,
    }

    @classmethod
    def register(
        cls,
        name: str,
        adapter: type[ModelAdapter],
    ) -> None:
        cls._adapters[name.lower()] = adapter

    @classmethod
    def create(cls, name: str) -> ModelAdapter:
        key = name.lower().strip()

        try:
            adapter_class = cls._adapters[key]
        except KeyError as exception:
            supported = ", ".join(sorted(cls._adapters))
            raise ValueError(
                f"Unbekanntes Modell '{name}'. "
                f"Unterstützt: {supported}"
            ) from exception

        return adapter_class()

    @classmethod
    def available_models(cls) -> list[str]:
        return sorted(cls._adapters)
