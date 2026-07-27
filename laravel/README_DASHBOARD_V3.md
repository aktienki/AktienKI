# AktienKI Dashboard 3.0

Kompaktes Dashboard ohne eigene Sidebar im Dashboard-View. Die globale Sidebar bleibt im Layout.

## Enthalten

- Kompakte Marktkarten: DAX, EUR/USD, Dow Jones, Gold
- Kompakte KPI-Karten
- BUY/SELL Tabellen im Trading-Dashboard-Stil
- Rechte Info-Spalte mit letztem KI-Lauf und Konfidenzsignalen
- Keine doppelten Blade-Funktionen
- Keine `rank`-Sortierung auf `prediction_models`

## Nach dem Einspielen

```bash
php artisan optimize:clear
php artisan view:clear
```

Falls Livewire-Komponenten gecached sind:

```bash
php artisan livewire:discover
```
