<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }

    public function down(): void
    {
        // Tabelle ricreate dalle migration originali 2026_02_04_235000 e 2026_02_04_235100.
    }
};
