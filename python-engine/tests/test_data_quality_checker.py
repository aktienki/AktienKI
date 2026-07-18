import pandas as pd

from app.core.data_quality_checker import DataQualityChecker


def test_quality_checker_removes_duplicates_and_invalid_prices():
    frame = pd.DataFrame(
        {
            "Open": [100, 101, 100],
            "High": [105, 106, 90],
            "Low": [99, 100, 95],
            "Close": [104, 105, 98],
            "Volume": [1000, 1100, 1200],
        },
        index=pd.DatetimeIndex([
            "2026-07-01",
            "2026-07-01",
            "2026-07-02",
        ]),
    )

    cleaned, report = DataQualityChecker().validate(frame)

    assert len(cleaned) == 1
    assert report.valid is True
    assert report.duplicate_rows_removed == 1
    assert report.invalid_rows_removed == 1


def test_quality_checker_rejects_missing_columns():
    frame = pd.DataFrame(
        {"Open": [100], "Close": [101]},
        index=pd.DatetimeIndex(["2026-07-01"]),
    )

    cleaned, report = DataQualityChecker().validate(frame)

    assert cleaned.empty
    assert report.valid is False
    assert set(report.missing_columns) == {"High", "Low"}
