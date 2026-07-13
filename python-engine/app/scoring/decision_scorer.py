from __future__ import annotations

import math


class DecisionScorer:
    @staticmethod
    def clamp(value: float, minimum: float, maximum: float) -> float:
        return max(minimum, min(maximum, value))

    def score(
        self,
        *,
        market_return: float,
        confidence: float,
        volatility: float | None,
        trend_strength: float,
    ) -> dict[str, float | str]:
        direction_score = self.clamp(
            math.tanh(market_return * 12.0) * 100.0,
            -100.0,
            100.0,
        )

        signal_strength = abs(direction_score)

        volatility_value = abs(volatility or 0.0)
        risk_score = self.clamp(volatility_value * 2.5, 0.0, 100.0)

        confidence_score = self.clamp(confidence, 0.0, 100.0)
        trend_score = self.clamp(trend_strength, 0.0, 100.0)

        ai_score = (
            signal_strength * 0.45
            + confidence_score * 0.30
            + trend_score * 0.15
            + (100.0 - risk_score) * 0.10
        )
        ai_score = self.clamp(ai_score, 0.0, 100.0)

        signal = self.signal_for(direction_score)

        return {
            "direction_score": round(direction_score, 4),
            "signal_strength": round(signal_strength, 4),
            "confidence": round(confidence_score, 4),
            "risk_score": round(risk_score, 4),
            "trend_strength": round(trend_score, 4),
            "ai_score": round(ai_score, 4),
            "signal": signal,
        }

    @staticmethod
    def signal_for(direction_score: float) -> str:
        if direction_score >= 80:
            return "strong_buy"
        if direction_score >= 40:
            return "buy"
        if direction_score >= 15:
            return "weak_buy"
        if direction_score <= -80:
            return "strong_sell"
        if direction_score <= -40:
            return "sell"
        if direction_score <= -15:
            return "weak_sell"
        return "neutral"
