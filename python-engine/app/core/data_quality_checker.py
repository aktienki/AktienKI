from __future__ import annotations

from dataclasses import dataclass

import pandas as pd


@dataclass(frozen=True, slots=True)
class DataQualityReport:
    valid: bool
    rows_input: int
    rows_output: int
    duplicate_rows_removed: int
    invalid_rows_removed: int
    missing_columns: tuple[str, ...]


class DataQualityChecker:
    REQUIRED_COLUMNS = ("Open", "High", "Low", "Close")

    def validate(self, frame: pd.DataFrame | None) -> tuple[pd.DataFrame, DataQualityReport]:
        if frame is None:
            frame = pd.DataFrame()

        rows_input = len(frame)
        missing_columns = tuple(
            column
            for column in self.REQUIRED_COLUMNS
            if column not in frame.columns
        )

        if frame.empty or missing_columns:
            return pd.DataFrame(), DataQualityReport(
                valid=False,
                rows_input=rows_input,
                rows_output=0,
                duplicate_rows_removed=0,
                invalid_rows_removed=rows_input,
                missing_columns=missing_columns,
            )

        cleaned = frame.copy()
        cleaned.index = pd.to_datetime(cleaned.index, utc=True, errors="coerce")

        invalid_index = cleaned.index.isna()
        invalid_rows_removed = int(invalid_index.sum())
        if invalid_rows_removed:
            cleaned = cleaned.loc[~invalid_index]

        duplicates = cleaned.index.duplicated(keep="last")
        duplicate_rows_removed = int(duplicates.sum())
        if duplicate_rows_removed:
            cleaned = cleaned.loc[~duplicates]

        numeric_columns = [
            column
            for column in (*self.REQUIRED_COLUMNS, "Adj Close", "Volume")
            if column in cleaned.columns
        ]
        cleaned[numeric_columns] = cleaned[numeric_columns].apply(
            pd.to_numeric,
            errors="coerce",
        )

        before_required_drop = len(cleaned)
        cleaned = cleaned.dropna(subset=list(self.REQUIRED_COLUMNS))
        invalid_rows_removed += before_required_drop - len(cleaned)

        price_valid = (
            (cleaned["High"] >= cleaned["Low"])
            & (cleaned["High"] >= cleaned[["Open", "Close"]].max(axis=1))
            & (cleaned["Low"] <= cleaned[["Open", "Close"]].min(axis=1))
            & (cleaned[list(self.REQUIRED_COLUMNS)] > 0).all(axis=1)
        )
        invalid_rows_removed += int((~price_valid).sum())
        cleaned = cleaned.loc[price_valid]

        if "Volume" in cleaned.columns:
            negative_volume = cleaned["Volume"].notna() & (cleaned["Volume"] < 0)
            invalid_rows_removed += int(negative_volume.sum())
            cleaned = cleaned.loc[~negative_volume]

        cleaned = cleaned.sort_index()

        report = DataQualityReport(
            valid=not cleaned.empty,
            rows_input=rows_input,
            rows_output=len(cleaned),
            duplicate_rows_removed=duplicate_rows_removed,
            invalid_rows_removed=invalid_rows_removed,
            missing_columns=missing_columns,
        )
        return cleaned, report
