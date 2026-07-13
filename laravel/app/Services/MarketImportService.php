<?php

namespace App\Services;

class MarketImportService
{
    public function queueHistoryImport(int $instrumentId, string $interval='1d'): void
    {
        // Dispatch(new ImportInstrumentHistoryJob(...));
    }
}
