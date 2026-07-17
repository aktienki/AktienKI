from aktienki_engine.market.snapshot import build_snapshot
def test_snapshot_risk_on():
    assets=[{'symbol':'^GSPC','score':80,'weight':1},{'symbol':'^VIX','score':30,'weight':1,'price':15}]
    predictions=[{'signal':'BUY','sector':'Technology','prediction_score':80,'predicted_return':.02} for _ in range(8)]+[{'signal':'SELL','sector':'Energy','prediction_score':30,'predicted_return':-.01} for _ in range(2)]
    result=build_snapshot(assets,predictions)
    assert result['market_score']>60
    assert result['risk_mode']=='RISK_ON'
