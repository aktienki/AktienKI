from __future__ import annotations

import json
from dataclasses import asdict
from typing import Any

from app.database.connection import Database
from app.models.prediction import Prediction
from app.repositories.base_repository import BaseRepository


class PredictionRepository(BaseRepository[Prediction]):
    table_name = "predictions"

    def __init__(self, database: Database) -> None:
        super().__init__(database)

    def _from_row(self, row: dict[str, Any]) -> Prediction:
        values = {k: row.get(k) for k in Prediction.__dataclass_fields__ if k in row}
        return Prediction(**values)

    def latest_for_instrument(self, instrument_id: int, *, timeframe: str | None = None) -> Prediction | None:
        sql = "SELECT * FROM predictions WHERE instrument_id=%s"
        params: list[Any] = [instrument_id]
        if timeframe is not None:
            sql += " AND timeframe=%s"
            params.append(timeframe)
        sql += " ORDER BY COALESCE(prediction_date, prediction_time) DESC, id DESC LIMIT 1"
        row = self.database.fetch_one(sql, tuple(params))
        return self._from_row(row) if row else None

    def top_predictions(self, *, timeframe: str = "1d", signal: str | None = None, limit: int = 10) -> list[Prediction]:
        sql = "SELECT * FROM predictions WHERE timeframe=%s"
        params: list[Any] = [timeframe]
        if signal is not None:
            sql += " AND signal=%s"
            params.append(signal)
        sql += " ORDER BY prediction_score DESC NULLS LAST, id DESC LIMIT %s"
        params.append(limit)
        return [self._from_row(row) for row in self.database.fetch_all(sql, tuple(params))]

    @staticmethod
    def _json_value(value: Any) -> str:
        return json.dumps(value, ensure_ascii=False, default=str)

    def insert(self, prediction: Prediction) -> Prediction:
        values = asdict(prediction)
        for key in ("id", "created_at", "updated_at"):
            values.pop(key, None)
        # Current schema path: do not send compatibility-only legacy columns.
        legacy_only = {
            "prediction_run_id", "prediction_date", "target_date", "predicted_price",
            "predicted_market_return", "predicted_strategy_return", "buy_probability",
            "hold_probability", "sell_probability", "prediction_score", "ensemble_score",
            "trend", "risk_level", "target_price", "stop_loss_percent",
            "take_profit_percent", "risk_reward", "hitrate", "model_results",
        }
        for key in legacy_only:
            values.pop(key, None)
        json_cols = {"explanation", "metadata"}
        cols = list(values)
        placeholders = ["%s::jsonb" if c in json_cols else "%s" for c in cols]
        params = tuple(self._json_value(values[c]) if c in json_cols else values[c] for c in cols)
        row = self.database.fetch_one(
            f"INSERT INTO predictions ({', '.join(cols)}, created_at, updated_at) "
            f"VALUES ({', '.join(placeholders)}, NOW(), NOW()) RETURNING *",
            params,
        )
        if row is None:
            raise RuntimeError("Prediction konnte nicht gespeichert werden")
        return self._from_row(row)

    def upsert(self, prediction: Prediction) -> Prediction:
        if prediction.prediction_run_id is None:
            return self.insert(prediction)

        # Compatibility path for the former run-based prediction schema.
        values = asdict(prediction)
        legacy_cols = [
            "prediction_run_id", "instrument_id", "prediction_date", "target_date",
            "timeframe", "current_price", "predicted_price", "predicted_market_return",
            "predicted_strategy_return", "buy_probability", "hold_probability",
            "sell_probability", "prediction_score", "ensemble_score", "confidence",
            "risk_score", "signal", "trend", "risk_level", "target_price", "stop_loss_percent",
            "take_profit_percent", "risk_reward", "hitrate", "model_results", "metadata",
        ]
        json_cols = {"model_results", "metadata"}
        params = tuple(
            self._json_value(values[c]) if c in json_cols else values[c]
            for c in legacy_cols
        )
        insert_placeholders = ["%s::jsonb" if c in json_cols else "%s" for c in legacy_cols]
        updates = [
            f"{c}=EXCLUDED.{c}" for c in legacy_cols
            if c not in {"prediction_run_id", "instrument_id", "prediction_date", "timeframe"}
        ]
        row = self.database.fetch_one(
            f"INSERT INTO predictions ({', '.join(legacy_cols)}, created_at, updated_at) "
            f"VALUES ({', '.join(insert_placeholders)}, NOW(), NOW()) "
            "ON CONFLICT (prediction_run_id, instrument_id, prediction_date, timeframe) "
            f"DO UPDATE SET {', '.join(updates)}, updated_at=NOW() RETURNING *",
            params,
        )
        if row is None:
            raise RuntimeError("Prediction konnte nicht gespeichert werden")
        return self._from_row(row)

    def delete_for_run(self, prediction_run_id: int) -> int:
        return self.database.execute(
            "DELETE FROM predictions WHERE prediction_run_id=%s",
            (prediction_run_id,),
        )
