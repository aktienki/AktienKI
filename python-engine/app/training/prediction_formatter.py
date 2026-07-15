from __future__ import annotations


class PredictionFormatter:

    @staticmethod
    def create(

        prediction,

    ):

        return {

            "direction":

                prediction["direction"],

            "expected_return":

                round(

                    prediction["return"] * 100,

                    2,

                ),

            "probability":

                round(

                    prediction["probability"] * 100,

                    1,

                ),

            "confidence":

                round(

                    prediction["confidence"] * 100,

                    1,

                ),

            "max_gain":

                round(

                    prediction["max_gain"] * 100,

                    2,

                ),

            "max_loss":

                round(

                    prediction["max_loss"] * 100,

                    2,

                ),

            "risk_reward":

                round(

                    prediction["risk_reward"],

                    2,

                ),

        }