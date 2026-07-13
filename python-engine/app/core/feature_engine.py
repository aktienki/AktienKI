from app.features.feature_builder import FeatureBuilder

class FeatureEngine:

    def __init__(self):
        self.builder = FeatureBuilder()

    def build(self, prices, indicators):
        return self.builder.build(prices, indicators)
