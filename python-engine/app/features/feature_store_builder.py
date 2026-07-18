from __future__ import annotations

import hashlib
import json
from dataclasses import asdict, dataclass, field
from typing import Any, Iterable, Mapping, Sequence

import numpy as np
import pandas as pd

from app.features.candlestick_features import CandlestickFeatures
from app.features.market_features import MarketFeatures
from app.features.momentum_features import MomentumFeatures
from app.features.time_features import TimeFeatures
from app.features.trend_features import TrendFeatures
from app.features.volume_features import VolumeFeatures
from app.features.volatility_features import VolatilityFeatures


@dataclass(slots=True)
class FeatureStoreConfig:
    """
    Versionierte Konfiguration für den zentralen Feature Store.
    """

    version: str = "2.0.0"

    enabled_blocks: tuple[str, ...] = (
        "trend",
        "momentum",
        "volume",
        "volatility",
        "candlestick",
        "time",
        "market",
    )

    required_price_columns: tuple[str, ...] = (
        "Open",
        "High",
        "Low",
        "Close",
        "Volume",
    )

    base_columns: tuple[str, ...] = (
        "Open",
        "High",
        "Low",
        "Close",
        "Adj Close",
        "Volume",
    )

    external_prefixes: dict[str, str] = field(
        default_factory=lambda: {
            "market": "market",
            "cross_asset": "cross",
            "sector": "sector",
            "fundamental": "fundamental",
            "macro": "macro",
        }
    )

    forward_fill_external: bool = True
    external_fill_limit: int | None = None
    drop_all_nan_features: bool = True
    remove_constant_features: bool = False

    def fingerprint(self) -> str:
        payload = json.dumps(
            asdict(self),
            sort_keys=True,
            ensure_ascii=False,
            default=str,
        ).encode("utf-8")

        return hashlib.sha256(payload).hexdigest()


@dataclass(slots=True)
class FeatureBuildResult:
    dataframe: pd.DataFrame
    feature_names: list[str]
    feature_version: str
    feature_hash: str
    rows: int
    columns: int
    dropped_features: list[str]


