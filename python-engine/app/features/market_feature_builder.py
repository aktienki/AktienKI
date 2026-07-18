from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(slots=True)
class MarketFeatureConfig:
    return_periods: tuple[int, ...] = (1, 5, 20, 60)
    trend_windows: tuple[int, ...] = (20, 50, 200)
    volatility_windows: tuple[int, ...] = (20, 60)
    breadth_window: int = 20
    regime_window: int = 50


class MarketFeatureBuilder:
    """
    Erzeugt Markt- und Regime-Features aus bereits ausgerichteten
    Marktzeitreihen.

    Erwartete Eingabe:
    - DatetimeIndex
    - numerische Marktspalten, z. B.
      market_sp500_close
      market_nasdaq_close
      market_dax_close
      market_china_close
      market_nikkei_close
      market_vix_close
      market_us10y_close

    Die Klasse verändert bestehende Spalten nicht, sondern ergänzt
    daraus abgeleitete Features.
    """

    def __init__(
        self,
        config: MarketFeatureConfig | None = None,
    ) -> None:
        self.config = config or MarketFeatureConfig()
        self._validate_config()

    def transform(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        self._validate_dataframe(dataframe)

        df = dataframe.copy()

        market_columns = self._market_columns(df)

        if not market_columns:
            return df

        for column in market_columns:
            series = pd.to_numeric(
                df[column],
                errors="coerce",
            )

            safe_series = series.replace(
                0,
                np.nan,
            )

            base_name = self._feature_base_name(
                column
            )

            for period in self.config.return_periods:
                df[
                    f"{base_name}_return_{period}"
                ] = series.pct_change(
                    periods=period
                )

            for window in self.config.trend_windows:
                moving_average = series.rolling(
                    window=window,
                    min_periods=window,
                ).mean()

                df[
                    f"{base_name}_sma_{window}"
                ] = moving_average

                df[
                    f"{base_name}_distance_sma_{window}"
                ] = (
                    series
                    / moving_average.replace(
                        0,
                        np.nan,
                    )
                    - 1.0
                )

            log_return = np.log(
                safe_series
                / safe_series.shift(1)
            )

            for window in self.config.volatility_windows:
                df[
                    f"{base_name}_volatility_{window}"
                ] = log_return.rolling(
                    window=window,
                    min_periods=window,
                ).std()

            df[
                f"{base_name}_positive_day"
            ] = (
                series.pct_change() > 0
            ).astype("int8")

        return_columns = [
            column
            for column in df.columns
            if column.startswith("market_")
            and "_return_1" in column
        ]

        if return_columns:
            return_frame = df[return_columns]

            df["market_breadth_positive_ratio"] = (
                return_frame.gt(0)
                .mean(axis=1)
            )

            df["market_breadth_negative_ratio"] = (
                return_frame.lt(0)
                .mean(axis=1)
            )

            df["market_average_return_1"] = (
                return_frame.mean(axis=1)
            )

            df["market_dispersion_1"] = (
                return_frame.std(axis=1)
            )

            df["market_breadth_score"] = (
                df["market_breadth_positive_ratio"]
                - df["market_breadth_negative_ratio"]
            )

            df["market_breadth_trend"] = (
                df["market_breadth_score"]
                .rolling(
                    window=self.config.breadth_window,
                    min_periods=self.config.breadth_window,
                )
                .mean()
            )

        benchmark = self._first_existing(
            df,
            (
                "market_sp500_close",
                "market_nasdaq_close",
                "market_dax_close",
                "market_nikkei_close",
                "market_china_close",
            ),
        )

        volatility_index = self._first_existing(
            df,
            (
                "market_vix_close",
                "market_volatility_close",
            ),
        )

        if benchmark is not None:
            benchmark_series = pd.to_numeric(
                df[benchmark],
                errors="coerce",
            )

            benchmark_sma = benchmark_series.rolling(
                window=self.config.regime_window,
                min_periods=self.config.regime_window,
            ).mean()

            benchmark_return = benchmark_series.pct_change(
                periods=20
            )

            df["market_regime_trend"] = (
                benchmark_series
                / benchmark_sma.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

            df["market_regime_momentum"] = (
                benchmark_return
            )

        if volatility_index is not None:
            vix_series = pd.to_numeric(
                df[volatility_index],
                errors="coerce",
            )

            vix_mean = vix_series.rolling(
                window=self.config.regime_window,
                min_periods=self.config.regime_window,
            ).mean()

            vix_std = vix_series.rolling(
                window=self.config.regime_window,
                min_periods=self.config.regime_window,
            ).std()

            df["market_vix_zscore"] = (
                vix_series - vix_mean
            ) / vix_std.replace(
                0,
                np.nan,
            )

        df["market_risk_on_score"] = self._risk_on_score(
            df
        )

        df["market_risk_off_score"] = (
            -df["market_risk_on_score"]
        )

        df["market_regime_code"] = np.select(
            [
                df["market_risk_on_score"] >= 1.0,
                df["market_risk_on_score"] <= -1.0,
            ],
            [
                1,
                -1,
            ],
            default=0,
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
    def _market_columns(
        dataframe: pd.DataFrame,
    ) -> list[str]:
        return [
            str(column)
            for column in dataframe.columns
            if str(column).startswith("market_")
            and pd.api.types.is_numeric_dtype(
                dataframe[column]
            )
            and not str(column).startswith(
                (
                    "market_breadth_",
                    "market_regime_",
                    "market_risk_",
                )
            )
        ]

    @staticmethod
    def _feature_base_name(
        column: str,
    ) -> str:
        if column.endswith("_close"):
            return column[:-6]

        return column

    @staticmethod
    def _first_existing(
        dataframe: pd.DataFrame,
        candidates: tuple[str, ...],
    ) -> str | None:
        return next(
            (
                column
                for column in candidates
                if column in dataframe.columns
            ),
            None,
        )

    @staticmethod
    def _risk_on_score(
        dataframe: pd.DataFrame,
    ) -> pd.Series:
        score = pd.Series(
            0.0,
            index=dataframe.index,
            dtype="float64",
        )

        if "market_regime_trend" in dataframe.columns:
            score = score + np.sign(
                dataframe["market_regime_trend"]
            )

        if "market_regime_momentum" in dataframe.columns:
            score = score + np.sign(
                dataframe["market_regime_momentum"]
            )

        if "market_breadth_score" in dataframe.columns:
            score = score + np.sign(
                dataframe["market_breadth_score"]
            )

        if "market_vix_zscore" in dataframe.columns:
            score = score - np.sign(
                dataframe["market_vix_zscore"]
            )

        return score

    def _validate_config(self) -> None:
        period_groups = (
            self.config.return_periods,
            self.config.trend_windows,
            self.config.volatility_windows,
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

        if self.config.breadth_window < 2:
            raise ValueError(
                "breadth_window muss mindestens 2 sein."
            )

        if self.config.regime_window < 2:
            raise ValueError(
                "regime_window muss mindestens 2 sein."
            )

    @staticmethod
    def _validate_dataframe(
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


market_feature_builder = MarketFeatureBuilder()
