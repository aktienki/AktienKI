# AktienKI – verbindliche Modell-, Ranking-, Entry- und Exit-Prozedur

**Version:** 1.0  
**Stand:** 24.08.2026  
**Status:** Verbindliche Zielprozedur. Regeln mit Kennzeichnung `EXPERIMENTAL` erzeugen ausschließlich Hinweise und keine automatische Order.

## 1. Ziel und Grundsätze

Diese Prozedur ist die einzige fachliche Reihenfolge für Training, Validierung, Filterung, Ranking, Entry und Exit. Abweichungen benötigen eine neue Versionsnummer und einen dokumentierten Vergleichstest.

Unveränderliche Grundsätze:

1. Alle Trainings und Backtests sind point-in-time und frei von Zukunftsdaten.
2. Ein Trade entsteht nur bei einem echten Signalwechsel in `BUY`, nicht an jedem weiteren BUY-Tag.
3. Ein Phasen- oder globales Modell darf nur nach bestandenem Quality Gate verwendet werden.
4. Gibt es kein qualifiziertes Modell, lautet die Entscheidung `WAIT`.
5. Der KI-Score ist ein Filter nach der Modellprognose und kein Trainingsfeature.
6. Ein Prognosehorizont ist kein automatisches Ablaufdatum einer Position.
7. Kalibrierungs-, Validierungs- und Kontrollzeiträume bleiben strikt getrennt.
8. Rohwerte, Modellversion, Filterentscheidung und Datenstand bleiben vollständig auditierbar.
9. `EXPERIMENTAL` darf keine automatische Kauf- oder Verkaufsorder auslösen.

## 2. Horizontstrategie

Pflichtmodelle:

- `5T`: kurzfristiges Entry-Timing und schnelle Verschlechterung
- `20T`: zentrale Richtung, Marktphase und strategischer Ausblick

Optionale Modelle:

- `10T` und `15T` werden nur neu trainiert, wenn ein Walk-Forward-Ablationstest einen stabilen Zusatznutzen gegenüber 5T und 20T zeigt.
- Bereits vorhandene qualifizierte 10T-/15T-Modelle bleiben verwendbar.
- Nicht trainierte Horizonte dürfen in der Oberfläche nicht als echte Modellprognose dargestellt werden. Interpolation ist nur mit eindeutiger Kennzeichnung zulässig und darf kein Signal auslösen.

## 3. Statusmodell je Aktie

Zulässige fachliche Zustände:

```text
DISCOVERED
DATA_PENDING
STANDARD_TRAINING
STANDARD_TRAINED
WALK_FORWARD_PENDING
SCORE_FILTER_PENDING
SCORE_FILTER_ACTIVE
PHASE_TRAINING_PENDING
PHASE_OPTIMIZED
PHASE_REJECTED
QUALITY_GATE_FAILED
PRODUCTION_READY
RETRAINING_REQUIRED
```

Der Status muss mit Zeitstempel, Pipelineversion und Begründung persistiert werden. Ein Fehler darf nicht als erfolgreicher Abschluss erscheinen.

## 4. Datenvorbereitung

Vor jedem Training:

1. Instrument und Listing eindeutig auflösen; Dubletten über ISIN und Börsenlisting verknüpfen.
2. Kursdaten, Corporate Actions und Währung prüfen.
3. Trainings- und Bewertungswerte in einer einheitlichen Basiswährung behandeln, wenn Geldbeträge verglichen werden.
4. Fehlende oder nicht plausible Kurse sperren.
5. Indikatoren ausschließlich aus zum Zeitpunkt verfügbaren Kursen berechnen.
6. Markt-, Index- und Sektordaten zeitlich korrekt ausrichten.
7. Datenabdeckung, Lücken und letzte Aktualisierung protokollieren.

## 5. Standardtraining

Reihenfolge je Aktie:

```text
Datenprüfung
→ 5T-Standardmodell
→ 20T-Standardmodell
→ optional 10T/15T
→ Walk-Forward
→ Noise- und Stabilitätsberechnung
→ Modellartefakt speichern
→ Prüfsumme und Metadaten speichern
```

Aktualität wird im Training gewichtet. Die Gewichtung ist Teil der Modellversion und darf nicht nachträglich ohne erneuten Walk-Forward geändert werden.

## 6. Phasenmodelle und Routing

Das 20T-Modell wird zusätzlich für Marktphasen geprüft. Die Phasenbestimmung und die Expertenmodelle verwenden dieselbe point-in-time Informationsbasis.

Produktionsrouting:

```text
qualifiziertes Phasenmodell mit ausreichender Routing-Konfidenz
→ andernfalls qualifiziertes globales Aktienmodell
→ andernfalls WAIT
```

PyTorch darf die Phase bestimmen oder als Filter dienen, aber nicht ohne dokumentierten Test die Prognoserichtung überschreiben.

