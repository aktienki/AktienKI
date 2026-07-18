from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(slots=True)
class MomentumFeatureConfig:
    rsi_periods: tuple[int, ...] = (7, 14, 21)
    stochastic_period: int = 14
    stochastic_smooth: int = 3
    williams_period: int = 14
    roc_periods: tuple[int, ...] = (1, 5, 10, 20)
    cci_period: int = 20
    mfi_period: int = 14
    ppo_fast: int = 12
    ppo_slow: int = 26
    ppo_signal: int = 9
    tsi_slow: int = 25
    tsi_fast: int = 13
    momentum_periods: tuple[int, ...] = (5, 10, 20)


class MomentumFeatureBuilder:
    """
    Erzeugt konfigurierbare Momentum-Features.

    Erwartete Spalten:
    - High
    - Low
    - Close

    Optional:
    - Volume
    """

    REQUIRED_COLUMNS = {
        "High",
        "Low",
        "Close",
    }

    def __init__(
        self,
        config: MomentumFeatureConfig | None = None,
    ) -> None:
        self.config = config or MomentumFeatureConfig()
        self._validate_config()

    def transform(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        self._validate_dataframe(dataframe)

        df = dataframe.copy()

        high = pd.to_numeric(df["High"], errors="coerce")
        low = pd.to_numeric(df["Low"], errors="coerce")
        close = pd.to_numeric(df["Close"], errors="coerce")
        volume = (
            pd.to_numeric(df["Volume"], errors="coerce")
            if "Volume" in df.columns
            else None
        )

        safe_close = close.replace(0, np.nan)
        delta = close.diff()
        gains = delta.clip(lower=0.0)
        losses = -delta.clip(upper=0.0)

        for period in self.config.rsi_periods:
            avg_gain = gains.ewm(
                alpha=1.0 / period,
                adjust=False,
                min_periods=period,
            ).mean()

            avg_loss = losses.ewm(
                alpha=1.0 / period,
                adjust=False,
                min_periods=period,
            ).mean()

            rs = avg_gain / avg_loss.replace(0, np.nan)
            rsi = 100.0 - (100.0 / (1.0 + rs))

            df[f"momentum_rsi_{period}"] = rsi
            df[f"momentum_rsi_centered_{period}"] = (
                rsi - 50.0
            ) / 50.0

        rolling_low = low.rolling(
            window=self.config.stochastic_period,
            min_periods=self.config.stochastic_period,
        ).min()

        rolling_high = high.rolling(
            window=self.config.stochastic_period,
            min_periods=self.config.stochastic_period,
        ).max()

        stochastic_k = (
            100.0
            * (close - rolling_low)
            / (rolling_high - rolling_low).replace(0, np.nan)
        )

        stochastic_d = stochastic_k.rolling(
            window=self.config.stochastic_smooth,
            min_periods=self.config.stochastic_smooth,
        ).mean()

        df[
            f"momentum_stochastic_k_{self.config.stochastic_period}"
        ] = stochastic_k

        df[
            f"momentum_stochastic_d_{self.config.stochastic_period}"
        ] = stochastic_d

        williams_high = high.rolling(
            window=self.config.williams_period,
            min_periods=self.config.williams_period,
        ).max()

        williams_low = low.rolling(
            window=self.config.williams_period,
            min_periods=self.config.williams_period,
        ).min()

        df[f"momentum_williams_r_{self.config.williams_period}"] = (
            -100.0
            * (williams_high - close)
            / (williams_high - williams_low).replace(0, np.nan)
        )

        for period in self.config.roc_periods:
            df[f"momentum_roc_{period}"] = (
                close.pct_change(periods=period) * 100.0
            )

        for period in self.config.momentum_periods:
            df[f"momentum_absolute_{period}"] = (
                close - close.shift(period)
            )

            df[f"momentum_relative_{period}"] = (
                close / close.shift(period).replace(0, np.nan) - 1.0
            )

        typical_price = (
            high + low + close
        ) / 3.0

        typical_mean = typical_price.rolling(
            window=self.config.cci_period,
            min_periods=self.config.cci_period,
        ).mean()

        mean_deviation = typical_price.rolling(
            window=self.config.cci_period,
            min_periods=self.config.cci_period,
        ).apply(
            lambda values: float(
                np.mean(
                    np.abs(
                        values - np.mean(values)
                    )
                )
            ),
            raw=True,
        )

        df[f"momentum_cci_{self.config.cci_period}"] = (
            typical_price - typical_mean
        ) / (
            0.015
            * mean_deviation.replace(0, np.nan)
        )

        if volume is not None:
            raw_money_flow = typical_price * volume
            positive_flow = raw_money_flow.where(
                typical_price.diff() > 0,
                0.0,
            )
            negative_flow = raw_money_flow.where(
                typical_price.diff() < 0,
                0.0,
            ).abs()

            positive_sum = positive_flow.rolling(
                window=self.config.mfi_period,
                min_periods=self.config.mfi_period,
            ).sum()

            negative_sum = negative_flow.rolling(
                window=self.config.mfi_period,
                min_periods=self.config.mfi_period,
            ).sum()

            money_ratio = (
                positive_sum
                / negative_sum.replace(0, np.nan)
            )

            df[f"momentum_mfi_{self.config.mfi_period}"] = (
                100.0
                - (
                    100.0
                    / (
                        1.0 + money_ratio
                    )
                )
            )

        ppo_fast = close.ewm(
            span=self.config.ppo_fast,
            adjust=False,
            min_periods=self.config.ppo_fast,
        ).mean()

        ppo_slow = close.ewm(
            span=self.config.ppo_slow,
            adjust=False,
            min_periods=self.config.ppo_slow,
        ).mean()

        ppo = (
            100.0
            * (ppo_fast - ppo_slow)
            / ppo_slow.replace(0, np.nan)
        )

        ppo_signal = ppo.ewm(
            span=self.config.ppo_signal,
            adjust=False,
            min_periods=self.config.ppo_signal,
        ).mean()

        df["momentum_ppo"] = ppo
        df["momentum_ppo_signal"] = ppo_signal
        df["momentum_ppo_histogram"] = (
            ppo - ppo_signal
        )

        price_change = close.diff()

        smoothed_change = price_change.ewm(
            span=self.config.tsi_slow,
            adjust=False,
            min_periods=self.config.tsi_slow,
        ).mean().ewm(
            span=self.config.tsi_fast,
            adjust=False,
            min_periods=self.config.tsi_fast,
        ).mean()

        smoothed_abs_change = price_change.abs().ewm(
            span=self.config.tsi_slow,
            adjust=False,
            min_periods=self.config.tsi_slow,
        ).mean().ewm(
            span=self.config.tsi_fast,
            adjust=False,
            min_periods=self.config.tsi_fast,
        ).mean()

        df["momentum_tsi"] = (
            100.0
            * smoothed_change
            / smoothed_abs_change.replace(0, np.nan)
        )

        df["momentum_price_acceleration"] = (
            close.pct_change().diff()
        )

        df["momentum_distance_from_20"] = (
            close
            / close.rolling(
                window=20,
                min_periods=20,
            ).mean().replace(0, np.nan)
            - 1.0
        )

        df["momentum_relative_strength_20_60"] = (
            close.pct_change(20)
            - close.pct_change(60)
        )

        df["momentum_close_normalized"] = (
            close
            / safe_close.rolling(
                window=20,
                min_periods=20,
            ).mean().replace(0, np.nan)
        )

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    def build(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        return self.transform(dataframe)

    def _validate_config(self) -> None:
        period_groups = (
            self.config.rsi_periods,
            self.config.roc_periods,
            self.config.momentum_periods,
        )

        for periods in period_groups:
            if not periods:
                raise ValueError(
                    "Periodenlisten dürfen nicht leer sein."
                )

            if any(period < 1 for period in periods):
                raise ValueError(
                    "Alle Perioden müssen mindestens 1 sein."
                )

        if self.config.stochastic_period < 2:
            raise ValueError(
                "stochastic_period muss mindestens 2 sein."
            )

        if self.config.stochastic_smooth < 1:
            raise ValueError(
                "stochastic_smooth muss mindestens 1 sein."
            )

        if self.config.williams_period < 2:
            raise ValueError(
                "williams_period muss mindestens 2 sein."
            )

        if self.config.cci_period < 2:
            raise ValueError(
                "cci_period muss mindestens 2 sein."
            )

        if self.config.mfi_period < 2:
            raise ValueError(
                "mfi_period muss mindestens 2 sein."
            )

        if self.config.ppo_fast >= self.config.ppo_slow:
            raise ValueError(
                "ppo_fast muss kleiner als ppo_slow sein."
            )

        if self.config.ppo_signal < 1:
            raise ValueError(
                "ppo_signal muss mindestens 1 sein."
            )

        if self.config.tsi_fast < 1:
            raise ValueError(
                "tsi_fast muss mindestens 1 sein."
            )

        if self.config.tsi_slow < 1:
            raise ValueError(
                "tsi_slow muss mindestens 1 sein."
            )

    @classmethod
    def _validate_dataframe(
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
                "MomentumFeatureBuilder fehlen Spalten: "
                f"{missing}"
            )


momentum_feature_builder = MomentumFeatureBuilder()
