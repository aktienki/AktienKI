from __future__ import annotations

from sqlalchemy import text


class TrainedModelLoaderRepository:
    def __init__(self, session):
        self.session = session

    def latest_ready(
        self,
        *,
        instrument_id: int,
        algorithm: str,
        target_name: str,
    ) -> dict | None:
        row = self.session.execute(
            text(
                """
                SELECT
                    tm.id,
                    tm.artifact_path,
                    tm.metrics,
                    tm.feature_names,
                    md.algorithm,
                    md.target_name,
                    md.feature_version
                FROM trained_models tm
                INNER JOIN model_definitions md
                    ON md.id = tm.model_definition_id
                WHERE tm.instrument_id = :instrument_id
                  AND tm.status = 'ready'
                  AND md.algorithm = :algorithm
                  AND md.target_name = :target_name
                  AND tm.deleted_at IS NULL
                ORDER BY tm.trained_at DESC NULLS LAST, tm.id DESC
                LIMIT 1
                """
            ),
            {
                "instrument_id": instrument_id,
                "algorithm": algorithm,
                "target_name": target_name,
            },
        ).mappings().first()

        if row is None:
            return None

        return dict(row)
