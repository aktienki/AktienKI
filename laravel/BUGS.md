# Bug- und Aufgabenliste (Stand 2026-09-13)

Gesammelt aus der laufenden Session. Status: ✅ behoben · 🟡 teilweise/mitigiert · 🔴 offen · ⏸️ wartet auf Input.

## 🔴 Offen — Datenqualität (extern, Serving-Pipeline)

1. **`serving_predictions` liefert doppelte, widersprüchliche Batches.**
   Für denselben `instrument_id`+`horizon`+`variant`+`as_of` existieren mehrere `batch_id`s mit stark abweichenden Werten. Betroffene Zeilen haben eine Konfidenz von exakt `1.0` (echte Werte sind nie so glatt, z.B. `0.6875`) zusammen mit einer ~8x überhöhten `expected_return`. **636 Zeilen bei 178 von ~522 Aktien betroffen** (Stichprobe 2026-09-13).
   In `ServingScreenerService::data()` wird das jetzt herausgefiltert (`->reject(confidence >= 0.999)`), das behebt nur die Symptome in der App — die Ursache liegt in der externen Modell-Pipeline und sollte dort untersucht werden.
   *Datei:* `app/Services/ServingScreenerService.php` (Filter vorhanden, Zeile ~194)

2. **Score-Donuts vs. Composite-Score können optisch auseinanderlaufen.**
   Nach Fix von Bug 1 legitim möglich: Composite-Score kann trotz mittelmäßiger "Signalqualität"/"Risk"-Donuts hoch sein, weil die Donuts eine ANDERE Kennzahl zeigen (Rating-Buchstabe/Modellqualität pur) als der Composite-Score (7-Faktor-Blend inkl. Quality-Gate/Profit-Faktor). Architektonisch erklärt, aber ggf. UX-mäßig noch verwirrend — evtl. Tooltip/Erklärung ergänzen.

## 🔴 Offen — Konfiguration

3. **Env-Variablen-Mismatch bei OpenAI External-Buy-Review-Settings.**
   `.env` setzt `EXTERNAL_BUY_REVIEW_MODEL`/`EXTERNAL_BUY_REVIEW_MAX_SEARCH_CALLS`, aber `config/aktienki.php` liest `EXTERNAL_BUY_REVIEW_V3_MODEL`/`EXTERNAL_BUY_REVIEW_V3_MAX_SEARCH_CALLS`. Solange Provider=openai aktiv war, liefen die Werte still auf die Fallback-Defaults (`gpt-5.6-luna`, 1 Suchaufruf) statt der eigentlich gewünschten (`gpt-5.6-terra`, 2 Aufrufe).
   *Datei:* `.env` vs. `config/aktienki.php:127,130`

4. **`GenerateExternalBuyReview`-Job kann sich selbst blockieren.**
   `ShouldBeUnique` mit `uniqueFor = 3600` (1h) hält die Lock-Datei auch nach einem fehlgeschlagenen Versuch (z.B. ungültiger API-Key) — ein erneuter Dispatch derselben Review-ID wird dann bis zu 1h lang **ohne jede Fehlermeldung** stillschweigend ignoriert. Musste einmal manuell per `cache:clear` umgangen werden.
   *Datei:* `app/Jobs/GenerateExternalBuyReview.php:25` — Lock-Release bei `failed()` ergänzen oder `uniqueFor` verkürzen.

## 🔴 Offen — Deployment / Repo-Hygiene

5. **Massiver uncommitted Diff, nie deployt.** Der komplette heutige Session-Stand (Composite-Score, Dashboard-Fixes, Perplexity-Integration, Grid-Feature, Chart-Fallback) liegt nur lokal im Working-Tree, nichts davon ist auf Produktion.
6. **Unerklärte, verdächtige Änderungen im selben Diff** (nicht von mir verursacht): `config/legal.php` verändert, `resources/legals/agb_v1.0.md` + `privacy_v1.0.md` gelöscht. Vor einem Deploy unbedingt gegenprüfen, ob das gewollt ist.
7. **Lokaler Dev-Server zeigte auf falsche Datenbank.** `.env` `DB_PORT=5432` (lokaler Homebrew-Postgres) statt `15432` (SSH-Tunnel zur echten Produktions-DB) — behoben, aber production selbst wurde nie geprüft, ob dort dieselbe Klasse von Fehlkonfiguration vorliegt.

