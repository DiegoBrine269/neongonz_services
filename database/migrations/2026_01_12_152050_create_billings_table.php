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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', '50')->nullable();
            $table->enum('payment_method', ['PUE', 'PPD'])->nullable();
            $table->enum('payment_form', ['01', '02', '03', '04', '28', '29', '30', '31', '99'])->nullable();
            $table->enum('type', ['factura', 'complemento'])->default('factura');

            $table->timestamps();
        });

        //Primero eliminamos los campos que ya había en invoices
        Schema::table('invoices', function (Blueprint $table){
            $table->dropColumn([
                'uuid',
                'payment_method',
                'payment_form',
                'billing_date',
                'uuid_complement',
            ]);

            //Nuevas columnas
            $table->unsignedBigInteger('billing_id')->nullable()->after('oc');
            $table->unsignedBigInteger('complement_id')->nullable()->after('billing_id');

            //Referencias
            $table->foreign('billing_id')
                ->references('id')
                ->on('billings')
                ->onDelete('set null');

            $table->foreign('complement_id')
                ->references('id')
                ->on('billings')
                ->onDelete('set null');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1️⃣ Primero quitamos las foreign keys y columnas nuevas
        Schema::table('invoices', function (Blueprint $table) {

            // Foreign keys
            $table->dropForeign(['billing_id']);
            $table->dropForeign(['complement_id']);

            // Columnas nuevas
            $table->dropColumn('billing_id');
            $table->dropColumn('complement_id');
        });

        // 2️⃣ Restauramos las columnas originales en invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('uuid', 50)->nullable()->after('oc');
            $table->enum('payment_method', ['PUE', 'PPD'])->nullable()->after('uuid');
            $table->enum('payment_form', ['01', '02', '03', '04', '28', '29', '30', '31', '99'])->nullable()->after('payment_method');
            $table->date('billing_date')->nullable()->after('payment_form');
            $table->char('uuid_complement', 50)->nullable()->after('billing_date');
        });

        // 3️⃣ Eliminamos la tabla billings
        Schema::dropIfExists('billings');
    }

};
