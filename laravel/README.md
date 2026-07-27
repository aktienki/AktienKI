# AktienKI Laravel Dashboard V2

Dieses Paket enthält Dateien für ein neues Dashboard, das echte Daten aus PostgreSQL nutzt.

## Enthalten

- `app/Services/DashboardService.php`
- `app/Livewire/Dashboard/KpiOverview.php`
- `app/Livewire/Dashboard/TopBuySignals.php`
- `app/Livewire/Dashboard/TopSellSignals.php`
- `app/Livewire/Dashboard/HighConfidenceSignals.php`
- `app/Livewire/Dashboard/HighRiskSignals.php`
- `app/Livewire/Dashboard/LatestPredictionRun.php`
- Blade Views für alle Komponenten
- neue `resources/views/dashboard.blade.php`

## Installation

Kopiere die Dateien in dein Laravel-Projekt.

Danach:

```bash
php artisan optimize:clear
php artisan view:clear
```

Dann `/dashboard` öffnen.

## Wichtig

Es wird keine neue Migration benötigt.
