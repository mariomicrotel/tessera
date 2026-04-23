<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testata dei movimenti contabili (giornale della partita doppia).
 *
 * Ogni movimento è composto da:
 *  - una testata (questa tabella): data, causale, stato, riferimento documento
 *  - N righe (tabella righe_movimento_contabile): conto, dare, avere
 *
 * Invariante fondamentale: SUM(righe.importo_dare) = SUM(righe.importo_avere)
 * → enforcement a livello service (MovimentoContabileService).
 *
 * Gli stati:
 *  - bozza     → modificabile, non impatta saldi
 *  - definitivo → saldi aggiornati, modificabile solo tramite storno
 *  - stornato  → annullato da un movimento di storno (sola lettura)
 *
 * Il flag `locked` blocca ogni modifica (esercizio chiuso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimenti_contabili', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            // Numerazione progressiva per esercizio
            $table->unsignedSmallInteger('anno_esercizio');
            $table->unsignedInteger('numero');        // progressivo 1, 2, … all'interno dell'anno

            // Date
            $table->date('data_registrazione');
            $table->date('data_competenza')->nullable();

            // Causale
            $table->foreignId('causale_id')
                ->constrained('causali_contabili')
                ->restrictOnDelete();

            $table->string('descrizione', 250);

            // Gestione separata istituzionale/commerciale (cooperative)
            $table->string('gestione', 20)->nullable();
            // valori: istituzionale, commerciale

            // Stato del movimento
            $table->string('stato', 20)->default('bozza');
            // valori: bozza, definitivo, stornato

            // Bloccato da chiusura esercizio
            $table->boolean('locked')->default(false);

            // Riferimento documento esterno (fattura, ricevuta, ecc.)
            $table->string('numero_documento', 50)->nullable();
            $table->date('data_documento')->nullable();

            // Collegamento storno: il movimento di storno punta all'originale
            $table->foreignId('movimento_origine_id')
                ->nullable()
                ->constrained('movimenti_contabili')
                ->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            // Chiave naturale: numero univoco per tenant + anno
            $table->unique(['tenant_id', 'anno_esercizio', 'numero']);

            // Indici di ricerca frequenti
            $table->index(['tenant_id', 'anno_esercizio', 'data_registrazione'], 'mov_cont_tenant_anno_data_idx');
            $table->index(['tenant_id', 'stato'],            'mov_cont_tenant_stato_idx');
            $table->index(['tenant_id', 'causale_id'],       'mov_cont_tenant_causale_idx');
            $table->index(['tenant_id', 'numero_documento'], 'mov_cont_tenant_num_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimenti_contabili');
    }
};
