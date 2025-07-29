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
        // Schema::create('project_types', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('name')->unique();
        // });

        // DB::table('project_types')->insert([
        //     ['name' => 'Servicio a flotilla'],
        //     ['name' => 'Venta de artículos'],
        //     ['name' => 'Préstamo'],
        //     ['name' => 'Mantenimiento a taller'],
        // ]);

        Schema::table('projects', function (Blueprint $table) {
            // $table->unsignedBigInteger('project_type_id')->default(1)->after('id');
            $table->text('commentary')->nullable()->after('has_vehicles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('projects', function (Blueprint $table) {
        //     $table->dropForeign(['project_type_id']);
        // });


        // Schema::dropIfExists('project_types');

        Schema::table('projects', function (Blueprint $table) {
            // $table->dropColumn('has_vehicles');
            $table->dropColumn('commentary');
        });
    }
};
