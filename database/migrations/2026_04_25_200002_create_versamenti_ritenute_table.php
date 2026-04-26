<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versamenti delle ritenute d'acconto tramite Modello F24.
 *
 * Ogni versamento aggrega le ritenute del mese di riferimento.
 * Il codice tributo standard per ritenute su lavoro autonomo è 1040.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versamenti_ritenute', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->tinyInteger('mese_riferimento');   // 1-12: mese dei compensi versati
            $table->smallInteger('anno_riferimento');
            $table->date('data_versamento');           // data effettiva del F24

            $table->string('codice_tributo', 10)->default('1040');
            $table->decimal('importo_totale', 12, 2); // somma delle ritenute versate

            // Coordinate F24
            $table->string('codice_ufficio', 4)->nullable();
            $table->string('codice_atto', 10)->nullable();

            // Eventuali PDF/XML generati
            $table->string('f24_pdf_path', 255)->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'anno_riferimento', 'mese_riferimento'], 'vers_rit_periodo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versamenti_ritenute');
    }
};
