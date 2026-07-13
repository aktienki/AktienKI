def test_short_validation_produces_positive_strategy_return():
    current_price = 100.0
    actual_price = 90.0
    strategy = "short"

    actual_market_return = (
        actual_price - current_price
    ) / current_price

    actual_long_return = actual_market_return
    actual_short_return = -actual_market_return

    actual_strategy_return = (
        actual_short_return
        if strategy == "short"
        else actual_long_return
    )

    assert actual_market_return == -0.1
    assert actual_short_return == 0.1
    assert actual_strategy_return == 0.1
