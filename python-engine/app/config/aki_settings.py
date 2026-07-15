from app.enums.timeframe import TimeFrame


AKI_SETTINGS = {

    #
    # Short-Term AI
    #

    "AKI-PULSE": {

        "timeframe": TimeFrame.H1,

        "training_years": 3,

        "forecast_hours": 24,

        "retrain_days": 7,

        "ensemble": True,

    },

    #
    # Long-Term AI
    #

    "AKI-HORIZON": {

        "timeframe": TimeFrame.D1,

        "training_years": 10,

        "forecast_days": 20,

        "retrain_days": 7,

        "ensemble": True,

    },

    #
    # Market AI
    #

    "AKI-CLIMATE": {

        "timeframe": TimeFrame.D1,

        "training_years": 10,

        "forecast_days": 20,

        "ensemble": True,

    },

}