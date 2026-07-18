from __future__ import annotations

from dataclasses import dataclass

import numpy as np
import pandas as pd


@dataclass(slots=True)
class VolumeFeatureConfig:
    rolling_windows: tuple[int, ...] = (5, 10, 20, 50)
    cmf_period: int = 20
    force_index_period: int = 13
    eom_period: int = 14
    volume_roc_periods: tuple[int, ...] = (1, 5, 10, 20)
    vwap_window: int = 20
    breakout_window: int = 20


class VolumeFeatureBuilder:
    """
    Erzeugt konfigurierbare volumenbasierte Features.

    Erwartete Spalten:
    - High
    - Low
    - Close
    - Volume
    """

    REQUIRED_COLUMNS = {
        "High",
        "Low",
        "Close",
        "Volume",
    }

    def __init__(
        self,
        config: VolumeFeatureConfig | None = None,
    ) -> None:
        self.config = config or VolumeFeatureConfig()
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
        volume = pd.to_numeric(df["Volume"], errors="coerce")

        safe_close = close.replace(0, np.nan)
        safe_volume = volume.replace(0, np.nan)

        price_change = close.diff()
        return_1 = close.pct_change()

        df["volume_obv"] = (
            np.sign(price_change)
            .fillna(0.0)
            .mul(volume.fillna(0.0))
            .cumsum()
        )

        money_flow_multiplier = (
            (close - low) - (high - close)
        ) / (
            high - low
        ).replace(0, np.nan)

        money_flow_volume = (
            money_flow_multiplier
            * volume
        )

        df["volume_accumulation_distribution"] = (
            money_flow_volume
            .fillna(0.0)
            .cumsum()
        )

        cmf_sum = money_flow_volume.rolling(
            window=self.config.cmf_period,
            min_periods=self.config.cmf_period,
        ).sum()

        volume_sum = volume.rolling(
            window=self.config.cmf_period,
            min_periods=self.config.cmf_period,
        ).sum()

        df[f"volume_cmf_{self.config.cmf_period}"] = (
            cmf_sum
            / volume_sum.replace(0, np.nan)
        )

        force_index = (
            price_change
            * volume
        )

        df["volume_force_index_1"] = force_index

        df[
            f"volume_force_index_{self.config.force_index_period}"
        ] = force_index.ewm(
            span=self.config.force_index_period,
            adjust=False,
            min_periods=self.config.force_index_period,
        ).mean()

        midpoint_move = (
            (
                high + low
            ) / 2.0
        ).diff()

        box_ratio = (
            volume
            / (
                high - low
            ).replace(0, np.nan)
        )

        ease_of_movement = (
            midpoint_move
            / box_ratio.replace(0, np.nan)
        )

        df["volume_eom_raw"] = ease_of_movement

        df[
            f"volume_eom_{self.config.eom_period}"
        ] = ease_of_movement.rolling(
            window=self.config.eom_period,
            min_periods=self.config.eom_period,
        ).mean()

        for period in self.config.volume_roc_periods:
            df[f"volume_roc_{period}"] = (
                volume.pct_change(
                    periods=period
                )
            )

        typical_price = (
            high + low + close
        ) / 3.0

        rolling_price_volume = (
            typical_price
            * volume
        ).rolling(
            window=self.config.vwap_window,
            min_periods=self.config.vwap_window,
        ).sum()

        rolling_volume = volume.rolling(
            window=self.config.vwap_window,
            min_periods=self.config.vwap_window,
        ).sum()

        rolling_vwap = (
            rolling_price_volume
            / rolling_volume.replace(0, np.nan)
        )

        df[f"volume_vwap_{self.config.vwap_window}"] = (
            rolling_vwap
        )

        df[
            f"volume_distance_vwap_{self.config.vwap_window}"
        ] = (
            close
            / rolling_vwap.replace(0, np.nan)
            - 1.0
        )

        for window in self.config.rolling_windows:
            rolling_mean = volume.rolling(
                window=window,
                min_periods=window,
            ).mean()

            rolling_std = volume.rolling(
                window=window,
                min_periods=window,
            ).std()

            df[f"volume_mean_{window}"] = rolling_mean
            df[f"volume_std_{window}"] = rolling_std

            df[f"volume_relative_{window}"] = (
                volume
                / rolling_mean.replace(0, np.nan)
            )

            df[f"volume_zscore_{window}"] = (
                volume - rolling_mean
            ) / rolling_std.replace(0, np.nan)

            df[f"volume_price_momentum_{window}"] = (
                return_1.rolling(
                    window=window,
                    min_periods=window,
                ).sum()
                * df[f"volume_relative_{window}"]
            )

            df[f"volume_money_flow_ratio_{window}"] = (
                money_flow_volume.rolling(
                    window=window,
                    min_periods=window,
                ).sum()
                / volume.rolling(
                    window=window,
                    min_periods=window,
                ).sum().replace(0, np.nan)
            )

        breakout_reference = volume.rolling(
            window=self.config.breakout_window,
            min_periods=self.config.breakout_window,
        ).max()

        df[
            f"volume_breakout_{self.config.breakout_window}"
        ] = (
            volume
            >= breakout_reference.shift(1)
        ).astype("int8")

        df["volume_price_confirmation"] = (
            np.sign(return_1)
            * np.sign(volume.pct_change())
        )

        df["volume_dollar_volume"] = (
            safe_close
            * safe_volume
        )

        df["volume_dollar_volume_log"] = np.log1p(
            df["volume_dollar_volume"]
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
        if not self.config.rolling_windows:
            raise ValueError(
                "rolling_windows darf nicht leer sein."
            )

        if any(
            window < 2
            for window in self.config.rolling_windows
        ):
            raise ValueError(
                "Alle rolling_windows müssen mindestens 2 sein."
            )

        if not self.config.volume_roc_periods:
            raise ValueError(
                "volume_roc_periods darf nicht leer sein."
            )

        if any(
            period < 1
            for period in self.config.volume_roc_periods
        ):
            raise ValueError(
                "Alle volume_roc_periods müssen mindestens 1 sein."
            )

        scalar_periods = (
            self.config.cmf_period,
            self.config.force_index_period,
            self.config.eom_period,
            self.config.vwap_window,
            self.config.breakout_window,
        )

        if any(period < 2 for period in scalar_periods):
            raise ValueError(
                "Alle Einzelperioden müssen mindestens 2 sein."
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
                "VolumeFeatureBuilder fehlen Spalten: "
                f"{missing}"
            )


volume_feature_builder = VolumeFeatureBuilder()
