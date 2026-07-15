from __future__ import annotations

import numpy as np
import pandas as pd


class MarketFeatures:
    """
    Erzeugt Marktkontext-Features aus bereits zusammengeführten
    Cross-Asset-Spalten.

    Erwartete optionale Präfixe:

    - SPY
    - QQQ
    - VIX
    - DXY
    - US10Y
    - GOLD
    - OIL
    - BTC
    - SECTOR

    Unterstützte Spaltenformen:

    - SPY_Close
    - SPY_Return
    - spy_close
    - spy_return

    Fehlende Marktspalten führen nicht zu einem Fehler. Dadurch kann
    der Feature Store zunächst auch nur mit OHLCV-Daten laufen.
    """

    ASSETS = {
        "spy": ["SPY", "spy"],
        "qqq": ["QQQ", "qqq"],
        "vix": ["VIX", "vix"],
        "dxy": ["DXY", "dxy"],
        "us10y": ["US10Y", "us10y"],
        "gold": ["GOLD", "gold"],
        "oil": ["OIL", "oil"],
        "btc": ["BTC", "btc"],
        "sector": ["SECTOR", "sector"],
    }

    RETURN_WINDOWS = [1, 3, 6, 12, 24]
    TREND_WINDOWS = [10, 20, 50]

    @classmethod
    def transform(
        cls,
        dataframe: pd.DataFrame,
    ) -> pd.DataFrame:
        df = dataframe.copy()

        for asset_name, aliases in cls.ASSETS.items():
            close_column = cls._find_column(
                df,
                aliases,
                suffixes=[
                    "Close",
                    "close",
                    "Price",
                    "price",
                ],
            )

            return_column = cls._find_column(
                df,
                aliases,
                suffixes=[
                    "Return",
                    "return",
                ],
            )

            if close_column is None and return_column is None:
                continue

            if close_column is not None:
                close = pd.to_numeric(
                    df[close_column],
                    errors="coerce",
                )

                df[f"market_{asset_name}_close"] = close

                for window in cls.RETURN_WINDOWS:
                    df[f"market_{asset_name}_return_{window}"] = (
                        close.pct_change(window)
                    )

                for window in cls.TREND_WINDOWS:
                    moving_average = (
                        close
                        .rolling(window)
                        .mean()
                    )

                    df[f"market_{asset_name}_ma_{window}"] = (
                        moving_average
                    )

                    df[
                        f"market_{asset_name}_distance_ma_{window}"
                    ] = (
                        close
                        / moving_average.replace(
                            0,
                            np.nan,
                        )
                        - 1
                    )

                df[f"market_{asset_name}_volatility_20"] = (
                    close
                    .pct_change()
                    .rolling(20)
                    .std()
                )

                df[f"market_{asset_name}_momentum_20"] = (
                    close
                    / close.shift(20)
                    - 1
                )

                df[f"market_{asset_name}_trend_positive"] = (
                    close
                    > close.rolling(20).mean()
                ).astype(int)

            if return_column is not None:
                returns = pd.to_numeric(
                    df[return_column],
                    errors="coerce",
                )

                df[f"market_{asset_name}_return_source"] = (
                    returns
                )

                df[
                    f"market_{asset_name}_return_source_mean_20"
                ] = (
                    returns
                    .rolling(20)
                    .mean()
                )

                df[
                    f"market_{asset_name}_return_source_std_20"
                ] = (
                    returns
                    .rolling(20)
                    .std()
                )

        cls._add_relative_strength_features(df)
        cls._add_risk_regime_features(df)
        cls._add_market_breadth_features(df)

        return df.replace(
            [np.inf, -np.inf],
            np.nan,
        )

    @classmethod
    def _add_relative_strength_features(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        close = cls._series_or_none(
            dataframe,
            "Close",
        )

        if close is None:
            return

        stock_return_20 = (
            close
            / close.shift(20)
            - 1
        )

        for asset_name in [
            "spy",
            "qqq",
            "sector",
        ]:
            column = (
                f"market_{asset_name}_return_20"
            )

            if column not in dataframe.columns:
                continue

            dataframe[
                f"relative_strength_{asset_name}_20"
            ] = (
                stock_return_20
                - dataframe[column]
            )

        if (
            "market_spy_return_20"
            in dataframe.columns
            and "market_qqq_return_20"
            in dataframe.columns
        ):
            dataframe["growth_vs_market_20"] = (
                dataframe["market_qqq_return_20"]
                - dataframe["market_spy_return_20"]
            )

    @classmethod
    def _add_risk_regime_features(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        vix_column = cls._first_existing(
            dataframe,
            [
                "market_vix_close",
                "VIX",
                "vix",
            ],
        )

        if vix_column is not None:
            vix = pd.to_numeric(
                dataframe[vix_column],
                errors="coerce",
            )

            dataframe["risk_vix_low"] = (
                vix < 15
            ).astype(int)

            dataframe["risk_vix_normal"] = (
                (vix >= 15)
                & (vix < 25)
            ).astype(int)

            dataframe["risk_vix_high"] = (
                vix >= 25
            ).astype(int)

            dataframe["risk_vix_change_5"] = (
                vix.pct_change(5)
            )

            dataframe["risk_vix_zscore_50"] = (
                vix
                - vix.rolling(50).mean()
            ) / vix.rolling(50).std().replace(
                0,
                np.nan,
            )

        if (
            "market_spy_trend_positive"
            in dataframe.columns
            and "risk_vix_high"
            in dataframe.columns
        ):
            dataframe["risk_on_regime"] = (
                (
                    dataframe[
                        "market_spy_trend_positive"
                    ]
                    == 1
                )
                & (
                    dataframe["risk_vix_high"]
                    == 0
                )
            ).astype(int)

            dataframe["risk_off_regime"] = (
                (
                    dataframe[
                        "market_spy_trend_positive"
                    ]
                    == 0
                )
                & (
                    dataframe["risk_vix_high"]
                    == 1
                )
            ).astype(int)

    @classmethod
    def _add_market_breadth_features(
        cls,
        dataframe: pd.DataFrame,
    ) -> None:
        trend_columns = [
            column
            for column in dataframe.columns
            if (
                column.startswith("market_")
                and column.endswith(
                    "_trend_positive"
                )
            )
        ]

        if not trend_columns:
            return

        dataframe["market_breadth_score"] = (
            dataframe[trend_columns]
            .mean(axis=1)
        )

        dataframe["market_breadth_bullish"] = (
            dataframe["market_breadth_score"]
            >= 0.66
        ).astype(int)

        dataframe["market_breadth_bearish"] = (
            dataframe["market_breadth_score"]
            <= 0.33
        ).astype(int)

    @staticmethod
    def _find_column(
        dataframe: pd.DataFrame,
        aliases: list[str],
        *,
        suffixes: list[str],
    ) -> str | None:
        candidates: list[str] = []

        for alias in aliases:
            for suffix in suffixes:
                candidates.extend(
                    [
                        f"{alias}_{suffix}",
                        f"{alias}.{suffix}",
                        f"{alias}{suffix}",
                    ]
                )

        return MarketFeatures._first_existing(
            dataframe,
            candidates,
        )

    @staticmethod
    def _first_existing(
        dataframe: pd.DataFrame,
        candidates: list[str],
    ) -> str | None:
        for candidate in candidates:
            if candidate in dataframe.columns:
                return candidate

        return None

    @staticmethod
    def _series_or_none(
        dataframe: pd.DataFrame,
        column: str,
    ) -> pd.Series | None:
        if column not in dataframe.columns:
            return None

        return pd.to_numeric(
            dataframe[column],
            errors="coerce",
        )
