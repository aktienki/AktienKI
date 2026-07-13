from __future__ import annotations

import hashlib
import itertools
import json
from copy import deepcopy


class VariantGenerator:
    def generate(
        self,
        *,
        base_configuration: dict,
        search_space: dict,
    ) -> list[dict]:
        ema_sets = search_space.get("ema_sets", [None])
        sma_sets = search_space.get("sma_sets", [None])
        rsi_sets = search_space.get("rsi_sets", [None])
        cross_asset_sets = search_space.get(
            "cross_asset_sets",
            [None],
        )

        variants: list[dict] = []

        combinations = itertools.product(
            ema_sets,
            sma_sets,
            rsi_sets,
            cross_asset_sets,
        )

        for index, combination in enumerate(combinations, start=1):
            ema_set, sma_set, rsi_set, cross_asset_set = combination

            resolved = deepcopy(base_configuration)
            technical = resolved.setdefault(
                "technical_features",
                {},
            )

            if ema_set is not None:
                technical["ema_periods"] = list(ema_set)

            if sma_set is not None:
                technical["sma_periods"] = list(sma_set)

            if rsi_set is not None:
                technical["rsi_periods"] = list(rsi_set)

            cross_asset = resolved.setdefault(
                "cross_asset_features",
                {},
            )

            if cross_asset_set is not None:
                cross_asset["enabled"] = bool(cross_asset_set)
                cross_asset["aliases"] = list(cross_asset_set)

            serialized = json.dumps(
                resolved,
                sort_keys=True,
                separators=(",", ":"),
            )

            variants.append(
                {
                    "variant_code": f"variant-{index:03d}",
                    "configuration": resolved,
                    "configuration_hash": hashlib.sha256(
                        serialized.encode("utf-8")
                    ).hexdigest(),
                }
            )

        return variants
