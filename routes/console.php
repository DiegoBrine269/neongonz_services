<?php

use App\Console\Commands\LimpiarProyectos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierra proyectos que llevan más de 7 días en estado abierto sin actividad
Schedule::command(LimpiarProyectos::class)->everyDay()->description('Cierra proyectos inactivos');