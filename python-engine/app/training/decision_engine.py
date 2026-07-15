from __future__ import annotations


class DecisionEngine:

    BUY = "BUY"

    HOLD = "HOLD"

    SELL = "SELL"

    @classmethod
    def decide(

        cls,

        prediction,

    ):

        probability = prediction["probability"]

        confidence = prediction["confidence"]

        expected_return = prediction["return"]

        if (

            probability > 0.70

            and

            confidence > 0.70

            and

            expected_return > 0

        ):

            return cls.BUY

        if (

            probability < 0.30

            and

            confidence > 0.70

        ):

            return cls.SELL

        return cls.HOLD