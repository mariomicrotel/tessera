<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella centri di costo per contabilità per area/progetto/sede.
 * Aggiunge FK nullable centro_costo_id su righe_movimento_contabile.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabella centri di costo
        Schema::create('centri_di_costo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('codice', 20);
            $table->string('descrizione', 200);
            $table->text('note')->nullable();
            $table->boolean('attivo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'codice'], 'cdc_tenant_codice_unique');
        });

        // 2. FK su righe_movimento_contabile (nullable — non tutte le righe hanno CDC)
        Schema::table('righe_movimento_contabile', function (Blueprint $table) {
            $table->unsignedBigInteger('centro_costo_id')
                  ->nullable()
                  ->after('gestione');

            $table->foreign('centro_costo_id')
                  ->references('id')
                  ->on('centri_di_costo')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('righe_movimento_contabile', function (Blueprint $table) {
            $table->dropForeign(['centro_costo_id']);
            $table->dropColumn('centro_costo_id');
        });

        Schema::dropIfExists('centri_di_costo');
    }
};
