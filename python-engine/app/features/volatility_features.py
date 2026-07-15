from __future__ import annotations

import numpy as np
import pandas as pd


class VolatilityFeatures:
    """
    Volatilitätsbasierte Features für AKI-PULSE und AKI-HORIZON.
    """

    ATR_PERIODS = [7, 14, 21]
    VOLATILITY_WINDOWS = [5, 10, 20, 50]
    BOLLINGER_WINDOWS = [10, 20, 50]

    @classmethod
    def transform(cls, dataframe: pd.DataFrame) -> pd.DataFrame:
        df = dataframe.copy()

        cls._validate_columns(df)

        high = df["High"].astype(float)
        low = df["Low"].astype(float)
        close = df["Close"].astype(float)

        previous_close = close.shift(1)

        true_range = pd.concat(
            [
                high - low,
                (high - previous_close).abs(),
                (low - previous_close).abs(),
            ],
            axis=1,
        ).max(axis=1)

        df["true_range"] = true_range

        for period in cls.ATR_PERIODS:
            df[f"atr_{period}"] = (
                true_range
                .ewm(
                    alpha=1 / period,
                    adjust=False,
                    min_periods=period,
                )
                .mean()
            )

            df[f"atr_{period}_normalized"] = (
                df[f"atr_{period}"]
                / close.replace(
                    0,
                    np.nan,
                )
            )

            df[f"atr_{period}_slope"] = (
                df[f"atr_{period}"]
                .diff()
            )

        returns = close.pct_change()

        for window in cls.VOLATILITY_WINDOWS:
            df[f"realized_volatility_{window}"] = (
                returns
                .rolling(window)
                .std()
            )

            df[f"annualized_volatility_{window}"] = (
                df[f"realized_volatility_{window}"]
                * np.sqrt(252)
            )

            df[f"volatility_zscore_{window}"] = (
                df[f"realized_volatility_{window}"]
                - df[f"realized_volatility_{window}"]
                .rolling(50)
                .mean()
            ) / df[f"realized_volatility_{window}"]                 .rolling(50)                 .std()                 .replace(
                    0,
                    np.nan,
                )

        for window in cls.BOLLINGER_WINDOWS:
            middle = close.rolling(window).mean()
            deviation = close.rolling(window).std()

            upper = middle + 2 * deviation
            lower = middle - 2 * deviation

            df[f"bollinger_middle_{window}"] = middle
            df[f"bollinger_upper_{window}"] = upper
            df[f"bollinger_lower_{window}"] = lower

            df[f"bollinger_width_{window}"] = (
                upper
                - lower
            ) / middle.replace(
                0,
                np.nan,
            )

            df[f"bollinger_percent_b_{window}"] = (
                close
                - lower
            ) / (
                upper
                - lower
            ).replace(
                0,
                np.nan,
            )

            df[f"bollinger_upper_distance_{window}"] = (
                close
                / upper
                - 1
            )

            df[f"bollinger_lower_distance_{window}"] = (
                close
                / lower
                - 1
            )

        for window in [10, 20, 55]:
            highest_high = high.rolling(window).max()
            lowest_low = low.rolling(window).min()

            df[f"donchian_high_{window}"] = highest_high
            df[f"donchian_low_{window}"] = lowest_low

            df[f"donchian_width_{window}"] = (
                highest_high
                - lowest_low
            ) / close.replace(
                0,
                np.nan,
            )

            df[f"donchian_position_{window}"] = (
                close
                - lowest_low
            ) / (
                highest_high
                - lowest_low
            ).replace(
                0,
                np.nan,
            )

        ema_20 = close.ewm(
            span=20,
            adjust=False,
        ).mean()

        atr_10 = true_range.ewm(
            alpha=1 / 10,
            adjust=False,
            min_periods=10,
        ).mean()

        df["keltner_middle_20"] = ema_20
        df["keltner_upper_20"] = (
            ema_20
            + 2 * atr_10
        )
        df["keltner_lower_20"] = (
            ema_20
            - 2 * atr_10
        )

        df["keltner_width_20"] = (
            df["keltner_upper_20"]
            - df["keltner_lower_20"]
        ) / ema_20.replace(
            0,
            np.nan,
        )

        df["keltner_position_20"] = (
            close
            - df["keltner_lower_20"]
        ) / (
            df["keltner_upper_20"]
            - df["keltner_lower_20"]
        ).replace(
            0,
            np.nan,
        )

        log_high_low = np.log(
            high
            / low.replace(
                0,
                np.nan,
            )
        )

        df["parkinson_volatility_20"] = np.sqrt(
            (
                log_high_low.pow(2)
                .rolling(20)
                .mean()
            )
            / (
                4
                * np.log(2)
            )
        )

        log_open_close = np.log(
            df["Open"].astype(float)
            / close.replace(
                0,
                np.nan,
            )
        )

        df["garman_klass_volatility_20"] = np.sqrt(
            (
                0.5
                * log_high_low.pow(2)
                - (
                    2
                    * np.log(2)
                    - 1
                )
                * log_open_close.pow(2)
            )
            .rolling(20)
            .mean()
            .clip(lower=0)
        )

        df["range_percent"] = (
            high
            - low
        ) / close.replace(
            0,
            np.nan,
        )

        df["gap_volatility"] = (
            df["Open"].astype(float)
            / previous_close
            - 1
        ).abs()

        df["volatility_regime"] = pd.cut(
            df["realized_volatility_20"],
            bins=[
                -np.inf,
                df["realized_volatility_20"].quantile(0.33),
                df["realized_volatility_20"].quantile(0.66),
                np.inf,
            ],
            labels=[
                0,
                1,
                2,
            ],
            include_lowest=True,
        ).astype(float)

        df["volatility_expansion"] = (
            df["realized_volatility_20"]
            > df["realized_volatility_20"]
            .rolling(50)
            .mean()
        ).astype(int)

        df["bollinger_squeeze"] = (
            df["bollinger_width_20"]
            < df["bollinger_width_20"]
            .rolling(50)
            .quantile(0.20)
        ).astype(int)

        df["volatility_composite"] = (
            cls._zscore(
                df["atr_14_normalized"]
            )
            + cls._zscore(
                df["realized_volatility_20"]
            )
            + cls._zscore(
                df["bollinger_width_20"]
            )
            + cls._zscore(
                df["donchian_width_20"]
            )
        ) / 4

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @staticmethod
    def _validate_columns(
        dataframe: pd.DataFrame,
    ) -> None:
        required = {
            "Open",
            "High",
            "Low",
            "Close",
        }

        missing = sorted(
            required.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "VolatilityFeatures fehlen Spalten: "
                f"{missing}"
            )

    @staticmethod
    def _zscore(
        series: pd.Series,
        window: int = 50,
    ) -> pd.Series:
        rolling_mean = (
            series
            .rolling(window)
            .mean()
        )

        rolling_std = (
            series
            .rolling(window)
            .std()
        )

        return (
            series
            - rolling_mean
        ) / rolling_std.replace(
            0,
            np.nan,
        )
