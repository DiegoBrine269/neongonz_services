<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('responsibles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('email'); // cada responsable con un correo único
            $table->timestamps();
        });

        Schema::create('centre_responsible', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('centre_id');
            $table->unsignedBigInteger('responsible_id');
            $table->timestamps();

            $table->foreign('centre_id')->references('id')->on('centres')->onDelete('cascade');
            $table->foreign('responsible_id')->references('id')->on('responsibles')->onDelete('cascade');

            $table->unique(['centre_id', 'responsible_id']); // evitar duplicados
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('centre_responsible');
        Schema::dropIfExists('responsibles');

    }
};
