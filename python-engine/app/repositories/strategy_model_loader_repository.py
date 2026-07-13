from __future__ import annotations

from sqlalchemy import text


class StrategyModelLoaderRepository:
    def __init__(self, session):
        self.session = session

    def latest_ready(
        self,
        *,
        strategy_code: str,
        instrument_id: int,
        algorithm: str,
    ) -> dict | None:
        row = self.session.execute(
            text(
                """
                SELECT
                    tm.id,
                    tm.artifact_path,
                    tm.metrics,
                    tm.feature_names,
                    tm.metadata,
                    md.algorithm,
                    md.target_name,
                    md.feature_version,
                    md.strategy_profile_id,
                    md.strategy_profile_version,
                    sp.code AS strategy_code,
                    sp.version AS current_strategy_version
                FROM trained_models tm
                INNER JOIN model_definitions md
                    ON md.id = tm.model_definition_id
                INNER JOIN strategy_profiles sp
                    ON sp.id = md.strategy_profile_id
                WHERE tm.instrument_id = :instrument_id
                  AND tm.status = 'ready'
                  AND tm.deleted_at IS NULL
                  AND md.algorithm = :algorithm
                  AND sp.code = :strategy_code
                  AND sp.deleted_at IS NULL
                ORDER BY tm.trained_at DESC NULLS LAST, tm.id DESC
                LIMIT 1
                """
            ),
            {
                "strategy_code": strategy_code,
                "instrument_id": instrument_id,
                "algorithm": algorithm,
            },
        ).mappings().first()

        return None if row is None else dict(row)
