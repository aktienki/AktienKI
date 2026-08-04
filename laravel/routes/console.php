<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('models:refresh-champion-challengers')
    ->dailyAt('04:30')
    ->withoutOverlapping();

Schedule::command('markets:refresh-indices')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('signals:send-emails --since=30')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('portfolios:send-trade-emails --limit=100')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();
