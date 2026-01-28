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
            $table->char('oc', '15')->nullable()->after('price');
            $table->char('uuid', '50')->nullable()->after('oc');
            $table->enum('payment_method', ['PUE', 'PPD'])->nullable()->after('uuid');
            $table->enum('payment_form', ['01', '02', '03', '04', '28', '29', '30', '31', '99'])->nullable()->after('payment_form');
            $table->char('f_receipt', '50')->nullable()->after('payment_form');
            $table->date('billing_date')->nullable()->after('f_receipt');
            $table->date('validation_date')->nullable()->after('billing_date');
            $table->char('uuid_complement', '50')->nullable()->after('validation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['oc', 'uuid', 'payment_method', 'payment_form', 'f_receipt', 'billing_date', 'validation_date', 'uuid_complement']);
        });
    }
};
