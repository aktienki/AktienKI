from __future__ import annotations

import numpy as np
import pandas as pd


class PriceFeatureBuilder:
    """
    Erzeugt ausschließlich preisbasierte Features.

    Erwartete Eingabespalten:
    - Open
    - High
    - Low
    - Close

    Optional:
    - Adj Close
    - Volume
    """

    REQUIRED_COLUMNS = {
        "Open",
        "High",
        "Low",
        "Close",
    }

    RETURN_HORIZONS = (
        1,
        5,
        20,
        60,
    )

    ROLLING_WINDOWS = (
        5,
        10,
        20,
        50,
        100,
        200,
    )

    @classmethod
    def transform(
        cls,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        cls._validate(dataframe)

        df = dataframe.copy()

        open_price = pd.to_numeric(
            df["Open"],
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

        df["price_return_1"] = close.pct_change()
        df["price_log_return_1"] = np.log(
            safe_close
            / previous_close
        )

        for horizon in cls.RETURN_HORIZONS:
            df[f"price_return_{horizon}"] = (
                close.pct_change(
                    periods=horizon
                )
            )

            df[f"price_log_return_{horizon}"] = np.log(
                safe_close
                / close.shift(horizon).replace(
                    0,
                    np.nan,
                )
            )

        df["price_gap_open"] = (
            open_price
            / previous_close
            - 1.0
        )

        df["price_intraday_return"] = (
            close
            / open_price.replace(
                0,
                np.nan,
            )
            - 1.0
        )

        df["price_high_low_range"] = (
            high
            / low.replace(
                0,
                np.nan,
            )
            - 1.0
        )

        df["price_close_location"] = (
            close - low
        ) / (
            high - low
        ).replace(
            0,
            np.nan,
        )

        df["price_true_range"] = pd.concat(
            [
                high - low,
                (high - previous_close).abs(),
                (low - previous_close).abs(),
            ],
            axis=1,
        ).max(
            axis=1
        )

        df["price_true_range_pct"] = (
            df["price_true_range"]
            / previous_close
        )

        for window in cls.ROLLING_WINDOWS:
            rolling_close = close.rolling(
                window=window,
                min_periods=window,
            )

            rolling_mean = rolling_close.mean()
            rolling_std = rolling_close.std()
            rolling_high = high.rolling(
                window=window,
                min_periods=window,
            ).max()
            rolling_low = low.rolling(
                window=window,
                min_periods=window,
            ).min()

            df[f"price_sma_{window}"] = rolling_mean

            df[f"price_distance_sma_{window}"] = (
                close
                / rolling_mean.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

            df[f"price_zscore_{window}"] = (
                close - rolling_mean
            ) / rolling_std.replace(
                0,
                np.nan,
            )

            df[f"price_rolling_high_{window}"] = (
                rolling_high
            )

            df[f"price_rolling_low_{window}"] = (
                rolling_low
            )

            df[f"price_distance_high_{window}"] = (
                close
                / rolling_high.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

            df[f"price_distance_low_{window}"] = (
                close
                / rolling_low.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

            df[f"price_volatility_{window}"] = (
                df["price_log_return_1"]
                .rolling(
                    window=window,
                    min_periods=window,
                )
                .std()
                * np.sqrt(window)
            )

            df[f"price_atr_base_{window}"] = (
                df["price_true_range"]
                .rolling(
                    window=window,
                    min_periods=window,
                )
                .mean()
            )

            df[f"price_atr_base_pct_{window}"] = (
                df[f"price_atr_base_{window}"]
                / safe_close
            )

            rolling_max_close = close.rolling(
                window=window,
                min_periods=window,
            ).max()

            df[f"price_drawdown_{window}"] = (
                close
                / rolling_max_close.replace(
                    0,
                    np.nan,
                )
                - 1.0
            )

        if "Adj Close" in df.columns:
            adjusted_close = pd.to_numeric(
                df["Adj Close"],
                errors="coerce",
            )

            df["price_adjustment_factor"] = (
                adjusted_close
                / safe_close
            )

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @classmethod
    def build(
        cls,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        """
        Kompatibler Alias.
        """

        return cls.transform(
            dataframe
        )

    @classmethod
    def _validate(
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
                "PriceFeatureBuilder fehlen Spalten: "
                f"{missing}"
            )


price_feature_builder = PriceFeatureBuilder()
