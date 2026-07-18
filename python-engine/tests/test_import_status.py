from app.core.import_status import ImportStatus


def test_import_status_counts_and_serializes():
    status = ImportStatus(total=3)
    status.record_success(10)
    status.record_success(0)
    status.record_failure()
    status.finish()

    result = status.as_dict()

    assert result["total"] == 3
    assert result["completed"] == 2
    assert result["failed"] == 1
    assert result["empty"] == 1
    assert result["bars_written"] == 10
    assert result["duration_seconds"] is not None
