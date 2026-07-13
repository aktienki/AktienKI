from __future__ import annotations

import json
from sqlalchemy import text

from app.models.decision import Decision


class PredictionRepository:
    def __init__(self, session):
        self.session = session

    def save(self, decision: Decision) -> int:
        payload = decision.to_dict()

        return int(
            self.session.execute(
                text(
                    """
                    INSERT INTO predictions (
                        instrument_id,
                        trained_model_id,
                        prediction_time,
                        interval,
                        current_price,
                        predicted_price_5d,
                        price_difference_5d,
                        market_return_5d,
                        long_return_5d,
                        short_return_5d,
                        strategy,
                        strategy_return_5d,
                        direction_score,
                        signal_strength,
                        confidence,
                        risk_score,
                        trend_strength,
                        ai_score,
                        signal,
                        status,
                        explanation,
                        metadata,
                        created_at,
                        updated_at
                    )
                    VALUES (
                        :instrument_id,
                        :trained_model_id,
                        NOW(),
                        :interval,
                        :current_price,
                        :predicted_price_5d,
                        :price_difference_5d,
                        :market_return_5d,
                        :long_return_5d,
                        :short_return_5d,
                        :strategy,
                        :strategy_return_5d,
                        :direction_score,
                        :signal_strength,
                        :confidence,
                        :risk_score,
                        :trend_strength,
                        :ai_score,
                        :signal,
                        'pending_validation',
                        CAST(:explanation AS jsonb),
                        CAST(:metadata AS jsonb),
                        NOW(),
                        NOW()
                    )
                    RETURNING id
                    """
                ),
                {
                    **payload,
                    "explanation": json.dumps(payload["explanation"]),
                    "metadata": json.dumps(payload["metadata"]),
                },
            ).scalar_one()
        )
