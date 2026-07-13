<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Diesen Block in die bestehende routes/console.php übernehmen.
 * Vorhandene Einträge nicht löschen.
 */
Schedule::command('aktienki:evaluate-champions')
    ->dailyAt('02:30')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/champion-scheduler.log')
    );
