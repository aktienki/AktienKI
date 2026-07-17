# AktienKI Architektur

## Hauptkomponenten

- `laravel/`: Webanwendung, Benutzeroberfläche, API, Abonnements und Datenzugriff.
- `python-engine/`: Marktimport, Feature Engineering, Training, Prediction und Modellverwaltung.
- PostgreSQL: Gemeinsame persistente Datenbasis.

## Architekturregel

Python erzeugt und bewertet Marktdaten sowie Predictions. Laravel liest die persistierten Ergebnisse und stellt sie Nutzern bereit. Rechenintensive ML-Prozesse werden nicht im Webrequest ausgeführt.

## Releaseprinzip

Änderungen werden als nummerierte Overlay-ZIPs ausgeliefert. Jedes Overlay enthält Changelog, Installationsanleitung und nur neue oder geänderte Dateien.
