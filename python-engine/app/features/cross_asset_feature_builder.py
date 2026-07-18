from __future__ import annotations

from dataclasses import dataclass, field
from typing import Iterable

import numpy as np
import pandas as pd


@dataclass(slots=True)
class CrossAssetFeatureConfig:
    """
    Konfiguration für Cross-Asset-Features.

    Die Daten müssen bereits zeitlich auf die Aktien-Zeitreihe ausgerichtet
    sein. Erwartet werden numerische Spalten mit stabilen Namen.
    """

    enabled_groups: tuple[str, ...] = (
        "equity",
        "volatility",
        "rates",
        "fx",
        "commodities",
    )

    return_periods: tuple[int, ...] = (
        1,
        5,
        20,
        60,
    )

    volatility_windows: tuple[int, ...] = (
        20,
        60,
    )

    correlation_windows: tuple[int, ...] = (
        20,
        60,
    )

    beta_windows: tuple[int, ...] = (
        60,
    )

    relative_strength_periods: tuple[int, ...] = (
        20,
        60,
    )

    primary_benchmark: str = "cross_sp500_close"

    groups: dict[str, tuple[str, ...]] = field(
        default_factory=lambda: {
            "equity": (
                "cross_sp500_close",
                "cross_nasdaq_close",
                "cross_dax_close",
                "cross_nikkei_close",
                "cross_shanghai_close",
            ),
            "volatility": (
                "cross_vix_close",
                "cross_vvix_close",
            ),
            "rates": (
                "cross_us2y_close",
                "cross_us10y_close",
            ),
            "fx": (
                "cross_eurusd_close",
                "cross_usdjpy_close",
                "cross_gbpusd_close",
                "cross_dxy_close",
            ),
            "commodities": (
                "cross_gold_close",
                "cross_silver_close",
                "cross_oil_close",
                "cross_natural_gas_close",
                "cross_copper_close",
            ),
        }
    )


