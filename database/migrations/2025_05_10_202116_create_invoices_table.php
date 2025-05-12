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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('centre_id');
            $table->date('date');

            $table->timestamps();
            $table->foreign('centre_id')->references('id')->on('centres')->onDelete('cascade');
        });

        Schema::create('invoice_vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('project_id');
            $table->text('commentary')->nullable();
            
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            

            $table->unique(['invoice_id', 'vehicle_id', 'project_id']);
        });

        Schema::table('project_vehicles', function (Blueprint $table) {
            $table->boolean('has_invoice')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
