<?php

use App\Services\VesselService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Gera as viagens das balsas a partir da grade recorrente.
Artisan::command('vessels:generate-departures {days=14}', function (VesselService $vessels) {
    $days = (int) $this->argument('days');
    $created = $vessels->generateDepartures(Carbon::today(), Carbon::today()->addDays($days));
    $this->info("Viagens geradas para os próximos {$days} dias: {$created} nova(s).");
})->purpose('Gera as viagens das balsas a partir da grade de horários');

Schedule::command('vessels:generate-departures 14')->dailyAt('00:10');
