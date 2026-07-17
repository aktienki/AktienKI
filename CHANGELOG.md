# AktienKI Beta 0.8 – Overlay 003

## Neu
- Zentraler `MarketDashboardService`
- API `GET /api/v1/market/snapshot`
- API `GET /api/v1/market/history?limit=30`
- Konsistentes JSON für Marktkarten, Market Score, Risk Mode, Breadth und Sektoren
- Artisan-Diagnose `php artisan aktienki:market-status`
- Feature-Tests für leeren und vollständigen Snapshot

## Kompatibilität
Verwendet ausschließlich die bereits vorhandenen Tabellen und Eloquent-Models aus Overlay 001/002.
