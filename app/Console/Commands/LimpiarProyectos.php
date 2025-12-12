<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;

class LimpiarProyectos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:limpiar-proyectos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $projects = Project::where('is_open', 'true')
        //     ->where('last_activity', '<', now()->subDays(7))
        //     ->get();

        Project::where('is_open', 'true')
            ->where('updated_at', '<', now()->subDays(7))
            ->update(['is_open' => 'false']);
    }
}
