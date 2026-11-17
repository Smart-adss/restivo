<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:advance-states')->hourly()->withoutOverlapping();
Schedule::command('billing:issue-invoices')->dailyAt('02:10')->timezone('Africa/Cairo')->withoutOverlapping();
Schedule::command('reports:fill-daily-summaries')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('ops:health-check')->dailyAt('07:30')->timezone('Africa/Cairo')->withoutOverlapping();
