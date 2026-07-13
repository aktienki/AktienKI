import pandas as pd
from sqlalchemy import text


class FeatureInputRepository:
    def __init__(self, session):
        self.session = session

    def load(
        self,
        *,
        instrument_id: int,
        interval: str,
    ) -> pd.DataFrame:
        rows = self.session.execute(
            text(
                """
                SELECT
                    pb.bar_time,
                    pb.close,
                    pb.volume,
                    ti.rsi_14,
                    ti.ema_20,
                    ti.ema_50,
                    ti.ema_200,
                    ti.macd,
                    ti.atr_14,
                    ti.volatility_20
                FROM price_bars pb
                INNER JOIN technical_indicators ti
                    ON ti.instrument_id = pb.instrument_id
                   AND ti.interval = pb.interval
                   AND ti.bar_time = pb.bar_time
                WHERE pb.instrument_id = :instrument_id
                  AND pb.interval = :interval
                ORDER BY pb.bar_time
                """
            ),
            {
                "instrument_id": instrument_id,
                "interval": interval,
            },
        ).mappings().all()

        if not rows:
            return pd.DataFrame()

        frame = pd.DataFrame(rows)
        frame["bar_time"] = pd.to_datetime(
            frame["bar_time"],
            utc=True,
        )
        frame = frame.set_index("bar_time")

        for column in frame.columns:
            frame[column] = pd.to_numeric(
                frame[column],
                errors="coerce",
            )

        return frame
