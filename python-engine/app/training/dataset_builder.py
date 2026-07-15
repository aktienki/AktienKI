from __future__ import annotations

import numpy as np
import pandas as pd

from app.features import FeatureStore
from app.providers.provider_factory import ProviderFactory


class DatasetBuilder:
    """
    Baut einen vollstÃ¤ndigen Trainingsdatensatz fÃ¼r eine AKI-Strategy.

    Ablauf:
    1. Marktdaten laden
    2. Feature Store ausfÃ¼hren
    3. Strategy-spezifisches Target erzeugen
    4. numerische Feature-Spalten auswÃ¤hlen
    5. ungÃ¼ltige Zeilen entfernen
    6. Features und Target getrennt zurÃ¼ckgeben
    """

    def __init__(self) -> None:
        self.store = FeatureStore()

    def build(
        self,
        symbol: str,
        strategy,
    ) -> tuple[pd.DataFrame, pd.Series]:
        provider = ProviderFactory.create(
            strategy.scope
        )

        dataframe = provider.load(
            symbol=symbol,
            days=strategy.training_days,
        )

        if dataframe.empty:
            raise RuntimeError(
                f"FÃ¼r {symbol} wurden keine Marktdaten geladen."
            )

        dataframe = self.store.transform(
            dataframe
        )

        dataframe = strategy.create_target(
            dataframe
        )

        if "target" not in dataframe.columns:
            raise RuntimeError(
                f"Strategy {strategy.alias} hat keine "
                "Target-Spalte erzeugt."
            )

        dataframe = dataframe.replace(
            [np.inf, -np.inf],
            np.nan,
        )

        feature_names = self.store.feature_columns(
            dataframe
        )

        if not feature_names:
            raise RuntimeError(
                "Der Feature Store hat keine numerischen "
                "Feature-Spalten erzeugt."
            )

        required_columns = [
            *feature_names,
            "target",
        ]

        dataframe = dataframe.dropna(
            subset=required_columns
        ).copy()

        if dataframe.empty:
            raise RuntimeError(
                f"Nach Feature- und Target-Berechnung "
                f"sind fÃ¼r {symbol} keine vollstÃ¤ndigen "
                "Trainingszeilen vorhanden."
            )

        features = dataframe[
            feature_names
        ].astype(float)

        target = dataframe[
            "target"
        ].astype(float)

        if len(features) != len(target):
            raise RuntimeError(
                "Features und Target besitzen unterschiedliche "
                "Zeilenanzahlen."
            )

        return features, target
