<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
        {
            DB::table('centres')
            ->select('id', 'responsible', 'responsible_email')
            ->orderBy('id')
            ->chunk(100, function ($centres) {
                foreach ($centres as $centre) {
                    if ($centre->responsible && $centre->responsible_email) {
                        // Buscar si ya existe el responsable

                        $responsibleId = DB::table('responsibles')
                            ->where('name', $centre->responsible)
                            ->value('id');

                        if (!$responsibleId) {
                            $responsibleId = DB::table('responsibles')->insertGetId([
                                'name'       => $centre->responsible,
                                'email'      => $centre->responsible_email,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // Insertar relación en la tabla pivote
                        DB::table('centre_responsible')->updateOrInsert(
                            ['centre_id' => $centre->id, 'responsible_id' => $responsibleId],
                            ['updated_at' => now(), 'created_at' => now()]
                        );
                    }
                }
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Si hacemos rollback, borramos lo que se insertó
        DB::table('centre_responsible')->delete();
    }
};
