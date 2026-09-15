<?php

namespace App\Services;

use App\Models\LocalSignalTransition;
use Illuminate\Support\Facades\DB;

/**
 * Detects and records daily signal changes for the local, server-scheduled
 * prediction pipeline. Called once per instrument from
 * ApplyHorizonFusion::handle() right after that instrument's fused signal
 * is written to predictions.signal - this is the one place a single,
 * final daily signal per instrument actually exists, so it is the only
 * reliable place to detect a real change from the last one.
 *
 * Deliberately independent of the external "serving" system's
 * serving_signal_transitions table, which has no scheduled job on this
 * server and has never recorded a row.
 */
class SignalTransitionRecorder
{
    /**
     * @return bool true if a real transition was recorded (false for a
     *              first-time baseline seed or an unchanged signal).
     */
    public function record(int $instrumentId, string $signal, ?float $score = null, ?float $risk = null): bool
    {
        $signal = strtoupper($signal);

        $previous = DB::table('local_signal_transitions')
            ->where('instrument_id', $instrumentId)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->value('to_signal');

        if ($previous === $signal) {
            // No change since the last recorded state - nothing to log.
            return false;
        }

        LocalSignalTransition::create([
            'instrument_id' => $instrumentId,
            'from_signal' => $previous, // null on the instrument's first-ever row
            'to_signal' => $signal,
            'changed_at' => now(),
            'score_at_change' => $score,
            'risk_at_change' => $risk,
        ]);

        return $previous !== null;
    }
}
