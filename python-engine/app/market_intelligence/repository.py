from __future__ import annotations

import json
from datetime import datetime, timezone
from typing import Any

from sqlalchemy import text

from .analyzer import MarketIntelligenceResult


class MarketIntelligenceRepository:
    def __init__(self, session):
        self.session = session

    def latest_predictions(self) -> list[dict[str, Any]]:
        query = text("""
            WITH latest AS (
                SELECT instrument_id, MAX(prediction_time) AS prediction_time
                FROM predictions
                GROUP BY instrument_id
            )
            SELECT
                p.instrument_id,
                p.signal,
                p.ai_score,
                p.confidence,
                p.market_return_5d,
                i.sector
            FROM predictions p
            JOIN latest l
              ON l.instrument_id = p.instrument_id
             AND l.prediction_time = p.prediction_time
            JOIN instruments i
              ON i.id = p.instrument_id
            WHERE p.status <> 'failed'
        """)

        return [
            dict(row)
            for row in self.session.execute(query).mappings().all()
        ]

    def latest_assets(self) -> list[dict[str, Any]]:
        query = text("""
            SELECT
                ma.symbol,
                ma.price,
                ma.change_percent,
                ma.category
            FROM market_assets ma
            JOIN (
                SELECT
                    symbol,
                    MAX(observed_at) AS observed_at
                FROM market_assets
                GROUP BY symbol
            ) latest
              ON latest.symbol = ma.symbol
             AND latest.observed_at = ma.observed_at
        """)

        return [
            dict(row)
            for row in self.session.execute(query).mappings().all()
        ]

    def persist(self, result: MarketIntelligenceResult) -> int:
        now = datetime.now(timezone.utc)

        snapshot_id = self.session.execute(
            text("""
                INSERT INTO market_snapshots (
                    snapshot_time,
                    market_score,
                    risk_mode,
                    market_trend,
                    volatility,
                    breadth_score,
                    buy_signals,
                    sell_signals,
                    hold_signals,
                    winning_sectors,
                    losing_sectors,
                    metadata,
                    created_at,
                    updated_at
                )
                VALUES (
                    :snapshot_time,
                    :market_score,
                    :risk_mode,
                    :market_trend,
                    :volatility,
                    :breadth_score,
                    :buy_signals,
                    :sell_signals,
                    :hold_signals,
                    CAST(:winning_sectors AS jsonb),
                    CAST(:losing_sectors AS jsonb),
                    CAST(:metadata AS jsonb),
                    :created_at,
                    :updated_at
                )
                RETURNING id
            """),
            {
                "snapshot_time": now,
                "market_score": result.market_score,
                "risk_mode": result.risk_mode,
                "market_trend": result.market_trend,
                "volatility": result.volatility,
                "breadth_score": result.breadth_score,
                "buy_signals": result.buy_count,
                "sell_signals": result.sell_count,
                "hold_signals": result.hold_count,
                "winning_sectors": self._json(result.winning_sectors),
                "losing_sectors": self._json(result.losing_sectors),
                "metadata": self._json(result.metadata),
                "created_at": now,
                "updated_at": now,
            },
        ).scalar_one()

        self.session.execute(
            text("""
                UPDATE market_assets
                SET
                    market_snapshot_id = :snapshot_id,
                    updated_at = :updated_at
                WHERE market_snapshot_id IS NULL
            """),
            {
                "snapshot_id": snapshot_id,
                "updated_at": now,
            },
        )

        self.session.execute(
            text("""
                INSERT INTO market_statistics (
                    market_snapshot_id,
                    companies_total,
                    buy_count,
                    sell_count,
                    hold_count,
                    average_score,
                    average_confidence,
                    average_prediction,
                    average_hitrate,
                    metadata,
                    created_at,
                    updated_at
                )
                VALUES (
                    :snapshot_id,
                    :companies_total,
                    :buy_count,
                    :sell_count,
                    :hold_count,
                    :average_score,
                    :average_confidence,
                    :average_prediction,
                    NULL,
                    CAST(:metadata AS jsonb),
                    :created_at,
                    :updated_at
                )
            """),
            {
                "snapshot_id": snapshot_id,
                "companies_total": (
                    result.buy_count
                    + result.sell_count
                    + result.hold_count
                ),
                "buy_count": result.buy_count,
                "sell_count": result.sell_count,
                "hold_count": result.hold_count,
                "average_score": result.average_score,
                "average_confidence": result.average_confidence,
                "average_prediction": result.average_prediction,
                "metadata": self._json({
                    "formula_version": "market-intelligence-1.0",
                }),
                "created_at": now,
                "updated_at": now,
            },
        )

        for sector in result.sectors:
            self.session.execute(
                text("""
                    INSERT INTO sector_snapshots (
                        market_snapshot_id,
                        sector,
                        average_return,
                        average_score,
                        buy_ratio,
                        sell_ratio,
                        trend,
                        rank,
                        companies_count,
                        metadata,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :snapshot_id,
                        :sector,
                        :average_return,
                        :average_score,
                        :buy_ratio,
                        :sell_ratio,
                        :trend,
                        :rank,
                        :companies_count,
                        CAST(:metadata AS jsonb),
                        :created_at,
                        :updated_at
                    )
                """),
                {
                    "snapshot_id": snapshot_id,
                    **sector,
                    "metadata": self._json({}),
                    "created_at": now,
                    "updated_at": now,
                },
            )

        return int(snapshot_id)

    def _json(self, value: Any) -> str:
        return json.dumps(
            value,
            ensure_ascii=False,
            default=str,
        )