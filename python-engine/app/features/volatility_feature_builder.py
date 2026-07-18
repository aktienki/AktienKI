from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(slots=True)
class VolatilityFeatureConfig:
    atr_periods: tuple[int, ...] = (7, 14, 21)
    bollinger_periods: tuple[int, ...] = (20, 50)
    bollinger_std: float = 2.0
    volatility_windows: tuple[int, ...] = (5, 10, 20, 60)
    parkinson_windows: tuple[int, ...] = (10, 20)
    drawdown_windows: tuple[int, ...] = (20, 60, 252)


class VolatilityFeatureBuilder:
    """
    Erzeugt konfigurierbare Volatilitäts- und Risikofeatures.

    Erwartete Spalten:
    - High
    - Low
    - Close
    """

    REQUIRED_COLUMNS = {
        "High",
        "Low",
        "Close",
    }

    def __init__(
        self,
        config: VolatilityFeatureConfig | None = None,
    ) -> None:
        self.config = config or VolatilityFeatureConfig()
        self._validate_config()

    def transform(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        self._validate_dataframe(dataframe)

        df = dataframe.copy()

        high = pd.to_numeric(
            df["High"],
            errors="coerce",
        )
        low = pd.to_numeric(
            df["Low"],
            errors="coerce",
        )
        close = pd.to_numeric(
            df["Close"],
            errors="coerce",
        )

        safe_close = close.replace(
            0,
            np.nan,
        )
        previous_close = close.shift(1).replace(
            0,
            np.nan,
        )

        true_range = pd.concat(
            [
                high - low,
                (high - previous_close).abs(),
                (low - previous_close).abs(),
            ],
            axis=1,
        ).max(axis=1)

        log_return = np.log(
            safe_close
            / previous_close
        )

        df["volatility_true_range"] = true_range
        df["volatility_true_range_pct"] = (
            true_range / previous_close
        )
        df["volatility_log_return"] = log_return
        df["volatility_abs_return"] = (
            close.pct_change().abs()
        )

        for period in self.config.atr_periods:
            atr = true_range.ewm(
                alpha=1.0 / period,
                adjust=False,
                min_periods=period,
            ).mean()

            df[f"volatility_atr_{period}"] = atr
            df[f"volatility_atr_pct_{period}"] = (
                atr / safe_close
            )

        for period in self.config.bollinger_periods:
            middle = close.rolling(
                window=period,
                min_periods=period,
            ).mean()

            deviation = close.rolling(
                window=period,
                min_periods=period,
            ).std()

            upper = (
                middle
                + self.config.bollinger_std
                * deviation
            )

            lower = (
                middle
                - self.config.bollinger_std
                * deviation
            )

            bandwidth = (
                upper - lower
            ) / middle.replace(
                0,
                np.nan,
            )

            percent_b = (
                close - lower
            ) / (
                upper - lower
            ).replace(
                0,
                np.nan,
            )

            df[f"volatility_bollinger_mid_{period}"] = middle
            df[f"volatility_bollinger_upper_{period}"] = upper
            df[f"volatility_bollinger_lower_{period}"] = lower
            df[f"volatility_bollinger_bandwidth_{period}"] = bandwidth
            df[f"volatility_bollinger_percent_b_{period}"] = percent_b

        for window in self.config.volatility_windows:
            rolling_std = log_return.rolling(
                window=window,
                min_periods=window,
            ).std()

            df[f"volatility_realized_{window}"] = (
                rolling_std
                * np.sqrt(window)
            )

            df[f"volatility_realized_annualized_{window}"] = (
                rolling_std
                * np.sqrt(252.0)
            )

            df[f"volatility_downside_{window}"] = (
                log_return.where(
                    log_return < 0,
                    0.0,
                )
                .rolling(
                    window=window,
                    min_periods=window,
                )
                .std()
                * np.sqrt(window)
            )

            df[f"volatility_upside_{window}"] = (
                log_return.where(
                    log_return > 0,
                    0.0,
                )
                .rolling(
                    window=window,
                    min_periods=window,
                )
                .std()
                * np.sqrt(window)
            )

            rolling_mean = log_return.rolling(
                window=window,
                min_periods=window,
            ).mean()

            df[f"volatility_return_to_risk_{window}"] = (
                rolling_mean
                / rolling_std.replace(
                    0,
                    np.nan,
                )
            )

        log_high_low = np.log(
            high.replace(0, np.nan)
            / low.replace(0, np.nan)
        )

        for window in self.config.parkinson_windows:
            parkinson = np.sqrt(
                (
                    log_high_low.pow(2)
                    .rolling(
                        window=window,
                        min_periods=window,
                    )
                    .mean()
                )
                / (
                    4.0
                    * np.log(2.0)
                )
            )

            df[f"volatility_parkinson_{window}"] = parkinson
            df[
                f"volatility_parkinson_annualized_{window}"
            ] = (
                parkinson
                * np.sqrt(252.0)
            )

        for window in self.config.drawdown_windows:
            rolling_peak = close.rolling(
                window=window,
                min_periods=window,
            ).max()

            drawdown = (
                close
                / rolling_peak.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

            df[f"volatility_drawdown_{window}"] = drawdown

            df[f"volatility_max_drawdown_{window}"] = (
                drawdown.rolling(
                    window=window,
                    min_periods=window,
                ).min()
            )

        rolling_volatility_20 = log_return.rolling(
            window=20,
            min_periods=20,
        ).std()

        rolling_volatility_60 = log_return.rolling(
            window=60,
            min_periods=60,
        ).std()

        df["volatility_regime_ratio_20_60"] = (
            rolling_volatility_20
            / rolling_volatility_60.replace(
                0,
                np.nan,
            )
        )

        df["volatility_expansion"] = (
            rolling_volatility_20
            / rolling_volatility_20.shift(5).replace(
                0,
                np.nan,
            )
            - 1.0
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
            self.config.atr_periods,
            self.config.bollinger_periods,
            self.config.volatility_windows,
            self.config.parkinson_windows,
            self.config.drawdown_windows,
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

        if self.config.bollinger_std <= 0:
            raise ValueError(
                "bollinger_std muss größer als 0 sein."
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
                "VolatilityFeatureBuilder fehlen Spalten: "
                f"{missing}"
            )


volatility_feature_builder = VolatilityFeatureBuilder()
