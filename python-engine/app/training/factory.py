from __future__ import annotations

from app.training.adapters.catboost_adapter import CatBoostAdapter
from app.training.adapters.lightgbm_adapter import LightGBMAdapter
from app.training.adapters.xgboost_adapter import XGBoostAdapter
from app.training.base import ModelAdapter


class ModelFactory:
    """
    Zentrale Factory für die internen Modelladapter.

    Öffentliche AKI-Aliase gehören in Strategy Profiles und Metadaten.
    Die Factory kennt ausschließlich interne Adapter.
    """

    _ADAPTERS: dict[str, type[ModelAdapter]] = {
        "xgboost": XGBoostAdapter,
        "lightgbm": LightGBMAdapter,
        "catboost": CatBoostAdapter,
    }

    @classmethod
    def create(cls, algorithm: str) -> ModelAdapter:
        normalized = algorithm.lower().strip()

        adapter_class = cls._ADAPTERS.get(normalized)

        if adapter_class is None:
            available = ", ".join(cls.available_models())

            raise ValueError(
                f"Unbekannter Algorithmus '{algorithm}'. "
                f"Verfügbar: {available}"
            )

        return adapter_class()

    @classmethod
    def available_models(cls) -> list[str]:
        return sorted(cls._ADAPTERS.keys())

    @classmethod
    def is_supported(cls, algorithm: str) -> bool:
        return algorithm.lower().strip() in cls._ADAPTERS
