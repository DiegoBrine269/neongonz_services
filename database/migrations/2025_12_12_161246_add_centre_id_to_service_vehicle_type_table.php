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
        Schema::table('service_vehicle_type', function (Blueprint $table) {
            // Nuevo campo centre_id opcional
            $table->foreignId('centre_id')
                ->nullable()
                ->constrained('centres'); // centres.id
        });

        // Índice único parcial para el "precio general" (centre_id NULL)
        // Esto es propio de PostgreSQL
        DB::statement("
            CREATE UNIQUE INDEX service_vehicle_type_general_unique
            ON service_vehicle_type (service_id, vehicle_type_id)
            WHERE centre_id IS NULL
        ");

        // Índice único para evitar duplicados por centro / servicio / tipo
        Schema::table('service_vehicle_type', function (Blueprint $table) {
            $table->unique(
                ['service_id', 'vehicle_type_id', 'centre_id'],
                'svc_vtype_centre_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el índice único compuesto
        Schema::table('service_vehicle_type', function (Blueprint $table) {
            $table->dropUnique('svc_vtype_centre_unique');
        });

        // Eliminar el índice parcial (SQL crudo)
        DB::statement('DROP INDEX IF EXISTS service_vehicle_type_general_unique');

        // Eliminar la foreign key y la columna
        Schema::table('service_vehicle_type', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centre_id');
        });
    }
};
