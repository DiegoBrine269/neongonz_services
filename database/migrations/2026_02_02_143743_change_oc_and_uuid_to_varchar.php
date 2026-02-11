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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('oc', 15)->nullable()->change();
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('oc', 15)->nullable()->change();
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->string('uuid', 50)->nullable()->change();
        });
    }
};
