<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('models:refresh-champion-challengers')
    ->dailyAt('04:30')
    ->withoutOverlapping();

Schedule::command('markets:refresh-indices')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('queue:work --once --queue=default --timeout=1200 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(25)
    ->runInBackground();
