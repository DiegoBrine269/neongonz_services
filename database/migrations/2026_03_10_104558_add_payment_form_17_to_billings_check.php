<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE billings DROP CONSTRAINT billings_payment_form_check");
        DB::statement("ALTER TABLE billings ADD CONSTRAINT billings_payment_form_check 
            CHECK (payment_form::text = ANY (ARRAY[
                '01', '02', '03', '04', '17', '28', '29', '30', '31', '99'
            ]))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE billings DROP CONSTRAINT billings_payment_form_check");
        DB::statement("ALTER TABLE billings ADD CONSTRAINT billings_payment_form_check 
            CHECK (payment_form::text = ANY (ARRAY[
                '01', '02', '03', '04', '28', '29', '30', '31', '99'
            ]))");
    }
};
