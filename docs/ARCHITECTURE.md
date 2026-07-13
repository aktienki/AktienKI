# Zielarchitektur

```text
Browser
  -> Laravel / Livewire
      -> PostgreSQL
      -> ML-Auftrag erzeugen
          -> Python Worker
              -> Daten / Features / Training / Backtest
              -> Ergebnisse und Status in PostgreSQL
      -> Laravel zeigt Fortschritt und Ergebnisse
```

## Verantwortlichkeiten

### Laravel

- Authentifizierung und Benutzer
- Abos, Limits und Berechtigungen
- Marktseiten und Dashboard
- Trainings- und Analyseaufträge
- Datenbankschema und Migrationen
- Administration und Audit

### Python

- Marktdatenimport
- Feature Engineering
- Dataset-Erstellung
- Modelltraining
- Prediction
- Backtesting
- Modellartefakte und Metriken
- Worker-Ausführung

## Modellarten

- `global`: Plattformweites Standardmodell
- `premium`: Plattformweites Modell für Premium-Tarife
- `user`: Benutzerdefiniertes Modell mit verpflichtender `user_id`

## Zeithorizonte

- Aktie: `1d`, 10 Jahre
- Forex: `15m`, 3 Jahre

Diese Werte sind Konfiguration und keine hart codierten Datenbankannahmen.
