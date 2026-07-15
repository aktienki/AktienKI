from __future__ import annotations

from typing import Dict, Type

from core.model_registry import MODEL_REGISTRY

# Import der eigentlichen Trainer
from trainers.short_term.xgboost_trainer import ShortTermXGBoostTrainer
from trainers.long_term.xgboost_trainer import LongTermXGBoostTrainer
from trainers.market.market_trainer import MarketTrainer
from trainers.consensus.consensus_trainer import ConsensusTrainer


class ModelFactory:

    _TRAINERS: Dict[str, Type] = {

        "AKI-PULSE": ShortTermXGBoostTrainer,

        "AKI-HORIZON": LongTermXGBoostTrainer,

        "AKI-CLIMATE": MarketTrainer,

        "AKI-NEXUS": ConsensusTrainer,

    }

    @classmethod
    def create(cls, alias: str):

        if alias not in MODEL_REGISTRY:
            raise ValueError(f"Unknown model alias: {alias}")

        if alias not in cls._TRAINERS:
            raise ValueError(f"No trainer registered for: {alias}")

        definition = MODEL_REGISTRY[alias]

        trainer_class = cls._TRAINERS[alias]

        return trainer_class(definition)