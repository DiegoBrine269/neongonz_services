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
            $table->decimal('total', 10, 2)->after('date')->nullable(); 
            $table->string('comments')->nullable()->after('total'); 
            $table->string('path')->nullable()->after('comments');
            $table->string('invoice_number')->nullable()->after('path');
        });

        Schema::table('invoice_vehicles', function (Blueprint $table) {
            $table->dropColumn('commentary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['total', 'comments', 'path', 'invoice_number']); 
        });

        Schema::table('invoice_vehicles', function (Blueprint $table) {
            $table->text('commentary')->nullable();
        });
    }
};