Eine Aktie erhält `PHASE_OPTIMIZED` nur, wenn das Phasenrouting im unabhängigen Vergleich stabilen Zusatznutzen gegenüber dem globalen Modell zeigt. Andernfalls erhält sie `PHASE_REJECTED`; das qualifizierte globale Modell bleibt Champion.

## 7. Filterreihenfolge

Filter werden in dieser Reihenfolge angewendet:

1. Datenqualitätsfilter
2. Modell-Quality-Gate
3. Noise- und Stabilitätsfilter
4. Marktphasenfilter beziehungsweise Routing
5. Indexfilter
6. Sektorfilter
7. Liquiditäts- und Ausführbarkeitsfilter
8. individueller KI-Score-Filter
9. persönliches Risikoprofil

Ein Filter muss `PASSED`, `BLOCKED`, `NOT_AVAILABLE` oder `NOT_APPLICABLE` speichern. Fehlende Filterdaten dürfen nicht stillschweigend als bestanden gelten.

## 8. Normierte Nutzerbewertungen

Qualitätswerte werden einheitlich dargestellt:

```text
1+ > 1− > 2+ > 2− > 3+ > 3− > 4+ > 4− > 5+ > 5−
```

`1+` ist immer die beste Qualität. Dies gilt für KI-Score, Konfidenz, Hit-Rate, Profitfaktor und Stabilität. Rohwerte bleiben im Tooltip und in Berichten sichtbar.

Risiko wird separat und ohne Plus/Minus dargestellt:

```text
1 = sehr niedrig
2 = niedrig
3 = mittel
4 = hoch
5 = sehr hoch
```

Die Normalisierung erfolgt hierarchisch und ausschließlich aus vergangenen Werten:

```text
Aktienhistorie mit ausreichender Abdeckung
→ ähnliche Aktien
→ Sektor
→ Markt
→ konservativer Sicherheitswert
```

## 9. Ranking

Das Ranking wird erst nach allen fachlichen Filtern berechnet. Ein hoher ungefilterter Modellscore darf keine gesperrte Aktie nach oben bringen.

Rankingbestandteile:

- normierter KI-Score
- Prognosequalität und Konfidenz
- Profitfaktor und Hit-Rate aus Walk-Forward
- Stabilität
- inverses Risiko
- Index- und Sektorbestätigung
- Aktualität der Modelle und Daten

Jeder Rankingwert speichert Komponenten, Gewichtung und Version. Ranking und Signal bleiben getrennte Größen.

## 10. Entry-Prozedur

Ein Entry ist nur zulässig, wenn:

1. ein echter Wechsel von `non-BUY` zu `BUY` vorliegt,
2. das gewählte Modell und alle Pflichtfilter bestanden sind,
3. die individuelle KI-Score-Schwelle erreicht ist,
4. bei unzureichender eigener Kalibrierung eine validierte hierarchische Schwelle verfügbar ist,
5. keine bereits offene Position desselben Instruments besteht,
6. persönliches Risiko- und Allokationslimit eingehalten werden.

Entry-Zonen:

- bevorzugt: Rating `1+` oder `1−`
- darunter nur bei explizit validierter individueller Schwelle
- keine belastbare Schwelle: `WAIT`

## 11. Exit-Prozedur

### 11.1 Grundlogik

Die Position wird täglich mit den neuesten vollständigen Prognosen neu bewertet. Der ursprüngliche Horizont beendet die Position nicht automatisch.

```text
HOLD
→ solange Rating, Risiko und Prognosebild tragen

WARNING
→ Rating 3− oder schlechter an einem Handelstag

EXIT
→ Rating 3− oder schlechter an zwei unterschiedlichen Handelstagen

IMMEDIATE EXIT RECOMMENDATION
→ validierter extremer Score-Einbruch oder technische Risikoregel
```

Eine wiederholte Verarbeitung derselben Prognose darf den Bestätigungszähler nicht erhöhen.

### 11.2 Technische Risikoregel

Aktueller Kandidat:

```text
Volatilität im oberen 20%-Quantil
UND ATR/Kurs im oberen 20%-Quantil
UND Schlusskurs unter EMA20
```

Diese Regel ist zunächst `EXPERIMENTAL`. Für Airbus reduzierte sie im Pilotfall einen Verlust von −17,91 % auf −2,85 %, besitzt aber noch zu wenige unabhängige Trades.

### 11.3 Mehrhorizont-Bestätigung

Die Revisionen von 5T, 10T, 15T und 20T dürfen eine Exit-Warnung bestätigen. Mindestens drei fallende Horizonte sind ein Warnmerkmal, jedoch erst nach einem vollständigen täglichen OOS-Test produktionsfähig.

### 11.4 Laufzeit

