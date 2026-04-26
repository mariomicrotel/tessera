<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erogazioni Liberali — D.Lgs. 117/2017 art. 83
 *
 * Traccia le donazioni ricevute dall'ETS con i dati fiscali necessari per:
 *  - La comunicazione annuale all'Agenzia delle Entrate (CSV/XML)
 *  - Il riconoscimento della detrazione in capo al donante (26% PF, 30% enti)
 *  - La rendicontazione nel bilancio ETS
 *
 * Requisito art. 83 CTS: la donazione deve avvenire con strumenti tracciabili
 * (bonifico, carta, assegno circolare). Le donazioni in contanti NON sono
 * detraibili e non devono essere incluse nella comunicazione AdE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erogazioni_liberali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // ── Anno di competenza ─────────────────────────────────────────
            $table->unsignedSmallInteger('anno');

            // ── Dati donante ───────────────────────────────────────────────
            $table->enum('donante_tipo', ['persona_fisica', 'ente'])->default('persona_fisica');
            $table->string('donante_cf', 16);           // CF obbligatorio (PF o ente)
            $table->string('donante_piva', 11)->nullable(); // P.IVA (solo enti)
            $table->string('donante_cognome', 100)->nullable(); // PF
            $table->string('donante_nome', 100)->nullable();    // PF
            $table->string('donante_ragione_sociale', 200)->nullable(); // Ente
            $table->string('donante_indirizzo', 200)->nullable();
            $table->string('donante_cap', 10)->nullable();
            $table->string('donante_comune', 100)->nullable();
            $table->char('donante_provincia', 2)->nullable();

            // ── Importo e data ─────────────────────────────────────────────
            $table->decimal('importo', 12, 2);
            $table->date('data_erogazione');

            // ── Modalità pagamento (tracciabilità art. 83 CTS) ─────────────
            $table->enum('modalita_pagamento', [
                'bonifico',
                'assegno_circolare',
                'carta_credito',
                'carta_debito',
                'altro_tracciabile',
                'contante',           // NON detraibile — solo registrazione
            ])->default('bonifico');

            // ── Detraibilità ───────────────────────────────────────────────
            $table->boolean('is_detraibile')->default(true);
            // Aliquota detrazione: 26% PF (art. 83 c.1), 30% enti (art. 83 c.2)
            // NULL = non detraibile (es. contante)
            $table->unsignedTinyInteger('aliquota_detrazione')->nullable(); // 26 o 30

            // ── Collegamento opzionale a Incasso ───────────────────────────
            $table->foreignId('incasso_id')->nullable()->constrained('incassi')->nullOnDelete();

            // ── Note ───────────────────────────────────────────────────────
            $table->text('note')->nullable();

            $table->timestamps();

            // ── Indici ─────────────────────────────────────────────────────
            $table->index(['tenant_id', 'anno']);
            $table->index(['tenant_id', 'donante_cf']);
            $table->index('is_detraibile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erogazioni_liberali');
    }
};
