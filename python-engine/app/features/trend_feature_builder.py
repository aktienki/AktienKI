from __future__ import annotations

from dataclasses import dataclass, field

import numpy as np
import pandas as pd


@dataclass(slots=True)
class TrendFeatureConfig:
    sma_periods: tuple[int, ...] = (20, 50, 100, 200)
    ema_periods: tuple[int, ...] = (9, 12, 20, 26, 50, 100, 200)
    macd_fast: int = 12
    macd_slow: int = 26
    macd_signal: int = 9
    adx_period: int = 14
    slope_periods: tuple[int, ...] = (5, 20, 50)


class TrendFeatureBuilder:
    """
    Erzeugt konfigurierbare Trend-Features.

    Erwartete Spalten:
    - High
    - Low
    - Close
    """

    REQUIRED_COLUMNS = {"High", "Low", "Close"}

    def __init__(
        self,
        config: TrendFeatureConfig | None = None,
    ) -> None:
        self.config = config or TrendFeatureConfig()
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
        safe_close = close.replace(0, np.nan)

        for period in self.config.sma_periods:
            sma = close.rolling(
                window=period,
                min_periods=period,
            ).mean()

            df[f"trend_sma_{period}"] = sma
            df[f"trend_distance_sma_{period}"] = (
                close / sma.replace(0, np.nan) - 1.0
            )

        for period in self.config.ema_periods:
            ema = close.ewm(
                span=period,
                adjust=False,
                min_periods=period,
            ).mean()

            df[f"trend_ema_{period}"] = ema
            df[f"trend_distance_ema_{period}"] = (
                close / ema.replace(0, np.nan) - 1.0
            )

        macd_fast = close.ewm(
            span=self.config.macd_fast,
            adjust=False,
            min_periods=self.config.macd_fast,
        ).mean()

        macd_slow = close.ewm(
            span=self.config.macd_slow,
            adjust=False,
            min_periods=self.config.macd_slow,
        ).mean()

        macd = macd_fast - macd_slow

        macd_signal = macd.ewm(
            span=self.config.macd_signal,
            adjust=False,
            min_periods=self.config.macd_signal,
        ).mean()

        macd_histogram = macd - macd_signal

        df["trend_macd"] = macd
        df["trend_macd_signal"] = macd_signal
        df["trend_macd_histogram"] = macd_histogram
        df["trend_macd_pct"] = macd / safe_close
        df["trend_macd_signal_pct"] = macd_signal / safe_close
        df["trend_macd_histogram_pct"] = macd_histogram / safe_close

        plus_di, minus_di, adx = self._adx(
            high=high,
            low=low,
            close=close,
            period=self.config.adx_period,
        )

        df[f"trend_plus_di_{self.config.adx_period}"] = plus_di
        df[f"trend_minus_di_{self.config.adx_period}"] = minus_di
        df[f"trend_adx_{self.config.adx_period}"] = adx
        df[f"trend_di_spread_{self.config.adx_period}"] = (
            plus_di - minus_di
        )

        for period in self.config.slope_periods:
            df[f"trend_slope_{period}"] = self._rolling_slope(
                close,
                period,
            )

            df[f"trend_normalized_slope_{period}"] = (
                df[f"trend_slope_{period}"] / safe_close
            )

        if 20 in self.config.ema_periods and 50 in self.config.ema_periods:
            df["trend_ema_20_50_spread"] = (
                df["trend_ema_20"]
                / df["trend_ema_50"].replace(0, np.nan)
                - 1.0
            )

        if 50 in self.config.sma_periods and 200 in self.config.sma_periods:
            df["trend_sma_50_200_spread"] = (
                df["trend_sma_50"]
                / df["trend_sma_200"].replace(0, np.nan)
                - 1.0
            )

            df["trend_golden_cross"] = (
                df["trend_sma_50"] > df["trend_sma_200"]
            ).astype("int8")

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    def build(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        return self.transform(dataframe)

    @staticmethod
    def _adx(
        *,
        high: pd.Series,
        low: pd.Series,
        close: pd.Series,
        period: int,
    ) -> tuple[pd.Series, pd.Series, pd.Series]:
        previous_close = close.shift(1)

        true_range = pd.concat(
            [
                high - low,
                (high - previous_close).abs(),
                (low - previous_close).abs(),
            ],
            axis=1,
        ).max(axis=1)

        up_move = high.diff()
        down_move = -low.diff()

        plus_dm = pd.Series(
            np.where(
                (up_move > down_move) & (up_move > 0),
                up_move,
                0.0,
            ),
            index=high.index,
            dtype="float64",
        )

        minus_dm = pd.Series(
            np.where(
                (down_move > up_move) & (down_move > 0),
                down_move,
                0.0,
            ),
            index=high.index,
            dtype="float64",
        )

        atr = true_range.ewm(
            alpha=1.0 / period,
            adjust=False,
            min_periods=period,
        ).mean()

        plus_di = (
            100.0
            * plus_dm.ewm(
                alpha=1.0 / period,
                adjust=False,
                min_periods=period,
            ).mean()
            / atr.replace(0, np.nan)
        )

        minus_di = (
            100.0
            * minus_dm.ewm(
                alpha=1.0 / period,
                adjust=False,
                min_periods=period,
            ).mean()
            / atr.replace(0, np.nan)
        )

        dx = (
            100.0
            * (plus_di - minus_di).abs()
            / (plus_di + minus_di).replace(0, np.nan)
        )

        adx = dx.ewm(
            alpha=1.0 / period,
            adjust=False,
            min_periods=period,
        ).mean()

        return plus_di, minus_di, adx

    @staticmethod
    def _rolling_slope(
        series: pd.Series,
        period: int,
    ) -> pd.Series:
        x = np.arange(period, dtype="float64")
        x_centered = x - x.mean()
        denominator = np.sum(x_centered ** 2)

        def slope(values: np.ndarray) -> float:
            if np.isnan(values).any():
                return np.nan

            y_centered = values - values.mean()

            return float(
                np.sum(x_centered * y_centered)
                / denominator
            )

        return series.rolling(
            window=period,
            min_periods=period,
        ).apply(
            slope,
            raw=True,
        )

    def _validate_config(self) -> None:
        period_groups = (
            self.config.sma_periods,
            self.config.ema_periods,
            self.config.slope_periods,
        )

        for periods in period_groups:
            if not periods:
                raise ValueError(
                    "Periodenlisten dürfen nicht leer sein."
                )

            if any(period < 2 for period in periods):
                raise ValueError(
                    "Alle Perioden müssen mindestens 2 sein."
                )

        if self.config.macd_fast >= self.config.macd_slow:
            raise ValueError(
                "macd_fast muss kleiner als macd_slow sein."
            )

        if self.config.macd_signal < 2:
            raise ValueError(
                "macd_signal muss mindestens 2 sein."
            )

        if self.config.adx_period < 2:
            raise ValueError(
                "adx_period muss mindestens 2 sein."
            )

    @classmethod
    def _validate_dataframe(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        if not isinstance(dataframe, pd.DataFrame):
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
                "TrendFeatureBuilder fehlen Spalten: "
                f"{missing}"
            )


trend_feature_builder = TrendFeatureBuilder()