## ⏸️ Wartet auf Input

8. **Grid-basierte KI-Einordnung fertig gebaut, aber inaktiv.** Braucht `GRID_API_KEY` in `.env` + `STOCK_AI_ASSESSMENT_ENABLED=true`, dann `php artisan reports:stock-ai-assessments` einmal testweise laufen lassen.
   *Dateien:* `app/Services/StockAiAssessmentService.php`, `app/Console/Commands/GenerateStockAiAssessments.php`
9. **12 "Strategie"-Datensätze (SavedPredictionFilter) nie angelegt.** Top-3/Top-5-Quality, je eine pro qualifiziertem Land (DE/US/GB/FR/IT/NL) und Sektor (Industrials/Financial Services/Technology/Communication Services) — Auswahl muss mit dem jetzt korrigierten (horizontgebundenen) Composite-Score neu gezogen werden, alte Zahlen sind überholt.

## 🔴 Neu gefunden (Code-Review 2026-09-13)

12. **Zwei deutsche Literal-Strings ohne Englisch-Übersetzung.**
    `__('Aktuelle Serving-Prediction ist verfügbar.')` und `__('Keine zusätzlichen Warnsignale in den kompakten Serving-Daten.')` fehlen in `lang/en.json` — pre-existing, nicht von der heutigen Session verursacht, aber vom `LocalizationCoverageTest` aktuell aufgedeckt.
    *Datei:* `app/Services/ServingScreenerService.php:1039-1040`

13. **`GenerateStockAiAssessments` überschreibt `created_at` bei jeder Neugenerierung.**
    `updateOrInsert()`s Update-Zweig setzt `created_at => now()` mit — bei `--force`-Neugenerierung am selben Tag geht das ursprüngliche Erstellungsdatum verloren (kosmetisch, aber unsauber für Audit-Zwecke).
    *Datei:* `app/Console/Commands/GenerateStockAiAssessments.php` — `created_at` nur im Insert-Zweig setzen.

14. **Yahoo-Finance-Fallback nutzt denselben `providerSymbol` wie Twelve Data.**
    Twelve-Data- und Yahoo-Tickerformate für dieselbe Aktie können abweichen (z.B. Suffix-Konventionen für ausländische Börsen). `YahooIndexService::dailyHistory()` validiert zwar streng gegen das zurückgegebene Meta-Symbol (liefert bei Mismatch einfach leer statt falscher Daten), aber der Fallback greift dadurch nur bei Symbolen, deren Format zufällig auf beiden Plattformen übereinstimmt — kein Crash-Risiko, aber begrenzter Nutzen als eigentlich erhofft.
    *Datei:* `app/Services/ServingChartCacheService.php`

15. ✅ **"PANEL 20T"-Banner überschnitt sich mit der 20T-Wahrscheinlichkeits-Badge im Aktienchart.**
    Beide waren an derselben Y-Position (`BOTTOM_BADGE_Y`) verankert. Da die Panel-Prognose ("20 Handelstage ab dem letzten Panel-Datenpunkt") und die 20T-Wahrscheinlichkeits-Badge praktisch immer auf dasselbe oder ein direkt benachbartes Kalenderdatum zeigen, lagen beide Elemente bei den meisten Aktien mit Panel-Daten optisch übereinander. Behoben durch eine eigene zweite Zeile (`PANEL_BADGE_Y`) für das Panel-Banner.
    *Datei:* `resources/views/stocks/show.blade.php` (`drawSmaLines()`/`renderMainChart()`)
16. ✅ **730 fehlende Übersetzungen (Deutsch → Englisch) ergänzt.** `lang/en.json` von 3370 auf 4100 Einträge erweitert, `LocalizationCoverageTest` jetzt grün.
17. ✅ **Champion-Karte auf dem Handy: Faktoren-Box hatte keine Mobile-Verkleinerung.** Die Verkleinerung existierte nur für den schmalen Desktop-Spaltenmodus (≥1280px) und für die kleinere Variante in "Beste Alternativen" — die Champion-Karten-eigene Box blieb auf dem Handy in voller Desktop-Größe (66px/76px), was den Namensbereich unnötig einengt. Jetzt mit eigener Verkleinerung im 767px-Mobile-Block + Komplett-Ausblendung unter 380px Breite.
    *Datei:* `resources/views/dashboard.blade.php`

