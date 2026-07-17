from __future__ import annotations

from typing import Any

from .analyzer import MarketIntelligenceAnalyzer
from .repository import MarketIntelligenceRepository


class MarketIntelligenceService:
    def __init__(self, session_factory):
        self.session_factory = session_factory
        self.analyzer = MarketIntelligenceAnalyzer()

    def run(self) -> dict[str, Any]:
        with self.session_factory() as session:
            repository = MarketIntelligenceRepository(session)
            predictions = repository.latest_predictions()
            assets = repository.latest_assets()
            result = self.analyzer.analyze(predictions, assets)
            snapshot_id = repository.persist(result)
            session.commit()

        return {
            "snapshot_id": snapshot_id,
            "market_score": result.market_score,
            "risk_mode": result.risk_mode,
            "market_trend": result.market_trend,
            "breadth_score": result.breadth_score,
            "buy_signals": result.buy_count,
            "sell_signals": result.sell_count,
            "hold_signals": result.hold_count,
            "sectors": len(result.sectors),
            "winning_sectors": result.winning_sectors,
            "losing_sectors": result.losing_sectors,
        }
