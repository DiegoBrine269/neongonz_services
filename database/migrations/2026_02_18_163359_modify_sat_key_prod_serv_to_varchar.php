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
            $table->string('sat_key_prod_serv', 8)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_rows', function (Blueprint $table) {
            //
            $table->char('sat_key_prod_serv', 8)->change();
        });
    }
};
