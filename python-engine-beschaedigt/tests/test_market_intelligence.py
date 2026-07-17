from app.market_intelligence.analyzer import MarketIntelligenceAnalyzer


def test_bullish_market_is_risk_on():
    predictions = [
        {"signal": "BUY", "ai_score": 80, "confidence": 0.8, "market_return_5d": 0.05, "sector": "Technology"},
        {"signal": "BUY", "ai_score": 75, "confidence": 0.7, "market_return_5d": 0.03, "sector": "Technology"},
        {"signal": "HOLD", "ai_score": 60, "confidence": 0.6, "market_return_5d": 0.01, "sector": "Industrials"},
    ]
    assets = [{"symbol": "^VIX", "price": 16.0, "change_percent": -2.0}]
    result = MarketIntelligenceAnalyzer().analyze(predictions, assets)
    assert result.market_score >= 62
    assert result.risk_mode == "RISK_ON"
    assert result.market_trend == "BULLISH"
    assert result.buy_count == 2


def test_high_vix_forces_risk_off():
    predictions = [
        {"signal": "BUY", "ai_score": 65, "confidence": 0.65, "sector": "Technology"},
        {"signal": "SELL", "ai_score": 45, "confidence": 0.60, "sector": "Finance"},
    ]
    assets = [{"symbol": "^VIX", "price": 35.0, "change_percent": 8.0}]
    result = MarketIntelligenceAnalyzer().analyze(predictions, assets)
    assert result.risk_mode == "RISK_OFF"
    assert result.volatility == 35.0


def test_sector_ranking_is_deterministic():
    predictions = [
        {"signal": "BUY", "ai_score": 80, "sector": "Technology"},
        {"signal": "SELL", "ai_score": 30, "sector": "Finance"},
    ]
    result = MarketIntelligenceAnalyzer().analyze(predictions)
    assert result.sectors[0]["sector"] == "Technology"
    assert result.sectors[0]["rank"] == 1
