from __future__ import annotations

import numpy as np
import pandas as pd


class VolumeFeatures:
    """
    Volumenbasierte Features für AKI-PULSE und AKI-HORIZON.
    """

    VOLUME_WINDOWS = [5, 10, 20, 50]

    @classmethod
    def transform(cls, dataframe: pd.DataFrame) -> pd.DataFrame:
        df = dataframe.copy()

        cls._validate_columns(df)

        high = df["High"].astype(float)
        low = df["Low"].astype(float)
        close = df["Close"].astype(float)
        volume = df["Volume"].astype(float)

        typical_price = (
            high
            + low
            + close
        ) / 3

        #
        # Rolling Volume
        #

        for window in cls.VOLUME_WINDOWS:
            mean_column = f"volume_mean_{window}"
            std_column = f"volume_std_{window}"

            df[mean_column] = (
                volume
                .rolling(window)
                .mean()
            )

            df[std_column] = (
                volume
                .rolling(window)
                .std()
            )

            df[f"volume_ratio_{window}"] = (
                volume
                / df[mean_column].replace(
                    0,
                    np.nan,
                )
            )

            df[f"volume_zscore_{window}"] = (
                volume
                - df[mean_column]
            ) / df[std_column].replace(
                0,
                np.nan,
            )

        #
        # Relative Volume / Spikes
        #

        df["relative_volume"] = (
            volume
            / volume.rolling(20).mean().replace(
                0,
                np.nan,
            )
        )

        df["volume_spike_150"] = (
            df["relative_volume"] >= 1.5
        ).astype(int)

        df["volume_spike_200"] = (
            df["relative_volume"] >= 2.0
        ).astype(int)

        df["volume_change_1"] = (
            volume.pct_change(1)
        )

        df["volume_change_5"] = (
            volume.pct_change(5)
        )

        #
        # On-Balance Volume
        #

        direction = np.sign(
            close.diff()
        ).fillna(0)

        df["obv"] = (
            direction
            * volume
        ).cumsum()

        df["obv_change_5"] = (
            df["obv"].pct_change(5)
        )

        df["obv_change_20"] = (
            df["obv"].pct_change(20)
        )

        df["obv_slope_10"] = (
            df["obv"].diff(10)
            / 10
        )

        #
        # Accumulation / Distribution
        #

        price_range = (
            high
            - low
        ).replace(
            0,
            np.nan,
        )

        money_flow_multiplier = (
            (
                close
                - low
            )
            - (
                high
                - close
            )
        ) / price_range

        money_flow_volume = (
            money_flow_multiplier
            * volume
        )

        df["ad_line"] = (
            money_flow_volume
            .fillna(0)
            .cumsum()
        )

        df["ad_line_change_10"] = (
            df["ad_line"]
            .pct_change(10)
        )

        #
        # Chaikin Money Flow
        #

        for window in [10, 20]:
            df[f"cmf_{window}"] = (
                money_flow_volume
                .rolling(window)
                .sum()
                / volume
                .rolling(window)
                .sum()
                .replace(
                    0,
                    np.nan,
                )
            )

        #
        # Money Flow Index
        #

        raw_money_flow = (
            typical_price
            * volume
        )

        positive_flow = raw_money_flow.where(
            typical_price.diff() > 0,
            0.0,
        )

        negative_flow = raw_money_flow.where(
            typical_price.diff() < 0,
            0.0,
        ).abs()

        positive_sum = (
            positive_flow
            .rolling(14)
            .sum()
        )

        negative_sum = (
            negative_flow
            .rolling(14)
            .sum()
        )

        money_flow_ratio = (
            positive_sum
            / negative_sum.replace(
                0,
                np.nan,
            )
        )

        df["mfi_14"] = (
            100
            - (
                100
                / (
                    1
                    + money_flow_ratio
                )
            )
        ).fillna(50)

        df["mfi_14_oversold"] = (
            df["mfi_14"] < 20
        ).astype(int)

        df["mfi_14_overbought"] = (
            df["mfi_14"] > 80
        ).astype(int)

        #
        # VWAP
        #

        cumulative_volume = (
            volume
            .replace(
                0,
                np.nan,
            )
            .cumsum()
        )

        df["vwap_cumulative"] = (
            (
                typical_price
                * volume
            )
            .cumsum()
            / cumulative_volume
        )

        for window in [12, 24, 50]:
            rolling_volume = (
                volume
                .rolling(window)
                .sum()
                .replace(
                    0,
                    np.nan,
                )
            )

            df[f"vwap_{window}"] = (
                (
                    typical_price
                    * volume
                )
                .rolling(window)
                .sum()
                / rolling_volume
            )

            df[f"price_vwap_distance_{window}"] = (
                close
                / df[f"vwap_{window}"]
                - 1
            )

        #
        # Price / Volume Confirmation
        #

        price_return = (
            close
            .pct_change()
        )

        df["price_volume_confirmation"] = (
            np.sign(
                price_return
            )
            * np.log1p(
                volume
            )
        )

        df["price_up_volume_up"] = (
            (
                price_return > 0
            )
            & (
                volume.pct_change() > 0
            )
        ).astype(int)

        df["price_down_volume_up"] = (
            (
                price_return < 0
            )
            & (
                volume.pct_change() > 0
            )
        ).astype(int)

        #
        # Ease of Movement
        #

        midpoint_move = (
            (
                high
                + low
            ) / 2
        ).diff()

        box_ratio = (
            volume
            / 100_000_000
        ) / price_range

        df["ease_of_movement"] = (
            midpoint_move
            / box_ratio.replace(
                0,
                np.nan,
            )
        )

        df["ease_of_movement_14"] = (
            df["ease_of_movement"]
            .rolling(14)
            .mean()
        )

        #
        # Force Index
        #

        df["force_index_1"] = (
            close.diff()
            * volume
        )

        df["force_index_13"] = (
            df["force_index_1"]
            .ewm(
                span=13,
                adjust=False,
            )
            .mean()
        )

        #
        # Composite Volume Score
        #

        df["volume_composite"] = (
            cls._zscore(
                df["relative_volume"]
            )
            + cls._zscore(
                df["cmf_20"]
            )
            + cls._zscore(
                df["mfi_14"]
            )
            + cls._zscore(
                df["obv_slope_10"]
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
            "High",
            "Low",
            "Close",
            "Volume",
        }

        missing = sorted(
            required.difference(
                dataframe.columns
            )
        )

        if missing:
            raise ValueError(
                "VolumeFeatures fehlen Spalten: "
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
