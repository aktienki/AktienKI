<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Resolves the read-optimized current-signal relation when it is installed.
 *
 * Older/local serving schemas keep working through the canonical live view.
 */
final class ServingCurrentSignalSource
{
    private static ?string $resolvedRelation = null;

    public static function relation(): string
    {
        if (self::$resolvedRelation !== null) {
            return self::$resolvedRelation;
        }

        try {
            $available = DB::connection('serving')->table('pg_matviews')
                ->where('schemaname', 'public')
                ->where('matviewname', 'serving_current_stock_signals_materialized')
                ->exists();
        } catch (Throwable) {
            $available = false;
        }

        return self::$resolvedRelation = $available
            ? 'serving_current_stock_signals_materialized'
            : 'serving_current_stock_signals';
    }
}
