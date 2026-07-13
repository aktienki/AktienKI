from __future__ import annotations

from copy import deepcopy

from app.experiments.selector import ExperimentSelector
from app.experiments.variant_generator import VariantGenerator
from app.repositories.strategy_experiment_repository import (
    StrategyExperimentRepository,
)
from app.repositories.strategy_profile_repository import (
    StrategyProfileRepository,
)
from app.core.strategy_training_engine import StrategyTrainingEngine


class StrategyExperimentEngine:
    def __init__(
        self,
        session_factory,
        *,
        storage_path,
    ):
        self.session_factory = session_factory
        self.storage_path = storage_path
        self.generator = VariantGenerator()
        self.selector = ExperimentSelector()

    def run(
        self,
        *,
        strategy_code: str,
        instrument_id: int,
        search_space: dict,
        algorithms: list[str],
        name: str | None = None,
        selection_rules: dict | None = None,
        max_variants: int = 50,
    ) -> dict:
        selection_rules = selection_rules or {}

        with self.session_factory() as session:
            strategy = StrategyProfileRepository(
                session
            ).get_by_code(strategy_code)

        if strategy is None:
            raise RuntimeError(
                f"Strategy Profile '{strategy_code}' wurde nicht gefunden."
            )

        variants = self.generator.generate(
            base_configuration=strategy.configuration,
            search_space=search_space,
        )

        if len(variants) > max_variants:
            raise ValueError(
                f"Experiment erzeugt {len(variants)} Varianten. "
                f"Erlaubt sind maximal {max_variants}."
            )

        with self.session_factory() as session:
            repository = StrategyExperimentRepository(session)

            experiment_id, public_id = repository.create_experiment(
                strategy_profile_id=strategy.id,
                instrument_id=instrument_id,
                name=name or f"{strategy.name} Experiment",
                search_space=search_space,
                algorithms=algorithms,
                selection_rules=selection_rules,
                variants_total=len(variants),
            )

            created_variants = []

            for variant in variants:
                variant_id = repository.create_variant(
                    experiment_id=experiment_id,
                    variant_code=variant["variant_code"],
                    resolved_configuration=variant["configuration"],
                    configuration_hash=variant[
                        "configuration_hash"
                    ],
                )

                created_variants.append(
                    {
                        **variant,
                        "variant_id": variant_id,
                    }
                )

            session.commit()

        completed = 0
        failed = 0
        result_rows = []

        for variant in created_variants:
            with self.session_factory() as session:
                repository = StrategyExperimentRepository(session)
                repository.start_variant(variant["variant_id"])
                session.commit()

            original_configuration = deepcopy(
                strategy.configuration
            )

            try:
                strategy.configuration.clear()
                strategy.configuration.update(
                    variant["configuration"]
                )

                for algorithm in algorithms:
                    training_engine = StrategyTrainingEngine(
                        self.session_factory,
                        storage_path=self.storage_path,
                    )

                    result = training_engine.train(
                        strategy_code=strategy_code,
                        instrument_id=instrument_id,
                        algorithm=algorithm,
                        configuration_override=(
                            variant["configuration"]
                        ),
                    )

                    scores = self.selector.score(
                        validation_metrics=result["metrics"][
                            "validation"
                        ],
                        test_metrics=result["metrics"]["test"],
                        rules=selection_rules,
                    )

                    with self.session_factory() as session:
                        repository = StrategyExperimentRepository(
                            session
                        )

                        repository.save_result(
                            variant_id=variant["variant_id"],
                            trained_model_id=result[
                                "trained_model_id"
                            ],
                            algorithm=algorithm,
                            metrics=result["metrics"],
                            stability_score=scores[
                                "stability_score"
                            ],
                            selection_score=scores[
                                "selection_score"
                            ],
                            metadata={
                                "strategy_code": strategy_code,
                                "variant_code": variant[
                                    "variant_code"
                                ],
                                "feature_count": result[
                                    "feature_count"
                                ],
                                "feature_names": result[
                                    "feature_names"
                                ],
                            },
                        )
                        session.commit()

                    result_rows.append(
                        {
                            "variant_code": variant[
                                "variant_code"
                            ],
                            "algorithm": algorithm,
                            "trained_model_id": result[
                                "trained_model_id"
                            ],
                            **scores,
                            "metrics": result["metrics"],
                        }
                    )

                with self.session_factory() as session:
                    repository = StrategyExperimentRepository(
                        session
                    )
                    repository.complete_variant(
                        variant["variant_id"]
                    )
                    session.commit()

                completed += 1

            except Exception as exception:
                with self.session_factory() as session:
                    repository = StrategyExperimentRepository(
                        session
                    )
                    repository.fail_variant(
                        variant_id=variant["variant_id"],
                        error_message=str(exception),
                    )
                    session.commit()

                failed += 1

            finally:
                strategy.configuration.clear()
                strategy.configuration.update(
                    original_configuration
                )

        with self.session_factory() as session:
            repository = StrategyExperimentRepository(session)
            repository.finish_experiment(
                experiment_id=experiment_id,
                completed=completed,
                failed=failed,
            )
            session.commit()

        ranking = sorted(
            result_rows,
            key=lambda item: item["selection_score"],
            reverse=True,
        )

        return {
            "experiment_id": public_id,
            "strategy_code": strategy_code,
            "instrument_id": instrument_id,
            "variants_total": len(variants),
            "variants_completed": completed,
            "variants_failed": failed,
            "algorithms": algorithms,
            "champion": ranking[0] if ranking else None,
            "ranking": ranking,
        }
