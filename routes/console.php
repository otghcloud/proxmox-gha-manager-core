<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('runners:reap')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('runners:warm-pools')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('targets:health')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('templates:prune')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('jobs:prune-logs')
    ->dailyAt('03:20')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('templates:check-updates')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