**Ergebnis der Gegenprüfung:** Vollständiger Testlauf (289 Tests) zeigt 25 Fehlschläge — alle davon durch gezielten Vergleich als bereits vorbestehend bzw. aus anderer, unabhängiger, nicht-committeter Arbeit (Infrastructure-Admin-Panel, Simulationsmodus, Corporate-Events, `ServingPortfolioSimulationService`) verifiziert. Keiner der 25 stammt aus den heutigen Änderungen dieser Session.

## ✅ Exit-Strategie-Analyse (2026-09-13)

18. ✅ **Systematischer Vergleich TCN-Signal-Exit vs. Fixhorizont-Exit über alle 5 Serving-Strategien × 3 Horizonte.** Ergebnis: bei `dax-standard-seven-model-tcn-v1` schadet der TCN-Exit messbar (PF -15..19%, Ø-Rendite -30..51%), bei den anderen 4 Strategien löst er praktisch nie aus. `RunFilteredBacktest.php` nutzt jetzt `scheduled_net_return`/`scheduled_exit_date`/`scheduled_exit_close_eur` statt der tatsächlichen TCN-Exit-Werte, sobald verfügbar. Live an einem echten Trade verifiziert (-0,88% → +0,53%).
    Als sichtbares, standardmäßig aktiviertes Item "Reinen Horizont-Exit bevorzugen" im Strategie-Assistenten (Schritt 4) verankert — Filter-Key `serving_fixed_horizon_exit_enabled`, pro Strategie abschaltbar.
    *Dateien:* `app/Jobs/RunFilteredBacktest.php`, `app/Http/Controllers/SavedPredictionFilterController.php`, `resources/views/predictions/heatmap.blade.php`

## ✅ Transaktions-Tracking für alle Strategien (2026-09-13)

19. ✅ **Alle Strategien schreiben jetzt echte BUY/SELL-Signale in eine vollwertige virtuelle Depotführung, nicht nur die mit einem sichtbaren Musterdepot verknüpften.** Wiederverwendet die bereits vorhandene, getestete `AutomatedPortfolioService`-Infrastruktur (inkl. `portfolio_cash_ledger` als Verrechnungstabelle) statt eines Parallelsystems. Neuer, unsichtbarer Portfolio-Typ `strategy_tracking`: `EnsureStrategyTrackingPortfolios`-Command legt pro Strategie ohne Depot automatisch ein Tracking-Depot an (alle 10 Min. per Scheduler), verschickt aber nie E-Mails und erscheint nie in der Depot-Übersicht. `AutomatedPortfolioService::scan()` musste um einen OR-Zweig erweitert werden, damit dieser neue Typ nicht den geteilten Strategie-Schalter `automatic_portfolio_enabled` benötigt (der hätte sonst versehentlich auch reale Musterdepots scharf geschaltet). `PORTFOLIO_AUTOMATION_ENABLED` global aktiviert; das echte "Strategietest"-Depot zusätzlich einzeln auf `live_enabled=true` + `transaction_email_enabled=true` gestellt.
    *Dateien:* `app/Console/Commands/EnsureStrategyTrackingPortfolios.php`, `app/Services/AutomatedPortfolioService.php`, `app/Http/Controllers/DepotController.php`, `app/Services/PortfolioTradeEmailService.php`, `routes/console.php`
