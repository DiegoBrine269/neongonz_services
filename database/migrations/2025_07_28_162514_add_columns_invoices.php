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
            $table->string('concept')->nullable();
            $table->integer('quantity')->nullable(); 
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('completed')->default(true);
            $table->text('services')->nullable();
            $table->text('internal_commentary')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('concept');
            $table->dropColumn('quantity');
            $table->dropColumn('price');
            $table->dropColumn('completed');
            $table->dropColumn('services');
            $table->dropColumn('internal_commentary');

        });
    }
};
