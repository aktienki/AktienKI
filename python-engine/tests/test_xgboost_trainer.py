from pathlib import Path

import numpy as np
import pandas as pd

from app.training.xgboost_trainer import XGBoostTrainer


def test_trainer_creates_model_artifact(tmp_path: Path) -> None:
    rows = 400
    frame = pd.DataFrame(
        {
            "bar_time": pd.date_range(
                "2024-01-01",
                periods=rows,
                freq="D",
                tz="UTC",
            ),
            "close": np.linspace(100, 180, rows),
            "volume": np.linspace(1_000_000, 2_000_000, rows),
            "rsi_14": np.linspace(30, 70, rows),
            "ema_20": np.linspace(99, 179, rows),
            "ema_50": np.linspace(98, 178, rows),
            "ema_200": np.linspace(95, 175, rows),
            "macd": np.sin(np.linspace(0, 10, rows)),
            "atr_14": np.linspace(2, 4, rows),
            "volatility_20": np.linspace(15, 25, rows),
            "target": np.sin(np.linspace(0, 15, rows)) / 100,
        }
    )

    feature_names = [
        "close",
        "volume",
        "rsi_14",
        "ema_20",
        "ema_50",
        "ema_200",
        "macd",
        "atr_14",
        "volatility_20",
    ]

    result = XGBoostTrainer(tmp_path).train(
        frame,
        instrument_id=1,
        target_name="target_return_5d",
        feature_names=feature_names,
        parameters={"n_estimators": 20, "n_jobs": 1},
    )

    assert Path(result.artifact_path).exists()
    assert result.training_rows > result.test_rows
    assert "test" in result.metrics
