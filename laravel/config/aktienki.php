<?php

return [
    'champion' => [
        'minimum_validated_predictions' => env(
            'AKTIENKI_MIN_VALIDATED_PREDICTIONS',
            1000
        ),

        'minimum_selection_score_difference' => env(
            'AKTIENKI_MIN_SELECTION_SCORE_DIFFERENCE',
            0.02
        ),

        'minimum_direction_accuracy_difference' => env(
            'AKTIENKI_MIN_DIRECTION_ACCURACY_DIFFERENCE',
            0.01
        ),

        'minimum_strategy_return_difference' => env(
            'AKTIENKI_MIN_STRATEGY_RETURN_DIFFERENCE',
            0.005
        ),

        'elo_k_factor' => env(
            'AKTIENKI_ELO_K_FACTOR',
            32
        ),
    ],
];
