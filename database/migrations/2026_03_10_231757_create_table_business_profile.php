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
        Schema::create('business_profile', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('legal_name');
            $table->string('rfc');
            $table->string('tax_regime')->nullable();  // RESICO, RIF, etc.
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('invoice_footer')->nullable(); // leyenda en PDFs
            $table->string('currency', 3)->default('MXN');
            $table->string('contact_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profile');
    }
};
