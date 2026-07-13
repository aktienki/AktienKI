# AktienKI v1

Saubere, modular erweiterbare Grundstruktur für Laravel, PostgreSQL und eine separate Python-ML-Engine.

## Architektur

- `laravel/`: Webanwendung, Benutzer, Abos, UI, API, Rechte und Job-Erstellung
- `python-engine/`: Datenimport, Features, Training, Predictions, Backtests und Worker
- `infrastructure/`: Docker, Nginx, PostgreSQL und Systemd
- `docs/`: Architektur- und Datenbankdokumentation
- `storage/`: Modelle, Exporte, Importe, Logs und Backups

## Feste Projektvorgaben

- Aktien: standardmäßig 10 Jahre Tageshistorie
- Forex: vorbereitet für 3 Jahre Historie mit 15-Minuten-Intervall
- Premium-Nutzer: eigene benutzerspezifische Modelle
- Laravel besitzt das Datenbankschema
- Python greift ausschließlich auf das von Laravel definierte Schema zu
- Lange ML-Prozesse laufen nie innerhalb eines Webrequests

## Start

1. Im Ordner `laravel/` ein neues Laravel-13-Projekt installieren.
2. Die vorbereiteten Dateien aus `laravel-template/` in das Laravel-Projekt übernehmen.
3. `.env.example` nach `.env` kopieren und PostgreSQL-Zugangsdaten eintragen.
4. Python-Umgebung in `python-engine/` installieren.
5. Datenbankmigrationen in Laravel ausführen.
6. Python-Worker starten.

Details stehen in `docs/SETUP.md`.
# AktienKI
