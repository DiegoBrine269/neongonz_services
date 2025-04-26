<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        //Del catálogo de servicios, en esta tabla se debe crear un precio por cada type
        Schema::create('service_vehicle_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('vehicle_type_id');
            $table->decimal('price', 10, 2)->nullable();

            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicles_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
