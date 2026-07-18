from __future__ import annotations

from typing import Dict, Type

from core.model_registry import MODEL_REGISTRY

from trainers.short_term.xgboost_trainer import ShortTermXGBoostTrainer
from trainers.long_term.xgboost_trainer import LongTermXGBoostTrainer
from trainers.market.market_trainer import MarketTrainer
from trainers.consensus.consensus_trainer import ConsensusTrainer


class ModelFactory:
    """
    Zentrale Factory für alle KI-Modelle.

    Neue Modelle müssen nur registriert werden.
    """

    _TRAINERS: Dict[str, Type] = {
        "AKI-PULSE": ShortTermXGBoostTrainer,
        "AKI-HORIZON": LongTermXGBoostTrainer,
        "AKI-CLIMATE": MarketTrainer,
        "AKI-NEXUS": ConsensusTrainer,
    }

    @classmethod
    def create(cls, alias: str):
        """
        Erstellt eine Trainer-Instanz.
        """

        definition = cls.definition(alias)
        trainer = cls.trainer(alias)

        return trainer(definition)

    @classmethod
    def definition(cls, alias: str):
        if alias not in MODEL_REGISTRY:
            raise ValueError(f"Unknown model alias: {alias}")

        return MODEL_REGISTRY[alias]

    @classmethod
    def trainer(cls, alias: str):
        if alias not in cls._TRAINERS:
            raise ValueError(f"No trainer registered for: {alias}")

        return cls._TRAINERS[alias]

    @classmethod
    def register(
        cls,
        alias: str,
        trainer: Type,
    ) -> None:
        """
        Registrierung neuer Modelle zur Laufzeit.
        """

        cls._TRAINERS[alias] = trainer

    @classmethod
    def registered_models(cls) -> list[str]:
        """
        Alle verfügbaren Modelle.
        """

        return sorted(cls._TRAINERS.keys())

    @classmethod
    def exists(cls, alias: str) -> bool:
        return alias in cls._TRAINERS