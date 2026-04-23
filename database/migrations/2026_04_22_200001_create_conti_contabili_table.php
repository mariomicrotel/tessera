<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella del piano dei conti contabili (Chart of Accounts).
 *
 * Struttura gerarchica a 4 livelli con codifica decimale parlante italiana:
 *   Classe (1)   → Mastro (2)   → Conto (3)      → Sottoconto (4)
 *   "1"          → "1.10"       → "1.10.05"      → "1.10.05.001"
 *
 * Solo i sottoconti (livello 4) sono movimentabili nelle scritture contabili;
 * i livelli superiori sono aggregativi e usati dal reporting (SP/CE).
 *
 * Multi-tenant: ogni tenant ha il proprio piano dei conti.
 * I conti `di_sistema = true` sono del template cooperativa e non eliminabili.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conti_contabili', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            // Codifica e gerarchia
            $table->string('codice', 20);              // "1.10.05.001"
            $table->string('descrizione', 200);
            $table->foreignId('parent_id')->nullable()
                ->constrained('conti_contabili')->nullOnDelete();
            $table->unsignedTinyInteger('livello');     // 1 = classe, 4 = sottoconto

            // Classificazione contabile
            $table->string('natura', 30);
            // valori: attivo, passivo, patrimonio_netto, costo, ricavo, conto_ordine, transitorio
            $table->string('segno_naturale', 10);
            // valori: dare, avere

            // Classificazione bilancio (CEE ex artt. 2424/2425 c.c.)
            $table->string('classe_bilancio_ce', 30)->nullable();
            // es. "B.II.1", "C.I.1", "A.1", ecc.
            $table->string('tipo_bilancio', 40)->nullable();
            // valori: sp_attivo, sp_passivo, pn,
            //         ce_valore_produzione, ce_costi_produzione,
            //         ce_proventi_oneri_finanziari, ce_rettifiche_finanziarie,
            //         ce_imposte, conto_ordine, transitorio

            // Flag operativi
            $table->boolean('movimentabile')->default(false);  // solo livello 4
            $table->boolean('di_sistema')->default(false);     // template ufficiale
            $table->boolean('attivo')->default(true);

            // Cooperative: separazione istituzionale/commerciale
            $table->string('gestione_default', 20)->nullable();
            // valori: istituzionale, commerciale

            // IVA default per facilitare la registrazione
            $table->foreignId('codice_iva_default_id')->nullable()
                ->constrained('codici_iva')->nullOnDelete();

            // Mapping → voci MOD_D ETS (per derivare rendiconto cooperative)
            $table->string('rendiconto_code_map', 30)->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            // Indici
            $table->unique(['tenant_id', 'codice']);
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'livello', 'attivo']);
            $table->index(['tenant_id', 'natura']);
            $table->index(['tenant_id', 'movimentabile']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conti_contabili');
    }
};
