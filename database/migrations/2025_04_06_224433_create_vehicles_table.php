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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('eco');
            $table->unsignedBigInteger('centre_id');
            $table->unsignedBigInteger('vehicle_type_id');
            $table->timestamps();

            $table->foreign('centre_id')->references('id')->on('centres')->onDelete('cascade');
            $table->foreign('vehicle_type_id')->references('id')->on('vehicles_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
