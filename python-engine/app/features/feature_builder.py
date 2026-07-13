import pandas as pd


class FeatureBuilder:
    VERSION = "1.0.0"

    FEATURE_COLUMNS = [
        "close",
        "volume",
        "rsi_14",
        "ema_20",
        "ema_50",
        "ema_200",
        "macd",
        "atr_14",
        "volatility_20",
    ]

    def build(self, frame: pd.DataFrame) -> pd.DataFrame:
        if frame.empty:
            return pd.DataFrame()

        result = frame.copy()

        result["target_return_1d"] = (
            result["close"].shift(-1) / result["close"] - 1
        )
        result["target_return_5d"] = (
            result["close"].shift(-5) / result["close"] - 1
        )
        result["target_return_20d"] = (
            result["close"].shift(-20) / result["close"] - 1
        )

        result["target_direction"] = 0
        result.loc[
            result["target_return_5d"] > 0.02,
            "target_direction",
        ] = 1
        result.loc[
            result["target_return_5d"] < -0.02,
            "target_direction",
        ] = -1

        result["feature_version"] = self.VERSION

        return result
