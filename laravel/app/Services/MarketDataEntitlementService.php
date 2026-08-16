<?php

namespace App\Services;

final class MarketDataEntitlementService
{
    public function historicalChartsAllowed(object $instrument): bool
    {
        $country = strtoupper(trim((string) ($instrument->country ?? '')));

        return ! in_array($country, config('aktienki.market_data.restricted_historical_chart_countries', []), true);
    }

    public function historicalChartRestrictionReason(object $instrument): ?string
    {
        if ($this->historicalChartsAllowed($instrument)) {
            return null;
        }

        return __('Historische Kurscharts sind für diesen Markt aufgrund der aktuellen Datenlizenz nicht verfügbar. Prognosen und KI-Auswertungen können weiterhin angezeigt werden.');
    }
}
