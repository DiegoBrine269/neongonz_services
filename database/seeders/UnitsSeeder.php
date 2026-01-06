<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('sat_units')->insert([
            ['key' => 'H87', 'name' => 'Pieza'],
            ['key' => 'E48', 'name' => 'Unidad de servicio'],
        ]);
    }
}
