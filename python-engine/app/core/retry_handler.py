from __future__ import annotations

import logging
import time
from collections.abc import Callable
from typing import TypeVar

logger = logging.getLogger(__name__)

T = TypeVar("T")


class RetryHandler:
    def __init__(
        self,
        *,
        max_attempts: int = 3,
        base_delay_seconds: float = 1.0,
        backoff_factor: float = 2.0,
        exceptions: tuple[type[Exception], ...] = (Exception,),
    ) -> None:
        if max_attempts < 1:
            raise ValueError("max_attempts must be at least 1")
        if base_delay_seconds < 0:
            raise ValueError("base_delay_seconds must not be negative")
        if backoff_factor < 1:
            raise ValueError("backoff_factor must be at least 1")

        self.max_attempts = max_attempts
        self.base_delay_seconds = base_delay_seconds
        self.backoff_factor = backoff_factor
        self.exceptions = exceptions

    def run(self, operation: Callable[[], T], *, label: str = "operation") -> T:
        delay = self.base_delay_seconds

        for attempt in range(1, self.max_attempts + 1):
            try:
                return operation()
            except self.exceptions:
                if attempt >= self.max_attempts:
                    raise

                logger.warning(
                    "%s fehlgeschlagen, Versuch %d/%d in %.2f Sekunden",
                    label,
                    attempt,
                    self.max_attempts,
                    delay,
                    exc_info=True,
                )
                if delay > 0:
                    time.sleep(delay)
                delay *= self.backoff_factor

        raise RuntimeError("retry loop ended unexpectedly")
