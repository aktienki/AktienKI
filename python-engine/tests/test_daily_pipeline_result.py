from app.core.daily_pipeline_engine import PipelineStepResult


def test_pipeline_step_result_serializes() -> None:
    result = PipelineStepResult(
        name="import_market",
        status="completed",
        result={"bars_written": 5},
    ).to_dict()

    assert result["name"] == "import_market"
    assert result["status"] == "completed"
    assert result["result"]["bars_written"] == 5
