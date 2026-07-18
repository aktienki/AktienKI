from __future__ import annotations

from collections.abc import Sequence

import pandas as pd
from sqlalchemy import text


class FeatureStoreRepository:
    """
    Lädt bereits berechnete Trainingsdaten aus der Tabelle ``feature_store``.

    Wichtig:
    - Dieses Repository berechnet keine Features neu.
    - Die TrainingEngine erhält hier bereits fertige Feature-Zeilen.
    - Fehlende optionale Marktfeatures werden vorerst mit 0.0 ergänzt.
    - Die bestehende öffentliche API bleibt vollständig kompatibel.
    """

    BASE_FEATURE_COLUMNS = [
        "close",
        "volume",
        "rsi_14",
        "ema_20",
        "ema_50",
        "ema_200",
        "macd",
        "atr_14",
        "volatility_20",
    ]

    MARKET_FEATURE_COLUMNS = [
        "market_bull_score",
        "market_bear_score",
        "market_volatility_score",
        "market_liquidity_score",
        "market_risk_score",
        "market_momentum_score",
    ]

    FEATURE_COLUMNS = [
        *BASE_FEATURE_COLUMNS,
        *MARKET_FEATURE_COLUMNS,
    ]

    SUPPORTED_TARGETS = {
        "target_return_1d",
        "target_return_5d",
        "target_return_20d",
    }

    def __init__(self, session) -> None:
        self.session = session

    @classmethod
    def feature_columns(
        cls,
        *,
        include_market: bool = True,
    ) -> list[str]:
        """
        Liefert die Feature-Spalten in stabiler Reihenfolge.

        ``FEATURE_COLUMNS`` bleibt für bestehenden Code erhalten.
        Neue Aufrufer können diese Methode verwenden.
        """

        columns = list(cls.BASE_FEATURE_COLUMNS)

        if include_market:
            columns.extend(cls.MARKET_FEATURE_COLUMNS)

        return columns

    def load_training_data(
        self,
        *,
        instrument_id: int,
        interval: str = "1d",
        feature_version: str = "1.0.0",
        target_name: str = "target_return_5d",
    ) -> pd.DataFrame:
        """
        Lädt chronologisch sortierte und vollständig bereinigte
        Trainingsdaten für genau ein Instrument.

        Das ausgewählte Target wird als einheitliche Spalte ``target``
        zurückgegeben.
        """

        self._validate_arguments(
            instrument_id=instrument_id,
            interval=interval,
            feature_version=feature_version,
            target_name=target_name,
        )

        columns_sql = ", ".join(
            self.BASE_FEATURE_COLUMNS
        )

        query = text(
            f"""
            SELECT
                bar_time,
                {columns_sql},
                {target_name} AS target
            FROM feature_store
            WHERE instrument_id = :instrument_id
              AND interval = :interval
              AND feature_version = :feature_version
              AND {target_name} IS NOT NULL
            ORDER BY bar_time ASC
            """
        )

        rows = (
            self.session.execute(
                query,
                {
                    "instrument_id": instrument_id,
                    "interval": interval,
                    "feature_version": feature_version,
                },
            )
            .mappings()
            .all()
        )

        if not rows:
            return self._empty_training_frame()

        frame = pd.DataFrame(
            rows
        )

        frame["bar_time"] = pd.to_datetime(
            frame["bar_time"],
            utc=True,
            errors="coerce",
        )

        numeric_columns = [
            *self.BASE_FEATURE_COLUMNS,
            "target",
        ]

        for column in numeric_columns:
            frame[column] = pd.to_numeric(
                frame[column],
                errors="coerce",
            )

        frame = (
            frame
            .dropna(
                subset=[
                    "bar_time",
                    *numeric_columns,
                ]
            )
            .sort_values(
                "bar_time"
            )
            .drop_duplicates(
                subset=["bar_time"],
                keep="last",
            )
            .reset_index(
                drop=True
            )
        )

        self._add_missing_market_features(
            frame
        )

        return frame[
            [
                "bar_time",
                *self.FEATURE_COLUMNS,
                "target",
            ]
        ]

    @classmethod
    def _add_missing_market_features(
        cls,
        frame: pd.DataFrame,
    ) -> None:
        """
        Übergangslösung bis die Marktfeatures tatsächlich in der
        Feature-Store-Pipeline gespeichert werden.

        Sobald echte Marktfeatures in ``feature_store`` vorhanden sind,
        wird diese Methode durch das Laden der realen Spalten ersetzt.
        """

        for column in cls.MARKET_FEATURE_COLUMNS:
            if column not in frame.columns:
                frame[column] = 0.0

    @classmethod
    def _empty_training_frame(
        cls,
    ) -> pd.DataFrame:
        return pd.DataFrame(
            columns=[
                "bar_time",
                *cls.FEATURE_COLUMNS,
                "target",
            ]
        )

    @classmethod
    def _validate_arguments(
        cls,
        *,
        instrument_id: int,
        interval: str,
        feature_version: str,
        target_name: str,
    ) -> None:
        if instrument_id < 1:
            raise ValueError(
                "instrument_id muss größer als 0 sein."
            )

        if not str(interval).strip():
            raise ValueError(
                "interval darf nicht leer sein."
            )

        if not str(feature_version).strip():
            raise ValueError(
                "feature_version darf nicht leer sein."
            )

        if target_name not in cls.SUPPORTED_TARGETS:
            supported = ", ".join(
                sorted(
                    cls.SUPPORTED_TARGETS
                )
            )

            raise ValueError(
                f"Nicht unterstütztes Target: {target_name}. "
                f"Unterstützt: {supported}"
            )

    @staticmethod
    def validate_required_columns(
        dataframe: pd.DataFrame,
        required_columns: Sequence[str],
    ) -> None:
        """
        Hilfsmethode für spätere Integrations- und Repository-Tests.
        """

        missing = sorted(
            set(required_columns).difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "Im Feature-Store fehlen Spalten: "
                f"{missing}"
            )
