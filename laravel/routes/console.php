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

// Store one deterministic Top-10 snapshot per day for the ranking history.
// The live screener score is calculated exclusively from model and backtest data.
Schedule::command('stocks:screen-top100 --limit=10')
    ->dailyAt('06:15')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('signals:send-emails --since=30')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('signals:send-entry-alerts')
    ->dailyAt('06:30')
    ->withoutOverlapping(10)
    ->runInBackground();
Schedule::command('predictions:send-purchase-reminders')->dailyAt('07:00')->withoutOverlapping(10)->runInBackground();

// Point-in-time context for sector and index rotation. This runs after the
// daily stock prediction batch and is consumed by depot selection and emails.
Schedule::command('predictions:market-context')
    ->dailyAt('06:20')
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('markets:generate-index-infos')
    ->dailyAt('06:35')
    ->withoutOverlapping(20)
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
