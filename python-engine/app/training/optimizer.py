from __future__ import annotations

from dataclasses import dataclass
from typing import Sequence

from app.training.selection_score import SelectionResult


@dataclass(frozen=True, slots=True)
class OptimizerResult:
    best: SelectionResult
    best_index: int
    ranking: tuple[SelectionResult, ...]


class SelectionOptimizer:
    """
    Wählt automatisch das beste Modell anhand des Selection Score.
    """

    def select_best(
        self,
        candidates: Sequence[SelectionResult],
    ) -> OptimizerResult:

        if not candidates:
            raise ValueError("Keine Kandidaten vorhanden.")

        ranking = tuple(
            sorted(
                candidates,
                key=lambda candidate: candidate.score,
                reverse=True,
            )
        )

        best = ranking[0]

        best_index = next(
            index
            for index, candidate in enumerate(candidates)
            if candidate is best
        )

        return OptimizerResult(
            best=best,
            best_index=best_index,
            ranking=ranking,
        )

    def rank(
        self,
        candidates: Sequence[SelectionResult],
    ) -> list[SelectionResult]:

        return sorted(
            candidates,
            key=lambda candidate: candidate.score,
            reverse=True,
        )