# Changelog – Paket 3.1 Dashboard Core

## 1.0.0

### Added
- DashboardService mit Cache und zentraler Datenaggregation
- Accessors im Prediction Model für Dashboard-Anzeige
- Erweiterte KPI-Karten
- Erweiterte Prediction-Karten

### Changed
- Dashboard-Statistiken basieren jetzt auf der neuesten Prediction-Runde
- Confidence-Werte werden robust als Prozentwerte behandelt
- Marktanalyse erkennt bullish, bearish und neutral

### Fixed
- Fehlende Methoden im `DashboardService`, die von Livewire-Komponenten bereits aufgerufen wurden
- Fehlende Anzeigeattribute `ai_score` und `direction` bei Predictions
