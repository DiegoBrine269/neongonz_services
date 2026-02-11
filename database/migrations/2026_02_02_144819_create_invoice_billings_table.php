<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        // 1) Quitar columnas de invoices (tabla existente)
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'billing_id',
                'complement_id',
                'billing_pdf_path',
                'billing_xml_path',
                'complement_pdf_path',
                'complement_xml_path',
            ]);
        });

        // 2) Crear tabla pivote invoice_billings
        Schema::create('invoice_billings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('billing_id')->constrained('billings')->cascadeOnDelete();

            // $table->string('pdf_path')->nullable();
            // $table->string('xml_path')->nullable();

            $table->timestamps();

            // Evita duplicados (misma factura-billing repetida)
            $table->unique(['invoice_id', 'billing_id']);
        });

        // 3) Añadir pdf_path y xml_path a tabla billings
        Schema::table('billings', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('type');
            $table->string('xml_path')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        // 1) Eliminar pivote primero (por llaves foráneas)
        Schema::dropIfExists('invoice_billings');

        // 2) Volver a crear columnas en invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->unsignedBigInteger('complement_id')->nullable();

            $table->string('billing_pdf_path')->nullable();
            $table->string('billing_xml_path')->nullable();
            $table->string('complement_pdf_path')->nullable();
            $table->string('complement_xml_path')->nullable();

            $table->foreign('billing_id')->references('id')->on('billings')->nullOnDelete();
            $table->foreign('complement_id')->references('id')->on('billings')->nullOnDelete(); // o la tabla correcta
        });

        // 3) Eliminar columnas pdf_path y xml_path de billings
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'xml_path']);
        });
    }
};
