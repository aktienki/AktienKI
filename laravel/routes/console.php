<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('models:refresh-champion-challengers')
    ->dailyAt('04:30')
    ->withoutOverlapping();

Schedule::command('markets:refresh-indices')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Newly imported stocks receive their bilingual descriptions automatically.
// The command skips instruments whose four description fields are complete.
Schedule::command('instruments:generate-descriptions --limit=25 --sleep-ms=100')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('signals:send-emails --since=30')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('opportunities:purge')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// Retraining can move a stock out of SLEEP as soon as its validated profit
// factor reaches 1.05. The classifier is intentionally independent of active.
Schedule::command('stocks:classify-risk')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// A completed walk-forward run changes the evidence behind the user-facing
// score. Recalculate only affected latest predictions; the raw model score is
// retained in model_prediction_score for auditing.
Schedule::command('scores:recalculate')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground();

// Score exits use the latest finalized daily prediction. A weak rating must
// persist for two sessions; model retraining alone never triggers an exit.
Schedule::command('exits:evaluate-normalized-scores')
    ->weekdays()->dailyAt('07:15')->timezone('Europe/Berlin')
    ->withoutOverlapping(30)->onOneServer()->runInBackground();

// Persist both language variants after the daily prediction batch. The report
// command skips a prediction/locale pair that has already been generated.
Schedule::command('reports:signal-change --locale=de')
    ->weekdays()->dailyAt('08:30')->timezone('Europe/Berlin')
    ->withoutOverlapping(30)->onOneServer()->runInBackground();
Schedule::command('reports:signal-change --locale=en')
    ->weekdays()->dailyAt('08:35')->timezone('Europe/Berlin')
    ->withoutOverlapping(30)->onOneServer()->runInBackground();

// Indicator and chart signals are prepared before the 08:00 prediction batch.
// They are refreshed once more by predictions:run-server after the predictions.
Schedule::command('chartview:refresh-signals')->dailyAt('06:10')->withoutOverlapping(60)->runInBackground();
Schedule::command('market-factors:calculate --days=14')
    ->weekdays()->dailyAt('06:25')->timezone('Europe/Berlin')
    ->withoutOverlapping(60)->onOneServer()->runInBackground();

// Refresh the persisted daily series used by the DAX, VDAX and bond macro
// cards after the European and US cash sessions have closed.
Schedule::command('markets:refresh-macro-history --range=3y')
    ->weekdays()->dailyAt('23:15')->timezone('Europe/Berlin')
    ->withoutOverlapping(90)->onOneServer()->runInBackground();

// Keep the broad German stock universe current as market caps and the
// available instrument catalogue grow. It is intentionally not labelled DAX.
Schedule::command('indices:sync-germany-top500')
    ->dailyAt('06:20')->timezone('Europe/Berlin')
    ->withoutOverlapping(10)->onOneServer()->runInBackground();

Schedule::command('markets:generate-index-infos')
    ->dailyAt('06:35')->timezone('Europe/Berlin')
    ->withoutOverlapping(20)
    ->onOneServer()
    ->runInBackground();

if (config('aktienki.portfolio_automation.enabled', false)) {
    Schedule::command('portfolios:send-trade-emails --limit=100')
        ->everyMinute()
        ->withoutOverlapping(10)
        ->runInBackground();

    // Prediction batches are written by the Python engine. Scanning every
    // minute applies each new prediction exactly once through the unique key.
    Schedule::command('portfolios:run-automation')
        ->everyMinute()
        ->withoutOverlapping(10)
        ->runInBackground();
}

Schedule::command('beta:send-trial-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('events:sync-twelve-data --days-back=7 --days-forward=60')
    ->dailyAt('03:00')
    ->withoutOverlapping(30)
    ->runInBackground();

// ETF issuers usually refresh portfolio files daily. A weekly snapshot keeps
// the reverse stock-to-ETF lookup current without unnecessary provider load.
Schedule::command('etfs:sync-holdings')
    ->weeklyOn(1, '03:30')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground();

// Der automatische Zertifikate-Import ist vorerst deaktiviert. Der manuelle
// Befehl bleibt für einen späteren kontrollierten Neustart verfügbar.

// Official company announcements are fetched incrementally. GPT only sees
// newly stored releases and runs in compact batches before daily predictions.
Schedule::command('news:sync-press-releases --limit=2500 --analyze --analysis-limit=1000')
    ->weekdays()
    ->dailyAt('04:45')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping(150)
    ->onOneServer()
    ->runInBackground();

// Predictions run on the application server so production remains available
// even when the training workstation is offline. The workstation only trains
// and validates models; released artifacts are synchronized separately.
if (config('aktienki.python_engine.server_predictions_enabled', false)) {
    $limit = max(1, (int) config('aktienki.python_engine.prediction_limit', 5000));

    // Regional batches refresh prices and calculate the four production
    // horizons plus the stock phase filter after each cash session. They do
    // not publish a partially refreshed global snapshot.
    Schedule::command("predictions:run-server asia --limit={$limit} --defer-finalization")
        ->weekdays()
        ->dailyAt('10:00')
        ->timezone('Europe/Berlin')
        ->withoutOverlapping(180)
        ->onOneServer()
        ->runInBackground();
    Schedule::command("predictions:run-server europe --limit={$limit} --defer-finalization")
        ->weekdays()
        ->dailyAt('18:30')
        ->timezone('Europe/Berlin')
        ->withoutOverlapping(240)
        ->onOneServer()
        ->runInBackground();
    Schedule::command("predictions:run-server americas --limit={$limit} --defer-finalization")
        ->weekdays()
        ->dailyAt('23:30')
        ->timezone('Europe/Berlin')
        ->withoutOverlapping(240)
        ->onOneServer()
        ->runInBackground();

    // The morning job only publishes after the completeness gate. It updates
    // indicator, sector and index filters before the 5T-20T fusion and digest.
    Schedule::command("predictions:run-server all --limit={$limit} --finalize-only --send-digest")
        ->weekdays()
        ->dailyAt('06:45')
        ->timezone('Europe/Berlin')
        ->withoutOverlapping(180)
        ->onOneServer()
        ->runInBackground();
}
