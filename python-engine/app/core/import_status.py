from __future__ import annotations

from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone


@dataclass(slots=True)
class ImportStatus:
    total: int
    completed: int = 0
    failed: int = 0
    empty: int = 0
    bars_written: int = 0
    started_at: datetime = field(
        default_factory=lambda: datetime.now(timezone.utc)
    )
    finished_at: datetime | None = None

    def record_success(self, bars_written: int) -> None:
        self.completed += 1
        self.bars_written += bars_written
        if bars_written == 0:
            self.empty += 1

    def record_failure(self) -> None:
        self.failed += 1

    def finish(self) -> None:
        self.finished_at = datetime.now(timezone.utc)

    def as_dict(self) -> dict[str, object]:
        payload = asdict(self)
        payload["duration_seconds"] = self.duration_seconds
        return payload

    @property
    def duration_seconds(self) -> float | None:
        if self.finished_at is None:
            return None
        return max(
            0.0,
            (self.finished_at - self.started_at).total_seconds(),
        )
