import pytest

from app.core.retry_handler import RetryHandler


def test_retry_handler_retries_until_success():
    attempts = {"count": 0}

    def operation():
        attempts["count"] += 1
        if attempts["count"] < 3:
            raise RuntimeError("temporary")
        return "ok"

    handler = RetryHandler(max_attempts=3, base_delay_seconds=0)

    assert handler.run(operation) == "ok"
    assert attempts["count"] == 3


def test_retry_handler_raises_after_last_attempt():
    handler = RetryHandler(max_attempts=2, base_delay_seconds=0)

    with pytest.raises(RuntimeError, match="permanent"):
        handler.run(lambda: (_ for _ in ()).throw(RuntimeError("permanent")))