- Positionen dürfen über 5T, 10T, 15T oder 20T hinaus gehalten werden.
- Jede neue vollständige Tagesprognose erneuert den Ausblick rollierend.
- Eine Sicherheitsprüfung erfolgt spätestens nach 60 Handelstagen.
- Eine Verlängerung über 60T benötigt weiterhin ein qualifiziertes Modell und darf kein bestehendes Exit-Signal übergehen.

## 12. Exit-Kalibrierung und Peer-Fallback

Kalibrierungshierarchie:

```text
individuelle Aktie
→ maximal 25 ähnliche Aktien
→ Sektor
→ Markt
→ konservativer Sicherheits-Stopp
```

Ähnlichkeit wird anhand vergangener Werte bestimmt:

- Volatilität
- ATR/Kurs
- typischer Drawdown
- Beta und Marktkorrelation
- Liquidität
- Marktkapitalisierung
- Sektor als Zusatzmerkmal

Die getestete Aktie wird aus ihrer Peer-Kalibrierung ausgeschlossen. Die Auswahl erfolgt point-in-time.

Gewichtung eigener und fremder Evidenz:

```text
Gewicht Aktie = eigene abgeschlossene Trades / (eigene abgeschlossene Trades + 20)
```

## 13. Nachkalibrierung

Keine tägliche Schwellenoptimierung. Eine Neukalibrierung ist nur zulässig:

- nach Modellwechsel oder Retraining,
- nach mindestens 10 zusätzlichen abgeschlossenen Trades,
- bei erkanntem Modelldrift,
- andernfalls höchstens quartalsweise.

Jede neue Kalibrierung erhält Version, Prüfsumme, Stichprobe, Zeitraum und Kontrolltestergebnis. Die vorherige Version bleibt auditierbar.

## 14. Validierung und Freigabe

Mindestvergleich je Änderung:

1. globales Modell ohne Zusatzfilter
2. globales Modell mit KI-Score-Filter
3. Phasenmodell ohne KI-Score-Filter
4. Phasenmodell mit KI-Score-Filter
5. feste Exit-Basislinie
6. Score-Exit
7. Score- plus Risikoregel
8. Score- plus Risikoregel mit Peer-Fallback

Kennzahlen:

- echte Signalwechsel und Tradezahl
- Profitfaktor
- Netto-Rendite je Trade
- Medianrendite
- Trefferquote
- maximaler Drawdown
- Haltedauer
- Transaktionskosten
- Stabilität je Jahr und Marktphase

Freigabestufen:

- `< 5 Trades`: `INSUFFICIENT_DATA`
- `≥ 5 Trades`: `EXPERIMENTAL`
- `≥ 20 Trades` plus unabhängiger Kontrolltest: `PROVISIONAL`
- `≥ 30 Trades`, unabhängiger Kontrolltest und Jahresstabilität: `VALIDATED`

## 15. Bestehende und neue Aktien

Bestehende Aktien:

```text
vorhandene qualifizierte Modelle behalten
→ KI-Score nachträglich kalibrieren
→ Exit-Kandidat berechnen
→ PHASE_TRAINING_PENDING markieren
→ 20T-Phasenmodell priorisiert nachholen
```

Neue Aktien:

```text
Datenprüfung
→ 5T und 20T trainieren
→ Walk-Forward
→ Phasentest 20T
→ Noise/Index/Sektor/Liquidität
→ KI-Score- und Ratingkalibrierung
→ Entry- und Exit-Backtest
→ Quality Gate
→ Artefakt veröffentlichen
→ erste vollständige Prediction
```

## 16. Betrieb und Monitoring

Nach jedem Lauf müssen sichtbar sein:

- aktuelle Pipelinephase und Instrument
- erfolgreich/fehlgeschlagen/übersprungen
- Modell- und Filterstatus je Horizont
- veröffentlichte Artefakte
- vollständige Prediction vorhanden ja/nein
- Anzahl produktionsbereiter Aktien
- Grund für `WAIT`, `BLOCKED` oder `QUALITY_GATE_FAILED`

Scheduler und Worker dürfen denselben Instrument-Horizont nicht gleichzeitig bearbeiten. Fehlgeschlagene Schritte werden gezielt wiederholt; ein kompletter erfolgreicher Lauf wird nicht unnötig neu gestartet.

## 17. Änderungsverfahren

Eine Regeländerung benötigt:

1. neue Pipeline- oder Regelversion,
2. point-in-time Vergleich gegen die aktuelle Champion-Regel,
3. unveränderte Kontrollperiode,
4. dokumentierte Kennzahlen und Stichprobe,
5. explizite Freigabestufe,
6. reversiblen Rollback auf die vorherige Version.

Diese Prozedur ist die fachliche Referenz. Implementierung, Scheduler, Datenbankschema und Benutzeroberfläche müssen denselben Status und dieselbe Regelversion ausweisen.
