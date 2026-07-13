from __future__ import annotations

import numpy as np
import pandas as pd


class IndicatorCalculator:
    VERSION = "1.0.0"

    @staticmethod
    def calculate(frame: pd.DataFrame) -> pd.DataFrame:
        if frame.empty:
            return pd.DataFrame()

        result = frame.copy()

        close = result["close"]
        high = result["high"]
        low = result["low"]
        volume = result["volume"]

        result["sma_20"] = close.rolling(20, min_periods=20).mean()
        result["sma_50"] = close.rolling(50, min_periods=50).mean()
        result["sma_200"] = close.rolling(200, min_periods=200).mean()

        result["ema_12"] = close.ewm(span=12, adjust=False).mean()
        result["ema_20"] = close.ewm(span=20, adjust=False).mean()
        result["ema_26"] = close.ewm(span=26, adjust=False).mean()
        result["ema_50"] = close.ewm(span=50, adjust=False).mean()
        result["ema_200"] = close.ewm(span=200, adjust=False).mean()

        delta = close.diff()
        gain = delta.clip(lower=0)
        loss = -delta.clip(upper=0)
        average_gain = gain.ewm(alpha=1 / 14, adjust=False, min_periods=14).mean()
        average_loss = loss.ewm(alpha=1 / 14, adjust=False, min_periods=14).mean()
        relative_strength = average_gain / average_loss.replace(0, np.nan)
        result["rsi_14"] = 100 - (100 / (1 + relative_strength))

        result["macd"] = result["ema_12"] - result["ema_26"]
        result["macd_signal"] = result["macd"].ewm(span=9, adjust=False).mean()
        result["macd_histogram"] = result["macd"] - result["macd_signal"]

        previous_close = close.shift(1)
        true_range = pd.concat(
            [
                high - low,
                (high - previous_close).abs(),
                (low - previous_close).abs(),
            ],
            axis=1,
        ).max(axis=1)

        result["atr_14"] = true_range.ewm(
            alpha=1 / 14,
            adjust=False,
            min_periods=14,
        ).mean()

        upward_move = high.diff()
        downward_move = -low.diff()

        plus_dm = pd.Series(
            np.where(
                (upward_move > downward_move) & (upward_move > 0),
                upward_move,
                0.0,
            ),
            index=result.index,
        )

        minus_dm = pd.Series(
            np.where(
                (downward_move > upward_move) & (downward_move > 0),
                downward_move,
                0.0,
            ),
            index=result.index,
        )

        atr = result["atr_14"].replace(0, np.nan)

        plus_di = 100 * plus_dm.ewm(
            alpha=1 / 14,
            adjust=False,
            min_periods=14,
        ).mean() / atr

        minus_di = 100 * minus_dm.ewm(
            alpha=1 / 14,
            adjust=False,
            min_periods=14,
        ).mean() / atr

        dx = 100 * (plus_di - minus_di).abs() / (
            plus_di + minus_di
        ).replace(0, np.nan)

        result["adx_14"] = dx.ewm(
            alpha=1 / 14,
            adjust=False,
            min_periods=14,
        ).mean()

        rolling_std = close.rolling(20, min_periods=20).std(ddof=0)
        result["bollinger_middle"] = result["sma_20"]
        result["bollinger_upper"] = result["sma_20"] + (2 * rolling_std)
        result["bollinger_lower"] = result["sma_20"] - (2 * rolling_std)
        result["bollinger_width"] = (
            result["bollinger_upper"] - result["bollinger_lower"]
        ) / result["bollinger_middle"].replace(0, np.nan)

        rolling_low = low.rolling(14, min_periods=14).min()
        rolling_high = high.rolling(14, min_periods=14).max()

        result["stochastic_k"] = 100 * (
            close - rolling_low
        ) / (rolling_high - rolling_low).replace(0, np.nan)

        result["stochastic_d"] = result["stochastic_k"].rolling(
            3,
            min_periods=3,
        ).mean()

        result["roc_12"] = close.pct_change(12) * 100
        result["momentum_10"] = close - close.shift(10)

        log_returns = np.log(
            close / close.shift(1)
        ).replace([np.inf, -np.inf], np.nan)

        result["volatility_20"] = (
            log_returns.rolling(20, min_periods=20).std(ddof=0)
            * np.sqrt(252)
            * 100
        )

        result["volume_sma_20"] = volume.rolling(
            20,
            min_periods=20,
        ).mean()

        return result
