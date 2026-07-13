def test_short_return_is_positive_when_market_return_is_negative():
    current_price = 100.0
    predicted_price = 90.0

    price_difference = predicted_price - current_price
    market_return = price_difference / current_price
    long_return = market_return
    short_return = -market_return

    assert price_difference == -10.0
    assert market_return == -0.1
    assert long_return == -0.1
    assert short_return == 0.1
