# AktienKI Laravel Complete Dashboard V3 Release

Dies ist ein vollständiges Laravel-Projektpaket auf Basis deines hochgeladenen Projekts.

## Wichtig

Deine bestehende `.env` wurde bewusst nicht in dieses Paket übernommen. Kopiere deine alte `.env` in den neuen Laravel-Ordner.

## Installation / Austausch auf dem Mac

Empfohlen:

```bash
cd /Users/silviotaubert/AktienKI-v3
mv laravel laravel_backup_dashboard_alt
unzip ~/Downloads/aktienki_laravel_complete_dashboard_v3_release.zip
mv aktienki_laravel_complete_dashboard_v3 laravel
cp laravel_backup_dashboard_alt/.env laravel/.env
cd laravel
composer install
npm install
npm run build
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

Wenn `vendor/` und `node_modules/` bereits im Backup vorhanden sind, kannst du sie auch kopieren. Sauberer ist aber `composer install` und `npm install`.

## Enthalten

- Dashboard 3.0 ohne zusätzliche Sidebar im Dashboard
- Bestehende globale Sidebar bleibt im Layout
- Kompakte Markt-Karten
- KPI Cards
- Top BUY / SELL Signale
- High Confidence
- High Risk
- Latest Prediction Run
- Zentraler `DashboardFormatter`, keine doppelten Blade-Funktionen
- Bereinigter `DashboardService` ohne `prediction_models.rank`
- Logo unter `public/images/aktienki-logo.png`

## Nach dem Einspielen

```bash
php artisan optimize:clear
php artisan view:clear
```
