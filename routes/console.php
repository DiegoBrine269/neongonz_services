<?php

use App\Console\Commands\LimpiarProyectos;
use App\Jobs\FetchInboxJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierra proyectos que llevan más de 7 días en estado abierto sin actividad
Schedule::command(LimpiarProyectos::class)->daily()->description('Cierra proyectos inactivos');

// routes/console.php
// Schedule::job(new FetchInboxJob)->everyMinute();