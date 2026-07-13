from app.experiments.variant_generator import VariantGenerator


def test_variant_generator_creates_expected_count():
    variants = VariantGenerator().generate(
        base_configuration={
            "technical_features": {},
            "cross_asset_features": {},
        },
        search_space={
            "ema_sets": [[10, 20], [20, 50]],
            "sma_sets": [[20]],
            "rsi_sets": [[14], [9, 21]],
            "cross_asset_sets": [[], ["nasdaq"]],
        },
    )

    assert len(variants) == 8
    assert variants[0]["variant_code"] == "variant-001"
