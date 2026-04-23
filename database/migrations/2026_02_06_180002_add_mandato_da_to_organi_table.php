<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            // Rimuovi mandato_a se esiste
            if (Schema::hasColumn('organi', 'mandato_a')) {
                $table->dropColumn('mandato_a');
            }
            // Aggiungi mandato_da se non esiste
            if (!Schema::hasColumn('organi', 'mandato_da')) {
                $table->date('mandato_da')->nullable()->after('richiedi_elezioni_fine_mandato');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organi', function (Blueprint $table) {
            if (Schema::hasColumn('organi', 'mandato_da')) {
                $table->dropColumn('mandato_da');
            }
            if (!Schema::hasColumn('organi', 'mandato_a')) {
                $table->date('mandato_a')->nullable()->after('mandato_da');
            }
        });
    }
};
