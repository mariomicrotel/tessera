<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Righe dei movimenti contabili (scritture dare/avere).
 *
 * Ogni riga registra l'addebito (dare) o l'accredito (avere) su un conto
 * movimentabile (livello 4 del piano dei conti).
 *
 * Regole:
 *  - `importo_dare` e `importo_avere` sono mutualmente esclusivi
 *    (almeno uno = 0, l'altro > 0) → enforcement via service
 *  - Il conto deve essere movimentabile (`conti_contabili.movimentabile = true`)
 *  - Per ogni movimento: SUM(importo_dare) = SUM(importo_avere)
 *
 * Il campo `gestione` permette di sovrascrivere a livello di riga la
 * gestione istituzionale/commerciale definita in testata (utile per
 * movimenti misti nelle cooperative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('righe_movimento_contabile', function (Blueprint $table) {
            $table->id();

            $table->foreignId('movimento_id')
                ->constrained('movimenti_contabili')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('ordine')->default(1);  // ordine visivo

            $table->foreignId('conto_contabile_id')
                ->constrained('conti_contabili')
                ->restrictOnDelete();

            $table->string('descrizione', 250)->nullable();

            // Importi — mutualmente esclusivi per riga
            $table->decimal('importo_dare', 15, 2)->default(0);
            $table->decimal('importo_avere', 15, 2)->default(0);

            // Contabilità analitica (opzionale)
            $table->string('centro_costo', 50)->nullable();

            // Override gestione a livello di riga (cooperative)
            $table->string('gestione', 20)->nullable();
            // valori: istituzionale, commerciale

            $table->timestamps();

            // Indici di ricerca / saldi
            $table->index(['movimento_id', 'ordine']);
            $table->index('conto_contabile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('righe_movimento_contabile');
    }
};
