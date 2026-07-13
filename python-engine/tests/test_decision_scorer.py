from app.scoring.decision_scorer import DecisionScorer


def test_short_signal_gets_positive_strength() -> None:
    result = DecisionScorer().score(
        market_return=-0.10,
        confidence=90,
        volatility=20,
        trend_strength=100,
    )

    assert result["direction_score"] < 0
    assert result["signal_strength"] > 0
    assert result["signal"] in {
        "sell",
        "strong_sell",
    }


def test_long_and_short_equal_moves_have_equal_strength() -> None:
    scorer = DecisionScorer()

    long_result = scorer.score(
        market_return=0.08,
        confidence=85,
        volatility=20,
        trend_strength=100,
    )

    short_result = scorer.score(
        market_return=-0.08,
        confidence=85,
        volatility=20,
        trend_strength=100,
    )

    assert long_result["signal_strength"] == short_result["signal_strength"]
    assert long_result["ai_score"] == short_result["ai_score"]
