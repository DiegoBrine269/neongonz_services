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
            $table->string('billing_pdf_path')->nullable()->after('uuid_complement');
            $table->string('billing_xml_path')->nullable()->after('billing_pdf_path');

            $table->string('complement_pdf_path')->nullable()->after('billing_xml_path');
            $table->string('complement_xml_path')->nullable()->after('complement_pdf_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_path', 'complement_path', 'billing_xml_path', 'complement_xml_path']);
        });
    }
};
