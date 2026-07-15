from app.enums.model_scope import ModelScope

from app.features.aki_pulse_features import AKIPulseFeatures
from app.features.aki_horizon_features import AKIHorizonFeatures


class FeatureFactory:

    _FEATURES = {

        ModelScope.SHORT_TERM: AKIPulseFeatures(),

        ModelScope.LONG_TERM: AKIHorizonFeatures(),

        ModelScope.MARKET: AKIHorizonFeatures(),

    }

    @classmethod
    def create(cls, scope):

        if scope not in cls._FEATURES:

            raise RuntimeError(
                f"No feature builder registered for {scope}"
            )

        return cls._FEATURES[scope]
    