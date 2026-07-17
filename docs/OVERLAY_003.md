# Market Dashboard API

Die Zugriffsschicht trennt Datenaufbereitung von Livewire/Blade. Dashboard, mobile App und spätere externe API können dadurch dasselbe JSON-Modell verwenden.

`MarketDashboardService::latest()` lädt den neuesten Snapshot inklusive Assets, Sektoren und Statistik mit Eager Loading. `history()` begrenzt die Ausgabe auf maximal 365 Snapshots.