class FeatureStoreBuilder:
    """
    Zentraler, reproduzierbarer Feature-Store-Builder.

    Aufgaben:
    - vorhandene Feature-Blöcke in definierter Reihenfolge ausführen
    - externe Zeitreihen strikt zeitlich ausrichten
    - Feature-Namen stabil und eindeutig halten
    - Target-Leakage verhindern
    - Feature-Version und Feature-Hash erzeugen

    Externe Zeitreihen werden ausschließlich rückwärtsgerichtet
    zusammengeführt. Dadurch kann kein Wert verwendet werden, der zum
    Zeitpunkt der Zielzeile noch nicht bekannt war.
    """

    BLOCKS: dict[str, Any] = {
        "trend": TrendFeatures,
        "momentum": MomentumFeatures,
        "volume": VolumeFeatures,
        "volatility": VolatilityFeatures,
        "candlestick": CandlestickFeatures,
        "time": TimeFeatures,
        "market": MarketFeatures,
    }

    TARGET_PREFIXES = (
        "target",
        "label",
        "future_",
    )

    def __init__(
        self,
        config: FeatureStoreConfig | None = None,
    ) -> None:
        self.config = config or FeatureStoreConfig()
        self._validate_config()

    def build(
        self,
        *,
        prices: pd.DataFrame,
        market_data: pd.DataFrame | None = None,
        cross_asset_data: pd.DataFrame | None = None,
        sector_data: pd.DataFrame | None = None,
        macro_data: pd.DataFrame | None = None,
        fundamental_data: pd.DataFrame | None = None,
        custom_frames: Mapping[str, pd.DataFrame] | None = None,
    ) -> FeatureBuildResult:
        """
        Erstellt den vollständigen Feature Store.

        Alle externen DataFrames müssen entweder einen DatetimeIndex oder
        eine der Zeitspalten `bar_time`, `timestamp`, `date`, `datetime`
        enthalten.
        """

        dataframe = self._prepare_prices(prices)

        for block_name in self.config.enabled_blocks:
            block = self.BLOCKS[block_name]
            dataframe = block.transform(dataframe)

            if not isinstance(dataframe, pd.DataFrame):
                raise TypeError(
                    f"Feature-Block '{block_name}' lieferte keinen DataFrame."
                )

        external_frames: list[tuple[str, pd.DataFrame | None]] = [
            (
                self.config.external_prefixes["market"],
                market_data,
            ),
            (
                self.config.external_prefixes["cross_asset"],
                cross_asset_data,
            ),
            (
                self.config.external_prefixes["sector"],
                sector_data,
            ),
            (
                self.config.external_prefixes["macro"],
                macro_data,
            ),
            (
                self.config.external_prefixes["fundamental"],
                fundamental_data,
            ),
        ]

        if custom_frames:
            external_frames.extend(custom_frames.items())

        for prefix, external_frame in external_frames:
            if external_frame is None:
                continue

            dataframe = self._merge_asof(
                base=dataframe,
                external=external_frame,
                prefix=prefix,
            )

        dataframe = dataframe.replace(
            [np.inf, -np.inf],
            np.nan,
        )

        dropped_features: list[str] = []

        if self.config.drop_all_nan_features:
            all_nan = [
                column
                for column in self.feature_columns(dataframe)
                if dataframe[column].isna().all()
            ]

            if all_nan:
                dataframe = dataframe.drop(columns=all_nan)
                dropped_features.extend(all_nan)

        if self.config.remove_constant_features:
            constants = [
                column
                for column in self.feature_columns(dataframe)
                if dataframe[column].dropna().nunique() <= 1
            ]

            if constants:
                dataframe = dataframe.drop(columns=constants)
                dropped_features.extend(constants)

        feature_names = self.feature_columns(dataframe)
        feature_hash = self.feature_hash(feature_names)

        dataframe.attrs["feature_version"] = self.config.version
        dataframe.attrs["feature_hash"] = feature_hash
        dataframe.attrs["feature_config_hash"] = (
            self.config.fingerprint()
        )

        return FeatureBuildResult(
            dataframe=dataframe,
            feature_names=feature_names,
            feature_version=self.config.version,
            feature_hash=feature_hash,
            rows=len(dataframe),
            columns=len(dataframe.columns),
            dropped_features=sorted(set(dropped_features)),
        )

    def transform(
        self,
        dataframe: pd.DataFrame,
        **kwargs: Any,
    ) -> pd.DataFrame:
        """
        Kompatibler Kurzweg für bestehenden Code.
        """

        return self.build(
            prices=dataframe,
            **kwargs,
        ).dataframe

    def feature_columns(
        self,
        dataframe: pd.DataFrame,
    ) -> list[str]:
        """
        Liefert ausschließlich numerische Eingangsmerkmale.
        Preis-Rohspalten, Targets und Labels werden ausgeschlossen.
        """

        base_columns_lower = {
            column.lower()
            for column in self.config.base_columns
        }

        feature_names: list[str] = []

        for column in dataframe.columns:
            column_name = str(column)
            normalized = column_name.lower()

            if normalized in base_columns_lower:
                continue

            if self._is_target_column(normalized):
                continue

            series = dataframe[column]

            if not pd.api.types.is_numeric_dtype(series):
                continue

            feature_names.append(column_name)

        self._validate_unique(feature_names)

        return feature_names

    @staticmethod
    def feature_hash(
        feature_names: Sequence[str],
    ) -> str:
        """
        Stabiler Hash der exakten Feature-Reihenfolge.
        """

        payload = json.dumps(
            list(feature_names),
            ensure_ascii=False,
            separators=(",", ":"),
        ).encode("utf-8")

        return hashlib.sha256(payload).hexdigest()

    def latest_feature_row(
        self,
        result: FeatureBuildResult | pd.DataFrame,
        *,
        drop_incomplete: bool = True,
    ) -> pd.DataFrame:
        """
        Liefert die aktuellste Feature-Zeile für die Prediction Engine.
        """

        if isinstance(result, FeatureBuildResult):
            dataframe = result.dataframe
            feature_names = result.feature_names
        else:
            dataframe = result
            feature_names = self.feature_columns(dataframe)

        feature_frame = dataframe[feature_names].replace(
            [np.inf, -np.inf],
            np.nan,
        )

        if drop_incomplete:
            feature_frame = feature_frame.dropna()

        if feature_frame.empty:
            raise ValueError(
                "Keine vollständige Feature-Zeile für die Vorhersage vorhanden."
            )

        return feature_frame.tail(1).copy()

    def training_frame(
        self,
        result: FeatureBuildResult | pd.DataFrame,
        *,
        target_names: str | Iterable[str],
        minimum_rows: int = 300,
    ) -> pd.DataFrame:
        """
        Erzeugt einen bereinigten Trainings-DataFrame.
        """

        if minimum_rows < 1:
            raise ValueError(
                "minimum_rows muss mindestens 1 sein."
            )

        dataframe = (
            result.dataframe
            if isinstance(result, FeatureBuildResult)
            else result
        )

        feature_names = (
            result.feature_names
            if isinstance(result, FeatureBuildResult)
            else self.feature_columns(dataframe)
        )

        targets = (
            [target_names]
            if isinstance(target_names, str)
            else list(target_names)
        )

        if not targets:
            raise ValueError(
                "Mindestens eine Zielspalte ist erforderlich."
            )

        missing = [
            column
            for column in [*feature_names, *targets]
            if column not in dataframe.columns
        ]

        if missing:
            raise ValueError(
                "Folgende Trainingsspalten fehlen: "
                f"{missing}"
            )

        training = (
            dataframe[[*feature_names, *targets]]
            .replace(
                [np.inf, -np.inf],
                np.nan,
            )
            .dropna()
            .copy()
        )

        if len(training) < minimum_rows:
            raise ValueError(
                "Zu wenige vollständige Trainingszeilen: "
                f"{len(training)}. Benötigt: {minimum_rows}."
            )

        return training

    def _prepare_prices(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        if not isinstance(dataframe, pd.DataFrame):
            raise TypeError(
                "prices muss ein pandas DataFrame sein."
            )

        if dataframe.empty:
            raise ValueError(
                "prices darf nicht leer sein."
            )

        prepared = self._ensure_datetime_index(dataframe.copy())
        prepared = prepared.sort_index()

        if prepared.index.has_duplicates:
            prepared = prepared[
                ~prepared.index.duplicated(keep="last")
            ]

        missing = [
            column
            for column in self.config.required_price_columns
            if column not in prepared.columns
        ]

        if missing:
            raise ValueError(
                "Folgende Preis-Spalten fehlen: "
                f"{missing}"
            )

        if not prepared.index.is_monotonic_increasing:
            raise ValueError(
                "Der Preisindex konnte nicht chronologisch sortiert werden."
            )

        return prepared

    def _merge_asof(
        self,
        *,
        base: pd.DataFrame,
        external: pd.DataFrame,
        prefix: str,
    ) -> pd.DataFrame:
        if not isinstance(external, pd.DataFrame):
            raise TypeError(
                f"Externe Daten '{prefix}' müssen ein DataFrame sein."
            )

        if external.empty:
            return base

        external_frame = self._ensure_datetime_index(
            external.copy()
        ).sort_index()

        if external_frame.index.has_duplicates:
            external_frame = external_frame[
                ~external_frame.index.duplicated(keep="last")
            ]

        external_frame = external_frame.select_dtypes(
            include=[np.number, "bool"]
        )

        if external_frame.empty:
            return base

        safe_prefix = self._safe_prefix(prefix)

        rename_map = {
            column: (
                column
                if str(column).startswith(f"{safe_prefix}_")
                else f"{safe_prefix}_{column}"
            )
            for column in external_frame.columns
        }

        external_frame = external_frame.rename(
            columns=rename_map
        )

        collisions = sorted(
            set(base.columns).intersection(
                external_frame.columns
            )
        )

        if collisions:
            raise ValueError(
                "Doppelte Feature-Namen beim Zusammenführen: "
                f"{collisions}"
            )

        left = base.reset_index()
        right = external_frame.reset_index()

        left_time = str(left.columns[0])
        right_time = str(right.columns[0])

        if left_time != "__feature_time__":
            left = left.rename(
                columns={left_time: "__feature_time__"}
            )

        if right_time != "__external_time__":
            right = right.rename(
                columns={right_time: "__external_time__"}
            )

        merged = pd.merge_asof(
            left.sort_values("__feature_time__"),
            right.sort_values("__external_time__"),
            left_on="__feature_time__",
            right_on="__external_time__",
            direction="backward",
            allow_exact_matches=True,
        )

        merged = merged.drop(
            columns=["__external_time__"],
            errors="ignore",
        ).set_index("__feature_time__")

        merged.index.name = base.index.name

        external_columns = list(
            external_frame.columns
        )

        if self.config.forward_fill_external:
            merged[external_columns] = merged[
                external_columns
            ].ffill(
                limit=self.config.external_fill_limit
            )

        return merged

    @classmethod
    def _ensure_datetime_index(
        cls,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        if isinstance(
            dataframe.index,
            pd.DatetimeIndex,
        ):
            dataframe.index = pd.to_datetime(
                dataframe.index,
                utc=True,
                errors="raise",
            )

            return dataframe

        time_columns = (
            "bar_time",
            "timestamp",
            "datetime",
            "date",
            "Date",
        )

        time_column = next(
            (
                column
                for column in time_columns
                if column in dataframe.columns
            ),
            None,
        )

        if time_column is None:
            raise ValueError(
                "DataFrame benötigt einen DatetimeIndex oder eine "
                "Zeitspalte: bar_time, timestamp, datetime oder date."
            )

        dataframe[time_column] = pd.to_datetime(
            dataframe[time_column],
            utc=True,
            errors="raise",
        )

        return dataframe.set_index(time_column)

    @classmethod
    def _is_target_column(
        cls,
        normalized_column: str,
    ) -> bool:
        if normalized_column in {
            "target",
            "label",
            "y",
        }:
            return True

        return normalized_column.startswith(
            cls.TARGET_PREFIXES
        )

    @staticmethod
    def _safe_prefix(
        prefix: str,
    ) -> str:
        cleaned = (
            str(prefix)
            .strip()
            .lower()
            .replace(" ", "_")
            .replace("-", "_")
        )

        if not cleaned:
            raise ValueError(
                "Der Prefix für externe Features darf nicht leer sein."
            )

        return cleaned

    def _validate_config(self) -> None:
        if not self.config.version.strip():
            raise ValueError(
                "Die Feature-Version darf nicht leer sein."
            )

        unknown_blocks = sorted(
            set(self.config.enabled_blocks).difference(
                self.BLOCKS
            )
        )

        if unknown_blocks:
            raise ValueError(
                "Unbekannte Feature-Blöcke: "
                f"{unknown_blocks}"
            )

        if len(set(self.config.enabled_blocks)) != len(
            self.config.enabled_blocks
        ):
            raise ValueError(
                "enabled_blocks enthält doppelte Einträge."
            )

    @staticmethod
    def _validate_unique(
        feature_names: Sequence[str],
    ) -> None:
        seen: set[str] = set()
        duplicates: set[str] = set()

        for feature_name in feature_names:
            if feature_name in seen:
                duplicates.add(feature_name)

            seen.add(feature_name)

        if duplicates:
            raise ValueError(
                "Doppelte Feature-Namen erkannt: "
                f"{sorted(duplicates)}"
            )


feature_store_builder = FeatureStoreBuilder()
