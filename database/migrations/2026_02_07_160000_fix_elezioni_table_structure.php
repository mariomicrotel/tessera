<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elezioni', function (Blueprint $table) {
            // Rimuovi le colonne vecchie se esistono
            if (Schema::hasColumn('elezioni', 'data_apertura')) {
                $table->dropColumn('data_apertura');
            }
            if (Schema::hasColumn('elezioni', 'data_chiusura')) {
                $table->dropColumn('data_chiusura');
            }
            
            // Aggiungi la colonna data_elezione se non esiste
            if (!Schema::hasColumn('elezioni', 'data_elezione')) {
                $table->date('data_elezione')->after('titolo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('elezioni', function (Blueprint $table) {
            // Ripristina le colonne vecchie
            if (!Schema::hasColumn('elezioni', 'data_apertura')) {
                $table->date('data_apertura')->after('titolo');
            }
            if (!Schema::hasColumn('elezioni', 'data_chiusura')) {
                $table->date('data_chiusura')->after('data_apertura');
            }
            
            // Rimuovi data_elezione
            if (Schema::hasColumn('elezioni', 'data_elezione')) {
                $table->dropColumn('data_elezione');
            }
        });
    }
};