20. ✅ **Kauf/Verkauf-Benachrichtigungsmail: Logo, Wortwahl, Score-Quelle und fehlende Inhalte überarbeitet.** `RecommendationEmailLogo` (weiterhin genutzt von `PublicPortfolioTradeNotification` und den übrigen Recommendation-Mails) liefert jetzt direkt `public/brand/generated/bull-logo-dark.png` statt eines per GD gezeichneten Platzhalters. Wortwahl von "Kauf ausgeführt"/"Verkauf ausgeführt" auf neutral "Aktie dem Depot hinzugefügt"/"Aktie aus dem Depot entfernt" geändert (keine Kauf-/Verkaufsempfehlung, reine Beschreibung der automatischen Depotbuchung). Die Aktions-Pille ist jetzt ein volles Banner über die Kartenbreite (mit Gap zum Inhalt darunter) statt einer kleinen Inline-Pille; die separate Logo/Tagline-Kopfzeile darüber entfiel (war redundant). **KI-Score zeigte den rohen, modelleigenen `predictions.prediction_score` (0-10-Skala) statt des im Rest der App etablierten einheitlichen Composite-Scores (0-100) — passte optisch/inhaltlich nicht zu Screener/Dashboard/Aktienseite.** Zeigt jetzt `ServingScreenerService::compositeScores()` für dieselbe Aktie, farbcodiert wie dort. Kursverlauf-Chart (`RecommendationEmailChart`, 32 Tagesbars) und die externe Bewertung (`mail/partials/external-buy-review.blade.php`, per `instrument_id` statt `prediction_id` gesucht — die meisten Serving-Reviews haben kein `prediction_id`) ergänzt.
    *Dateien:* `app/Services/RecommendationEmailLogo.php`, `app/Services/PortfolioTradeEmailService.php`, `app/Notifications/PortfolioTradeNotification.php`, `app/Notifications/PublicPortfolioTradeNotification.php`, `resources/views/mail/portfolio-trade.blade.php`

## 🟡 Teilweise erledigt

10. ✅ **Perplexity-Reviews: hohe INSUFFICIENT_EVIDENCE-Quote — akzeptiert, kein Bug.** `search_context_size` von `low` auf `medium` gestellt, alle 96 betroffenen Aktien neu bewertet (78 von 96 bleiben trotzdem bei "unzureichende Beweise", da die 2-Domain-Mindestanforderung für Perplexity-Sonar strukturell schwer erreichbar ist). Geprüft und bestätigt: der Kommentar/die Zusammenfassung wird trotzdem vollständig geschrieben — nur Verdict/Confidence werden gedeckelt, nicht der Text. Als gewolltes Verhalten akzeptiert.
11. **Yahoo-Finance-Fallback für Chartanzeige.** In `ServingChartCacheService` eingebaut (Fallback auf `YahooIndexService::dailyHistory()`, wenn Twelve Data leer liefert), Dependency-Injection verifiziert — siehe Punkt 14 zur Symbolformat-Einschränkung. Ende-zu-Ende noch nicht an einer echten leeren-Twelve-Data-Aktie getestet.

## ✅ Heute behoben (2)

23. ✅ **"Allocation je Aktie"-Fenster im Strategie-Assistenten hatte keine Horizont-Auswahl.** Die im Hauptfilter gesetzte Horizont-Auswahl (10T/20T/40T) wurde beim Öffnen dieses Fensters nur unsichtbar per Hidden-Field durchgereicht, ohne dass sie dort sichtbar oder änderbar war. Jetzt als eigenes, editierbares Auswahlfeld direkt im Fenster (gleiches Toggle-Button-Muster wie "Allocation je Aktie").
    *Datei:* `resources/views/predictions/heatmap.blade.php`

## ✅ Heute behoben (3)

24. ✅ **KI-Score im Marktüberblick auf 0-100 umgestellt.** Sowohl die "Market Command Center"-Kennzahlen im Dashboard (`ServingMarketSnapshotService`, inkl. Regime-Schwellenwerte 65/40 statt 6,5/4,0 und Cache-Key-Versionsbump wegen Skalenwechsel) als auch die Börsen-Übersichtsseite (`markets/index.blade.php`: "Höchster KI-Score"-Karte je Börse und die sortierbare Börsentabelle, per `AiScore::toPercent()` statt `toTen()`) zeigten den KI-Score noch auf der alten 0-10-Skala statt dem seit heute etablierten einheitlichen 0-100-Composite-Score.
    *Dateien:* `app/Services/ServingMarketSnapshotService.php`, `resources/views/livewire/dashboard/market-data.blade.php`, `resources/views/markets/index.blade.php`
    *Update:* Auf expliziten Wunsch ("der Score muss überall umgebaut werden") vollständig nachgezogen — siehe Punkt 26.
