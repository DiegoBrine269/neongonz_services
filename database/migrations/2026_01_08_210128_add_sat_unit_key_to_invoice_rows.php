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
        Schema::table('invoice_rows', function (Blueprint $table) {
            //
            $table->string('sat_unit_key', 10)->nullable()->after('price'); // Ej: E48, H87

            $table->char('sat_key_prod_serv', 10)->nullable()->after('sat_unit_key'); // Ej: 10101500

            $table->foreign('sat_unit_key')
                  ->references('key')
                  ->on('sat_units')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_rows', function (Blueprint $table) {
            //
            $table->dropForeign(['sat_unit_key']);
            $table->dropColumn('sat_unit_key');
            $table->dropColumn('sat_key_prod_serv');
        });
    }
};
