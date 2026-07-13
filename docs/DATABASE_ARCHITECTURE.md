# AktienKI – endgültige Datenbankbasis

## Leitlinien

- Laravel ist Eigentümer der Datenbankstruktur.
- Python greift ausschließlich auf die durch Laravel erzeugten Tabellen zu.
- `instruments` vereinheitlicht Aktien, ETFs, Indizes und spätere Forex-Paare.
- `price_bars.interval` unterstützt unter anderem `1d` und `15m`.
- Aktienmodelle verwenden standardmäßig 10 Jahre Historie.
- Forex-Modelle können später mit 3 Jahren und 15-Minuten-Intervallen angelegt werden.
- Globale, Premium- und benutzerspezifische Modelle werden über `trained_models.scope` und `owner_user_id` abgebildet.
- Lange Python-Arbeiten laufen über `ml_jobs`; Laravel-Webrequests starten kein Training direkt.

## Module

1. Benutzer und Authentifizierung
2. Abonnements und Berechtigungen
3. Börsen, Instrumente und Indizes
4. Kurs- und Fundamentaldaten
5. Feature-Sets und Modelldefinitionen
6. ML-Jobsteuerung
7. Vorhersagen
8. Backtests
9. Watchlists und Portfolios
10. Alerts und Benachrichtigungen
11. Systemeinstellungen und Audit-Protokolle

## Wichtige Statuswerte

### ml_jobs.status
`pending`, `running`, `completed`, `failed`, `canceled`

### trained_models.scope
`global`, `premium`, `user`

### trained_models.status
`training`, `ready`, `failed`, `archived`

### instruments.asset_type
`stock`, `etf`, `index`, `forex`, `crypto`

### interval
`1d`, `1h`, `15m`

Diese Werte sollten später als PHP-Enums und Python-Enums gespiegelt werden.
