from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any

import json
import numpy as np
import pandas as pd


@dataclass(slots=True, frozen=True)
class FeatureImportanceEntry:
    feature: str
    importance: float
    rank: int


class FeatureImportanceReport:
    """
    Erstellt einen einheitlichen Feature-Importance-Report für
    XGBoost, LightGBM und CatBoost.

    Der Report kann direkt nach jedem Training erzeugt und als JSON
    gespeichert werden.
    """

    def extract(
        self,
        *,
        model: Any,
        feature_names: list[str],
    ) -> list[FeatureImportanceEntry]:
        importances = self._read_importances(
            model=model,
            feature_names=feature_names,
        )

        ranked = sorted(
            zip(
                feature_names,
                importances,
                strict=True,
            ),
            key=lambda item: item[1],
            reverse=True,
        )

        return [
            FeatureImportanceEntry(
                feature=feature,
                importance=float(importance),
                rank=index,
            )
            for index, (
                feature,
                importance,
            ) in enumerate(
                ranked,
                start=1,
            )
        ]

    def top(
        self,
        *,
        model: Any,
        feature_names: list[str],
        limit: int = 40,
    ) -> list[FeatureImportanceEntry]:
        if limit < 1:
            raise ValueError(
                "limit muss mindestens 1 sein."
            )

        return self.extract(
            model=model,
            feature_names=feature_names,
        )[:limit]

    def to_dataframe(
        self,
        *,
        model: Any,
        feature_names: list[str],
    ) -> pd.DataFrame:
        entries = self.extract(
            model=model,
            feature_names=feature_names,
        )

        return pd.DataFrame(
            [
                {
                    "feature": entry.feature,
                    "importance": entry.importance,
                    "rank": entry.rank,
                }
                for entry in entries
            ]
        )

    def save_json(
        self,
        *,
        model: Any,
        feature_names: list[str],
        path: str | Path,
        model_alias: str,
        algorithm: str,
        symbol: str | None = None,
        timeframe: str | None = None,
    ) -> Path:
        destination = Path(path)
        destination.parent.mkdir(
            parents=True,
            exist_ok=True,
        )

        entries = self.extract(
            model=model,
            feature_names=feature_names,
        )

        payload = {
            "model_alias": model_alias,
            "algorithm": algorithm,
            "symbol": symbol,
            "timeframe": timeframe,
            "feature_count": len(entries),
            "features": [
                {
                    "feature": entry.feature,
                    "importance": entry.importance,
                    "rank": entry.rank,
                }
                for entry in entries
            ],
        }

        destination.write_text(
            json.dumps(
                payload,
                indent=2,
                ensure_ascii=False,
            ),
            encoding="utf-8",
        )

        return destination

    @staticmethod
    def _read_importances(
        *,
        model: Any,
        feature_names: list[str],
    ) -> np.ndarray:
        if not feature_names:
            raise ValueError(
                "feature_names darf nicht leer sein."
            )

        values: np.ndarray | None = None

        if hasattr(
            model,
            "feature_importances_",
        ):
            values = np.asarray(
                model.feature_importances_,
                dtype=float,
            )

        elif hasattr(
            model,
            "get_feature_importance",
        ):
            values = np.asarray(
                model.get_feature_importance(),
                dtype=float,
            )

        elif hasattr(
            model,
            "booster_",
        ) and hasattr(
            model.booster_,
            "feature_importance",
        ):
            values = np.asarray(
                model.booster_.feature_importance(
                    importance_type="gain"
                ),
                dtype=float,
            )

        if values is None:
            raise TypeError(
                "Das Modell stellt keine unterstützte "
                "Feature-Importance-Schnittstelle bereit."
            )

        values = values.reshape(-1)

        if len(values) != len(feature_names):
            raise ValueError(
                "Anzahl der Feature-Importances stimmt nicht "
                "mit feature_names überein. "
                f"Importances: {len(values)}, "
                f"Features: {len(feature_names)}"
            )

        if not np.isfinite(values).all():
            raise ValueError(
                "Feature-Importances enthalten NaN oder "
                "unendliche Werte."
            )

        total = float(
            values.sum()
        )

        if total > 0:
            values = values / total

        return values


feature_importance_report = FeatureImportanceReport()
