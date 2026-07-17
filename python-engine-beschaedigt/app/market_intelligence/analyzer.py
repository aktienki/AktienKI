from __future__ import annotations

from dataclasses import dataclass
from statistics import fmean
from typing import Any, Iterable


@dataclass(frozen=True, slots=True)
class MarketIntelligenceResult:
    market_score: float
    breadth_score: float
    risk_mode: str
    market_trend: str
    volatility: float | None
    buy_count: int
    sell_count: int
    hold_count: int
    average_score: float | None
    average_confidence: float | None
    average_prediction: float | None
    sectors: list[dict[str, Any]]
    winning_sectors: list[str]
    losing_sectors: list[str]
    metadata: dict[str, Any]


class MarketIntelligenceAnalyzer:
    """Pure, deterministic market scoring without database dependencies."""

    BUY_SIGNALS = {"BUY", "STRONG_BUY", "LONG"}
    SELL_SIGNALS = {"SELL", "STRONG_SELL", "SHORT"}

    def analyze(
        self,
        predictions: Iterable[dict[str, Any]],
        assets: Iterable[dict[str, Any]] = (),
    ) -> MarketIntelligenceResult:
        rows = [dict(row) for row in predictions]
        asset_rows = [dict(row) for row in assets]

        buy = sum(self._signal(row) in self.BUY_SIGNALS for row in rows)
        sell = sum(self._signal(row) in self.SELL_SIGNALS for row in rows)
        hold = max(0, len(rows) - buy - sell)
        directional = buy + sell
        breadth = 50.0 if directional == 0 else 50.0 + 50.0 * (buy - sell) / directional
        breadth = self._clamp(breadth)

        scores = self._numbers(rows, "ai_score")
        confidences = self._numbers(rows, "confidence")
        returns = self._numbers(rows, "market_return_5d")

        average_score = fmean(scores) if scores else None
        average_confidence = fmean(confidences) if confidences else None
        average_prediction = fmean(returns) if returns else None

        asset_momentum = self._asset_momentum(asset_rows)
        volatility = self._volatility(asset_rows)
        volatility_component = self._volatility_component(volatility)

        prediction_component = average_score if average_score is not None else 50.0
        confidence_component = self._normalize_confidence(average_confidence)
        market_score = self._clamp(
            breadth * 0.40
            + prediction_component * 0.25
            + confidence_component * 0.15
            + asset_momentum * 0.10
            + volatility_component * 0.10
        )

        sectors = self._sectors(rows)
        winners = [x["sector"] for x in sectors[:3] if x["average_score"] >= 50]
        losers = [x["sector"] for x in reversed(sectors[-3:]) if x["average_score"] < 50]

        if market_score >= 62 and breadth >= 55 and (volatility is None or volatility < 25):
            risk_mode = "RISK_ON"
        elif market_score <= 38 or breadth <= 35 or (volatility is not None and volatility >= 30):
            risk_mode = "RISK_OFF"
        else:
            risk_mode = "NEUTRAL"

        if market_score >= 60:
            trend = "BULLISH"
        elif market_score <= 40:
            trend = "BEARISH"
        else:
            trend = "NEUTRAL"

        return MarketIntelligenceResult(
            market_score=round(market_score, 2),
            breadth_score=round(breadth, 2),
            risk_mode=risk_mode,
            market_trend=trend,
            volatility=round(volatility, 4) if volatility is not None else None,
            buy_count=buy,
            sell_count=sell,
            hold_count=hold,
            average_score=round(average_score, 2) if average_score is not None else None,
            average_confidence=round(average_confidence, 4) if average_confidence is not None else None,
            average_prediction=round(average_prediction, 6) if average_prediction is not None else None,
            sectors=sectors,
            winning_sectors=winners,
            losing_sectors=losers,
            metadata={
                "formula_version": "market-intelligence-1.0",
                "prediction_rows": len(rows),
                "asset_rows": len(asset_rows),
                "components": {
                    "breadth": round(breadth, 2),
                    "prediction": round(prediction_component, 2),
                    "confidence": round(confidence_component, 2),
                    "asset_momentum": round(asset_momentum, 2),
                    "volatility": round(volatility_component, 2),
                },
            },
        )

    def _sectors(self, rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
        grouped: dict[str, list[dict[str, Any]]] = {}
        for row in rows:
            sector = str(row.get("sector") or "Unknown")
            grouped.setdefault(sector, []).append(row)

        result: list[dict[str, Any]] = []
        for sector, sector_rows in grouped.items():
            scores = self._numbers(sector_rows, "ai_score")
            returns = self._numbers(sector_rows, "market_return_5d")
            count = len(sector_rows)
            buy = sum(self._signal(row) in self.BUY_SIGNALS for row in sector_rows)
            sell = sum(self._signal(row) in self.SELL_SIGNALS for row in sector_rows)
            avg_score = fmean(scores) if scores else 50.0
            avg_return = fmean(returns) if returns else 0.0
            trend = "BULLISH" if avg_score >= 60 else "BEARISH" if avg_score <= 40 else "NEUTRAL"
            result.append({
                "sector": sector,
                "average_return": round(avg_return, 6),
                "average_score": round(avg_score, 2),
                "buy_ratio": round(buy / count, 6) if count else 0.0,
                "sell_ratio": round(sell / count, 6) if count else 0.0,
                "trend": trend,
                "companies_count": count,
            })

        result.sort(key=lambda item: (item["average_score"], item["average_return"]), reverse=True)
        for rank, item in enumerate(result, start=1):
            item["rank"] = rank
        return result

    def _asset_momentum(self, assets: list[dict[str, Any]]) -> float:
        changes = self._numbers(assets, "change_percent")
        if not changes:
            return 50.0
        return self._clamp(50.0 + fmean(changes) * 8.0)

    def _volatility(self, assets: list[dict[str, Any]]) -> float | None:
        for row in assets:
            symbol = str(row.get("symbol") or "").upper()
            if symbol in {"^VIX", "VIX"} and row.get("price") is not None:
                return float(row["price"])
        return None

    def _volatility_component(self, volatility: float | None) -> float:
        if volatility is None:
            return 50.0
        return self._clamp(100.0 - max(0.0, volatility - 10.0) * 4.0)

    def _normalize_confidence(self, confidence: float | None) -> float:
        if confidence is None:
            return 50.0
        return self._clamp(confidence * 100.0 if confidence <= 1.0 else confidence)

    def _signal(self, row: dict[str, Any]) -> str:
        return str(row.get("signal") or "HOLD").upper()

    def _numbers(self, rows: Iterable[dict[str, Any]], key: str) -> list[float]:
        values: list[float] = []
        for row in rows:
            value = row.get(key)
            if value is not None:
                values.append(float(value))
        return values

    def _clamp(self, value: float) -> float:
        return max(0.0, min(100.0, value))
