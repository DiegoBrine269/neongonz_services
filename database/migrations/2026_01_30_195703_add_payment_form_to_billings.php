<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar ENUM a VARCHAR(2)
        DB::statement("
            ALTER TABLE billings
            ALTER COLUMN payment_form TYPE VARCHAR(2)
            USING payment_form::text
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
  // 1. Crear nuevamente el ENUM
        DB::statement("
            CREATE TYPE payment_form_enum AS ENUM (
                '01','02','03','04','17','28','29','30','31','99'
            )
        ");

        // 2. Regresar la columna a ENUM
        DB::statement("
            ALTER TABLE billings
            ALTER COLUMN payment_form TYPE payment_form_enum
            USING payment_form::payment_form_enum
        ");
    }
};
