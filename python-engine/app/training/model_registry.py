from __future__ import annotations

from app.training.model_monitor import (

    ModelMonitor,

)


class RuntimeModelRegistry:

    """
    Registry aller aktuell trainierten Modelle.

    Diese Klasse wird später vom Dashboard,

    Newsletter,

    API

    und AKI-NEXUS benutzt.
    """

    def __init__(self):

        self.monitor = ModelMonitor()

        self.models = {}

    # -----------------------------------------------------

    def register(

        self,

        alias,

        algorithm,

        version,

        metrics,

        feature_count,

        trained_at,

    ):

        self.models[alias] = (

            self.monitor.evaluate(

                alias,

                algorithm,

                version,

                metrics,

                feature_count,

                trained_at,

            )

        )

    # -----------------------------------------------------

    def get(

        self,

        alias,

    ):

        return self.models.get(

            alias,

        )

    # -----------------------------------------------------

    def all(self):

        return self.models

    # -----------------------------------------------------

    def online(self):

        return [

            model

            for model in self.models.values()

            if model.status == "ONLINE"

        ]


runtime_registry = RuntimeModelRegistry()