class CrossAssetFeatureBuilder:
    """
    Erzeugt abgeleitete Cross-Asset-Features.

    Unterstützt:
    - Returns
    - Volatilität
    - Relative Stärke
    - Rolling Correlation
    - Rolling Beta
    - Zins-Spreads
    - Risk-On/Risk-Off-Regime
    - Diversifikations- und Divergenz-Signale

    Es werden nur Spalten verarbeitet, die im DataFrame tatsächlich existieren.
    """

    def __init__(
        self,
        config: CrossAssetFeatureConfig | None = None,
    ) -> None:
        self.config = config or CrossAssetFeatureConfig()
        self._validate_config()

    def transform(
        self,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        self._validate_dataframe(dataframe)

        df = dataframe.copy()

        available = self._available_columns(df)

        if not available:
            return df

        prepared: dict[str, pd.Series] = {
            column: pd.to_numeric(
                df[column],
                errors="coerce",
            )
            for column in available
        }

        for column, series in prepared.items():
            base = self._base_name(column)
            safe_series = series.replace(0, np.nan)

            for period in self.config.return_periods:
                df[f"{base}_return_{period}"] = (
                    series.pct_change(periods=period)
                )

            log_return = np.log(
                safe_series
                / safe_series.shift(1)
            )

            for window in self.config.volatility_windows:
                df[f"{base}_volatility_{window}"] = (
                    log_return
                    .rolling(
                        window=window,
                        min_periods=window,
                    )
                    .std()
                )

        benchmark_column = (
            self.config.primary_benchmark
            if self.config.primary_benchmark in prepared
            else self._fallback_benchmark(prepared)
        )

        if benchmark_column is not None:
            benchmark = prepared[benchmark_column]
            benchmark_return_1 = benchmark.pct_change()

            for column, series in prepared.items():
                if column == benchmark_column:
                    continue

                base = self._base_name(column)
                asset_return_1 = series.pct_change()

                for window in self.config.correlation_windows:
                    df[f"{base}_corr_benchmark_{window}"] = (
                        asset_return_1
                        .rolling(
                            window=window,
                            min_periods=window,
                        )
                        .corr(benchmark_return_1)
                    )

                for window in self.config.beta_windows:
                    covariance = (
                        asset_return_1
                        .rolling(
                            window=window,
                            min_periods=window,
                        )
                        .cov(benchmark_return_1)
                    )

                    variance = (
                        benchmark_return_1
                        .rolling(
                            window=window,
                            min_periods=window,
                        )
                        .var()
                    )

                    df[f"{base}_beta_benchmark_{window}"] = (
                        covariance
                        / variance.replace(0, np.nan)
                    )

                for period in self.config.relative_strength_periods:
                    df[f"{base}_relative_strength_{period}"] = (
                        series.pct_change(period)
                        - benchmark.pct_change(period)
                    )

        self._add_rate_features(
            df=df,
            prepared=prepared,
        )

        self._add_risk_regime_features(
            df=df,
            prepared=prepared,
        )

        self._add_divergence_features(
            df=df,
            prepared=prepared,
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

    def _available_columns(
        self,
        dataframe: pd.DataFrame,
    ) -> list[str]:
        enabled_columns: list[str] = []

        for group in self.config.enabled_groups:
            for column in self.config.groups[group]:
                if column in dataframe.columns:
                    enabled_columns.append(column)

        return list(dict.fromkeys(enabled_columns))

    @staticmethod
    def _base_name(
        column: str,
    ) -> str:
        return (
            column[:-6]
            if column.endswith("_close")
            else column
        )

    @staticmethod
    def _fallback_benchmark(
        prepared: dict[str, pd.Series],
    ) -> str | None:
        candidates = (
            "cross_nasdaq_close",
            "cross_dax_close",
            "cross_nikkei_close",
            "cross_shanghai_close",
        )

        return next(
            (
                column
                for column in candidates
                if column in prepared
            ),
            None,
        )

    @staticmethod
    def _add_rate_features(
        *,
        df: pd.DataFrame,
        prepared: dict[str, pd.Series],
    ) -> None:
        us2y = prepared.get(
            "cross_us2y_close"
        )
        us10y = prepared.get(
            "cross_us10y_close"
        )

        if us2y is None or us10y is None:
            return

        df["cross_yield_curve_10y_2y"] = (
            us10y - us2y
        )

        df["cross_yield_curve_inverted"] = (
            df["cross_yield_curve_10y_2y"] < 0
        ).astype("int8")

        df["cross_yield_curve_change_5"] = (
            df["cross_yield_curve_10y_2y"]
            .diff(5)
        )

        df["cross_yield_curve_change_20"] = (
            df["cross_yield_curve_10y_2y"]
            .diff(20)
        )

    @staticmethod
    def _add_risk_regime_features(
        *,
        df: pd.DataFrame,
        prepared: dict[str, pd.Series],
    ) -> None:
        score = pd.Series(
            0.0,
            index=df.index,
            dtype="float64",
        )

        sp500 = prepared.get(
            "cross_sp500_close"
        )
        nasdaq = prepared.get(
            "cross_nasdaq_close"
        )
        vix = prepared.get(
            "cross_vix_close"
        )
        gold = prepared.get(
            "cross_gold_close"
        )
        dxy = prepared.get(
            "cross_dxy_close"
        )
        us10y = prepared.get(
            "cross_us10y_close"
        )

        if sp500 is not None:
            score = score + np.sign(
                sp500.pct_change(20)
            )

        if nasdaq is not None:
            score = score + np.sign(
                nasdaq.pct_change(20)
            )

        if vix is not None:
            score = score - np.sign(
                vix.pct_change(20)
            )

            vix_mean = vix.rolling(
                window=60,
                min_periods=60,
            ).mean()

            vix_std = vix.rolling(
                window=60,
                min_periods=60,
            ).std()

            df["cross_vix_zscore_60"] = (
                vix - vix_mean
            ) / vix_std.replace(
                0,
                np.nan,
            )

        if gold is not None:
            score = score - np.sign(
                gold.pct_change(20)
            )

        if dxy is not None:
            score = score - np.sign(
                dxy.pct_change(20)
            )

        if us10y is not None:
            score = score - np.sign(
                us10y.diff(20)
            )

        df["cross_risk_on_score"] = score
        df["cross_risk_off_score"] = -score

        df["cross_market_regime"] = np.select(
            [
                score >= 2.0,
                score <= -2.0,
            ],
            [
                1,
                -1,
            ],
            default=0,
        ).astype("int8")

    @staticmethod
    def _add_divergence_features(
        *,
        df: pd.DataFrame,
        prepared: dict[str, pd.Series],
    ) -> None:
        sp500 = prepared.get(
            "cross_sp500_close"
        )
        nasdaq = prepared.get(
            "cross_nasdaq_close"
        )
        gold = prepared.get(
            "cross_gold_close"
        )
        oil = prepared.get(
            "cross_oil_close"
        )
        dxy = prepared.get(
            "cross_dxy_close"
        )

        if sp500 is not None and nasdaq is not None:
            df["cross_nasdaq_sp500_divergence_20"] = (
                nasdaq.pct_change(20)
                - sp500.pct_change(20)
            )

        if sp500 is not None and gold is not None:
            df["cross_equity_gold_spread_20"] = (
                sp500.pct_change(20)
                - gold.pct_change(20)
            )

        if oil is not None and dxy is not None:
            df["cross_oil_dxy_divergence_20"] = (
                oil.pct_change(20)
                + dxy.pct_change(20)
            )

    def _validate_config(self) -> None:
        unknown_groups = sorted(
            set(self.config.enabled_groups).difference(
                self.config.groups
            )
        )

        if unknown_groups:
            raise ValueError(
                "Unbekannte Cross-Asset-Gruppen: "
                f"{unknown_groups}"
            )

        period_groups: Iterable[tuple[int, ...]] = (
            self.config.return_periods,
            self.config.volatility_windows,
            self.config.correlation_windows,
            self.config.beta_windows,
            self.config.relative_strength_periods,
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


cross_asset_feature_builder = CrossAssetFeatureBuilder()
