from __future__ import annotations

from collections.abc import Iterable

import numpy as np
import pandas as pd


class TargetBuilder:
    """
    Erzeugt zukunftsbezogene Trainingsziele für AktienKI.

    Unterstützt:
    - einzelner Horizont über ``build()``
    - mehrere Horizonte über ``build_multi_horizon()``
    - Long- und Short-Ziele
    - Expected Return
    - Maximalgewinn, Maximalverlust, Volatilität und Risk/Reward

    Rückwärtskompatibilität:
    Bei ``build()`` bleiben die bisherigen unbenannten Spalten erhalten,
    darunter ``target_return``, ``target_direction`` und ``target``.
    """

    REQUIRED_COLUMNS = {
        "High",
        "Low",
        "Close",
    }

    DEFAULT_HORIZONS = (
        1,
        5,
        20,
        60,
    )

    @classmethod
    def build(
        cls,
        dataframe: pd.DataFrame,
        *,
        horizon: int,
        direction_threshold: float = 0.0,
        probability_clip: float = 0.30,
        suffix: str | None = None,
        add_legacy_aliases: bool = True,
    ) -> pd.DataFrame:
        """
        Erzeugt Targets für genau einen Horizont.

        Parameter:
        - ``horizon``: Anzahl zukünftiger Perioden
        - ``direction_threshold``: neutrale Schwelle für Long/Short
        - ``probability_clip``: Return-Bereich für die Zielwahrscheinlichkeit
        - ``suffix``: optionaler Spaltensuffix, zum Beispiel ``5d``
        - ``add_legacy_aliases``: erzeugt die bisherigen Spaltennamen
        """

        cls._validate_parameters(
            horizon=horizon,
            direction_threshold=direction_threshold,
            probability_clip=probability_clip,
        )
        cls._validate_columns(dataframe)

        df = dataframe.copy()

        close = pd.to_numeric(
            df["Close"],
            errors="coerce",
        )
        high = pd.to_numeric(
            df["High"],
            errors="coerce",
        )
        low = pd.to_numeric(
            df["Low"],
            errors="coerce",
        )

        safe_close = close.replace(
            0,
            np.nan,
        )

        future_close = close.shift(
            -horizon
        )

        future_return = (
            future_close
            / safe_close
            - 1.0
        )

        future_high = cls._forward_rolling_max(
            high,
            horizon,
        )
        future_low = cls._forward_rolling_min(
            low,
            horizon,
        )

        future_max_gain = (
            future_high
            / safe_close
            - 1.0
        )

        future_max_loss = (
            future_low
            / safe_close
            - 1.0
        )

        future_period_returns = close.pct_change()

        future_volatility = cls._forward_rolling_std(
            future_period_returns,
            horizon,
        )

        downside_risk = (
            future_max_loss
            .clip(upper=0.0)
            .abs()
            .replace(0.0, np.nan)
        )

        upside_capture = future_max_gain.clip(
            lower=0.0
        )

        risk_reward = (
            upside_capture
            / downside_risk
        )

        direction = cls._direction(
            future_return=future_return,
            threshold=direction_threshold,
        )

        probability = cls._probability(
            future_return=future_return,
            probability_clip=probability_clip,
        )

        confidence = cls._confidence(
            future_volatility=future_volatility,
        )

        short_return = -future_return
        short_probability = 1.0 - probability

        short_max_gain = (
            future_max_loss
            .clip(upper=0.0)
            .abs()
        )

        short_max_loss = (
            future_max_gain
            .clip(lower=0.0)
        )

        short_downside = short_max_loss.replace(
            0.0,
            np.nan,
        )

        short_risk_reward = (
            short_max_gain
            / short_downside
        )

        expected_return = cls._expected_return(
            probability=probability,
            future_max_gain=future_max_gain,
            future_max_loss=future_max_loss,
        )

        short_expected_return = cls._expected_return(
            probability=short_probability,
            future_max_gain=short_max_gain,
            future_max_loss=-short_max_loss,
        )

        target_values = {
            "target_return": future_return,
            "target_direction": direction,
            "target_max_gain": future_max_gain,
            "target_max_loss": future_max_loss,
            "target_volatility": future_volatility,
            "target_risk_reward": risk_reward,
            "target_probability": probability,
            "target_confidence": confidence,
            "target_upside_capture": upside_capture,
            "target_downside_risk": downside_risk,
            "target_expected_return": expected_return,
            "target_short_return": short_return,
            "target_short_probability": short_probability,
            "target_short_max_gain": short_max_gain,
            "target_short_max_loss": short_max_loss,
            "target_short_risk_reward": short_risk_reward,
            "target_short_expected_return": short_expected_return,
        }

        normalized_suffix = cls._normalize_suffix(
            suffix
        )

        for name, values in target_values.items():
            output_name = (
                f"{name}_{normalized_suffix}"
                if normalized_suffix
                else name
            )
            df[output_name] = values

        if add_legacy_aliases:
            cls._add_legacy_aliases(
                dataframe=df,
                target_values=target_values,
            )

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @classmethod
    def build_multi_horizon(
        cls,
        dataframe: pd.DataFrame,
        *,
        horizons: Iterable[int] = DEFAULT_HORIZONS,
        direction_threshold: float = 0.0,
        probability_clip: float = 0.30,
        suffix_template: str = "{horizon}d",
        legacy_horizon: int = 5,
    ) -> pd.DataFrame:
        """
        Erzeugt Targets für mehrere Horizonte.

        Standardmäßig entstehen:
        - target_return_1d
        - target_return_5d
        - target_return_20d
        - target_return_60d

        Entsprechende Direction-, Risiko-, Probability-, Short- und
        Expected-Return-Spalten werden ebenfalls erzeugt.
        """

        normalized_horizons = cls._normalize_horizons(
            horizons
        )

        if legacy_horizon not in normalized_horizons:
            raise ValueError(
                "legacy_horizon muss in horizons enthalten sein."
            )

        result = dataframe.copy()

        for horizon in normalized_horizons:
            suffix = suffix_template.format(
                horizon=horizon
            )

            result = cls.build(
                result,
                horizon=horizon,
                direction_threshold=direction_threshold,
                probability_clip=probability_clip,
                suffix=suffix,
                add_legacy_aliases=False,
            )

        legacy_suffix = cls._normalize_suffix(
            suffix_template.format(
                horizon=legacy_horizon
            )
        )

        cls._add_legacy_aliases_from_suffix(
            dataframe=result,
            suffix=legacy_suffix,
        )

        return result.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @staticmethod
    def _direction(
        *,
        future_return: pd.Series,
        threshold: float,
    ) -> pd.Series:
        direction = pd.Series(
            0,
            index=future_return.index,
            dtype="int8",
        )

        direction.loc[
            future_return > threshold
        ] = 1

        direction.loc[
            future_return < -threshold
        ] = -1

        return direction

    @staticmethod
    def _probability(
        *,
        future_return: pd.Series,
        probability_clip: float,
    ) -> pd.Series:
        clipped_return = future_return.clip(
            lower=-probability_clip,
            upper=probability_clip,
        )

        return (
            (
                clipped_return
                + probability_clip
            )
            / (
                2.0
                * probability_clip
            )
        ).clip(
            lower=0.0,
            upper=1.0,
        )

    @staticmethod
    def _confidence(
        *,
        future_volatility: pd.Series,
    ) -> pd.Series:
        return (
            1.0
            / (
                1.0
                + future_volatility.abs()
            )
        ).clip(
            lower=0.0,
            upper=1.0,
        )

    @staticmethod
    def _expected_return(
        *,
        probability: pd.Series,
        future_max_gain: pd.Series,
        future_max_loss: pd.Series,
    ) -> pd.Series:
        gain = future_max_gain.clip(
            lower=0.0
        )

        loss = (
            future_max_loss
            .clip(upper=0.0)
            .abs()
        )

        return (
            probability
            * gain
            - (
                1.0
                - probability
            )
            * loss
        )

    @staticmethod
    def _forward_rolling_max(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        return (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .max()
            .iloc[::-1]
        )

    @staticmethod
    def _forward_rolling_min(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        return (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .min()
            .iloc[::-1]
        )

    @staticmethod
    def _forward_rolling_std(
        series: pd.Series,
        horizon: int,
    ) -> pd.Series:
        reversed_series = (
            series
            .shift(-1)
            .iloc[::-1]
        )

        return (
            reversed_series
            .rolling(
                window=horizon,
                min_periods=horizon,
            )
            .std()
            .iloc[::-1]
        )

    @classmethod
    def _add_legacy_aliases(
        cls,
        *,
        dataframe: pd.DataFrame,
        target_values: dict[str, pd.Series],
    ) -> None:
        for name, values in target_values.items():
            dataframe[name] = values

        dataframe["target"] = dataframe[
            "target_return"
        ]

    @classmethod
    def _add_legacy_aliases_from_suffix(
        cls,
        *,
        dataframe: pd.DataFrame,
        suffix: str,
    ) -> None:
        base_names = (
            "target_return",
            "target_direction",
            "target_max_gain",
            "target_max_loss",
            "target_volatility",
            "target_risk_reward",
            "target_probability",
            "target_confidence",
            "target_upside_capture",
            "target_downside_risk",
            "target_expected_return",
            "target_short_return",
            "target_short_probability",
            "target_short_max_gain",
            "target_short_max_loss",
            "target_short_risk_reward",
            "target_short_expected_return",
        )

        for base_name in base_names:
            source_name = (
                f"{base_name}_{suffix}"
            )

            if source_name not in dataframe.columns:
                raise ValueError(
                    "Legacy-Zielspalte fehlt: "
                    f"{source_name}"
                )

            dataframe[base_name] = dataframe[
                source_name
            ]

        dataframe["target"] = dataframe[
            "target_return"
        ]

    @staticmethod
    def _normalize_suffix(
        suffix: str | None,
    ) -> str | None:
        if suffix is None:
            return None

        normalized = (
            str(suffix)
            .strip()
            .lower()
            .replace(" ", "_")
            .replace("-", "_")
        )

        if not normalized:
            raise ValueError(
                "suffix darf nicht leer sein."
            )

        return normalized

    @staticmethod
    def _normalize_horizons(
        horizons: Iterable[int],
    ) -> tuple[int, ...]:
        normalized = tuple(
            dict.fromkeys(
                int(horizon)
                for horizon in horizons
            )
        )

        if not normalized:
            raise ValueError(
                "Mindestens ein Horizont ist erforderlich."
            )

        invalid = [
            horizon
            for horizon in normalized
            if horizon < 1
        ]

        if invalid:
            raise ValueError(
                "Horizonte müssen mindestens 1 sein: "
                f"{invalid}"
            )

        return normalized

    @classmethod
    def _validate_columns(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        if not isinstance(
            dataframe,
            pd.DataFrame,
        ):
            raise TypeError(
                "dataframe muss ein pandas DataFrame sein."
            )

        if dataframe.empty:
            raise ValueError(
                "dataframe darf nicht leer sein."
            )

        missing = sorted(
            cls.REQUIRED_COLUMNS.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "TargetBuilder fehlen Spalten: "
                f"{missing}"
            )

    @staticmethod
    def _validate_parameters(
        *,
        horizon: int,
        direction_threshold: float,
        probability_clip: float,
    ) -> None:
        if horizon < 1:
            raise ValueError(
                "horizon muss mindestens 1 sein."
            )

        if direction_threshold < 0:
            raise ValueError(
                "direction_threshold darf nicht negativ sein."
            )

        if probability_clip <= 0:
            raise ValueError(
                "probability_clip muss größer als 0 sein."
            )


target_builder = TargetBuilder()