25. ✅ **700 von 852 aktiven Aktien hatten weniger als 3 Jahre `1d`-Kurshistorie (524 davon komplett ohne)** — direkte Ursache für die 132-von-772-Lücke im Strategie-Assistenten (Punkt 22). Neuer Befehl `stocks:backfill-short-history` lädt fehlende Historie über Twelve Data nach (gleiche Speicherung wie bei bestehenden Aktien: `price_bars`, `interval='1d'`, `source='twelve_data'`), Symbol-Auflösung über die bereits bestehende `ServingChartCacheService::providerSymbol()`. Vollständiger Lauf für alle 700 Aktien gestartet.
    *Datei:* `app/Console/Commands/BackfillShortHistoryStocks.php`
    *Ergebnis:* 471 von 700 erfolgreich nachgeladen. 226 scheitern weiterhin mit "Keine Tageskurse geliefert" — bei Stichproben ausländischer Notierungen (z.B. `9618.HK`) löst `ServingChartCacheService::providerSymbol()` über `german_listing_symbol`/`german_listing_exchange` ein falsches Symbol auf (z.B. `JD3:XETR`, ein deutsches XETRA-Symbol für eine Hongkong-Aktie). Eigenständiges Symbol-Mapping-Problem für nicht-deutsche Börsenplätze, noch offen.

26. ✅ **KI-Bewertung auf der Aktienseite zeigte nur 3 von 7 Gesamtscore-Komponenten; KI-Score/0-10-Skala vollständig auf 0-100 umgebaut.** Die "KI-Bewertung"-Donuts zeigten nur Signalqualität (30% Gewicht), Risiko (12%) und Panel Sektor (5%) — Quality Gate (25%, zweitgrößtes Gewicht!), Profit-Faktor (13%) und Konfidenz (10%) fehlten komplett. Drei neue Donuts ergänzt (`ServingStockLegacyViewService` exponiert jetzt `qualityGatePassed`/`profitFactorValue`/`profitFactorPercent`/`confidencePercentValue` einzeln); Donut-Reihe umbricht jetzt zweizeilig statt abgeschnitten zu werden. Zusätzlich auf expliziten Wunsch systematisch alle verbliebenen Live-Vorkommen von `AiScore::toTen()`/"X,X / 10" in der ganzen App auf `AiScore::toPercent()`/"XX / 100" umgestellt (Dashboard-Kacheln, Marktkarten, Indizes, Sektoren, Watchlists, Prognosetabelle, Vergleichsansicht, Reports, mehrere E-Mail-Vorlagen inkl. dem Score/Risiko-Donut-Bild). Bewusst NICHT angefasst: rein interne, in sich konsistente 0-10-Verwendungen ohne Nutzeranzeige (Chart-Koordinatensysteme, interne Ranking-/Bucket-Formeln) sowie tote/unreferenzierte Dateien (`resources/views/show.blade.php`, `stocks/show-copy.blade.php`, `markets/index-copy.blade.php`).
    *Dateien:* `app/Services/ServingStockLegacyViewService.php`, `resources/views/stocks/show.blade.php`, `app/Services/MarketService.php`, `app/Services/IndexAiScoreService.php`, `app/Services/SignalEmailDonutChart.php`, `app/Http/Controllers/IndexScreenerController.php`, `app/Notifications/EntrySignalBuyNotification.php`, `app/Console/Commands/Send{Dashboard,Recommendation}Digest.php`, diverse `resources/views/**/*.blade.php`

## 🔴 Offen (beim Deploy entdeckt)

