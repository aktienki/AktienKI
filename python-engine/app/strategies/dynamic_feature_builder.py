import numpy as np
import pandas as pd

class DynamicFeatureBuilder:
    def build(self, base_frame, strategy, cross_asset_frames=None):
        if base_frame.empty:
            return pd.DataFrame()

        result = base_frame.copy().sort_index()
        close = result["close"]
        high = result["high"]
        low = result["low"]
        cfg = strategy.configuration.get("technical_features", {})

        for period in cfg.get("ema_periods", []):
            period = int(period)
            name = f"ema_{period}"
            result[name] = close.ewm(span=period, adjust=False).mean()
            result[f"close_to_{name}_pct"] = close / result[name].replace(0, np.nan) - 1

        for period in cfg.get("sma_periods", []):
            period = int(period)
            name = f"sma_{period}"
            result[name] = close.rolling(period, min_periods=period).mean()
            result[f"close_to_{name}_pct"] = close / result[name].replace(0, np.nan) - 1

        delta = close.diff()
        for period in cfg.get("rsi_periods", []):
            period = int(period)
            gain = delta.clip(lower=0)
            loss = -delta.clip(upper=0)
            avg_gain = gain.ewm(alpha=1/period, adjust=False, min_periods=period).mean()
            avg_loss = loss.ewm(alpha=1/period, adjust=False, min_periods=period).mean()
            rs = avg_gain / avg_loss.replace(0, np.nan)
            result[f"rsi_{period}"] = 100 - (100 / (1 + rs))

        previous_close = close.shift(1)
        tr = pd.concat([
            high-low,
            (high-previous_close).abs(),
            (low-previous_close).abs(),
        ], axis=1).max(axis=1)

        for period in cfg.get("atr_periods", []):
            period = int(period)
            result[f"atr_{period}"] = tr.ewm(
                alpha=1/period, adjust=False, min_periods=period
            ).mean()

        for variant in cfg.get("macd_variants", []):
            fast, slow, signal = map(int, (
                variant["fast"], variant["slow"], variant["signal"]
            ))
            prefix = f"macd_{fast}_{slow}_{signal}"
            fast_ema = close.ewm(span=fast, adjust=False).mean()
            slow_ema = close.ewm(span=slow, adjust=False).mean()
            result[prefix] = fast_ema - slow_ema
            result[f"{prefix}_signal"] = result[prefix].ewm(span=signal, adjust=False).mean()
            result[f"{prefix}_histogram"] = result[prefix] - result[f"{prefix}_signal"]

        for pair in cfg.get("crossovers", []):
            fast, slow = int(pair["fast"]), int(pair["slow"])
            f, s = f"ema_{fast}", f"ema_{slow}"
            if f not in result:
                result[f] = close.ewm(span=fast, adjust=False).mean()
            if s not in result:
                result[s] = close.ewm(span=slow, adjust=False).mean()
            state = result[f] > result[s]
            prev = state.shift(1).fillna(False)
            result[f"{f}_crossed_above_{s}"] = (state & ~prev).astype(int)
            result[f"{f}_crossed_below_{s}"] = (~state & prev).astype(int)
            result[f"{f}_to_{s}_pct"] = result[f] / result[s].replace(0, np.nan) - 1

        frames = cross_asset_frames or {}
        cross_cfg = strategy.configuration.get("cross_asset_features", {})
        if cross_cfg.get("enabled", False):
            for item in strategy.instruments:
                frame = frames.get(item.alias)
                if frame is None or frame.empty:
                    continue
                ext = frame["close"].reindex(result.index).ffill()
                alias = item.alias.lower()
                result[f"{alias}_return_5d"] = ext.pct_change(5)
                result[f"{alias}_return_20d"] = ext.pct_change(20)
                result[f"relative_return_vs_{alias}_20d"] = (
                    close.pct_change(20) - ext.pct_change(20)
                )

        horizon = int(strategy.target_horizon_days)
        result[f"target_return_{horizon}d"] = close.shift(-horizon) / close - 1
        return result