22. **Strategie-Assistent/Heatmap zeigt nur ~133 von 772+ Aktien — strukturell, nicht filterbedingt.** Jeder System-Backtest-Lauf (`backtest_runs`, `run_type` = system/`system_walk_forward_source`) deckt seit mindestens 21.8.2026 konsequent exakt 133 Instrumente ab (verifiziert über 6 aufeinanderfolgende Läufe inkl. dem heutigen von 15:52 Uhr) — unabhängig von Score-/Konfidenz-/Qualitäts-Filtern (alle auf Standard/aus getestet). Der komplette Backtest-Assistent (`PredictionController::heatmap()`, `AutomatedPortfolioService::candidates()`) basiert auf der Legacy-`predictions`/`backtest_trades`-Tabelle, die nur für diese kleine Kern-Auswahl gepflegt wird, während das reale/Serving-Universum 522-928 Aktien umfasst. Echte Lösung: entweder den System-Backtest auf das volle Universum ausweiten, oder den Strategie-Assistenten auf die Serving-Datenbasis umstellen (großer Schnitt, nicht nebenbei zu machen).
    *Dateien:* `app/Http/Controllers/PredictionController.php` (heatmap-Query), `app/Services/AutomatedPortfolioService.php` (`candidates()`)
21. **`ENTRY_SHADOW_SOURCE_BATCH_FAILED` — wiederkehrender Fehler alle 5 Minuten seit mindestens 9.9.2026, unabhängig vom heutigen Deploy.** `FinalEntryShadowWriter.php:279` (`assertOutboxEnvelope()`), ausgelöst über `signals:shadow-final-entry` (`app/Console/Commands/ShadowFinalEntrySignals.php`). Über 1200 Vorkommen im Produktions-Log, keine sichtbare Auswirkung auf die Nutzer-Anwendung bisher (Shadow-Modus), aber unnötige Log-/Fehlerlast. Noch nicht untersucht.
    *Datei:* `app/Services/FinalEntryShadowWriter.php`

## ✅ Heute behoben

- Composite-Score horizontgebunden (nicht mehr cross-scope geblendet)
- Composite-Score auf Aktien-Detailseite = identischer Wert wie im Screener (statt eigener Näherung)
- Dashboard-"Drei-Faktoren-Champion" nutzt jetzt denselben Composite-Score wie der Screener statt einer eigenen Durchschnittsformel
- Champion/Alternativen kommen aus demselben Pool (garantiert absteigende Reihenfolge)
- "Vielleicht in ein paar Tagen interessant"-Karte zeigte immer eine hartcodierte 0 im Donut — jetzt echter Score
- Externe Bewertung: Provider/Modell werden aus Config gelesen statt hartcodiert "openai"/"gpt-5.6-luna"
- Perplexity Sonar als wählbarer Provider für externe Bewertungen eingebaut
- PHP `memory_limit` (lokal) 128M → 512M nach Fatal Error auf der Aktienseite

## ✅ Heute behoben (4)

27. ✅ **Falsches Logo im Light Theme (Topbar zeigte den cyanfarbenen Dark-Theme-Bullen statt des orangenen) + zwei tiefere Bugs dahinter.** Ursache: `data-theme` wird ausschließlich clientseitig per JS gesetzt (`preferences.js`, erst nach `DOMContentLoaded`); die CSS-Regeln basierten auf `:root:not([data-theme="light"])`, was vor Ablauf des JS (und damit bei jedem Seitenaufruf kurz) fälschlich als "dark" galt, obwohl Light das echte Standard-Theme aller Nutzer ist. Zusätzlich zeigte `brand-wordmark.blade.php` (Login/Registrierung/Preise/Kontakt/Roadmap/… — 11 Seiten) die Dark-Variante am Desktop nie an, da schlicht keine `display:block`-Regel dafür existierte. Behoben: Light-Artwork ist jetzt ohne Attribut-Abhängigkeit der Standard (kein Flash mehr möglich), Dark ist ein expliziter `[data-theme="dark"]`-Override; beide Kopfzeilen-Logos nutzen jetzt außerdem denselben orangenen Bullen (`bull-logo-dark.png` für dunkle, `bull-logo-light-clean.png` für helle Hintergründe) statt des bisherigen cyanfarbenen `bull-icon.png`.
    *Dateien:* `resources/views/components/app-topbar.blade.php`, `resources/views/components/brand-wordmark.blade.php`, `resources/css/app.css`
    *Hinweis:* `welcome-brand-logo.blade.php` wird dadurch nirgends mehr referenziert (totes Component-File, nicht gelöscht).

---
*Format-Vorschlag: dieses Dokument einfach der Reihe nach abarbeiten, erledigte Punkte auf ✅ setzen statt löschen (Historie bleibt nachvollziehbar).*
