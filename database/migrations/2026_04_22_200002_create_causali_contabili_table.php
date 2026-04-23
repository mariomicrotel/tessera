<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Causali contabili (modelli di scrittura).
 *
 * Una causale classifica il tipo di movimento contabile e può suggerire
 * automaticamente i conti di contropartita più comuni (es. causale
 * "Fattura di acquisto" → contropartita default = Debiti v/fornitori).
 *
 * Le causali `di_sistema = true` sono predefinite dal template cooperativa
 * e non eliminabili, ma possono essere disattivate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('causali_contabili', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('codice', 10);
            $table->string('descrizione', 150);

            // Classificazione macroscopica del tipo di operazione
            $table->string('tipo', 30)->default('generico');
            // valori: generico, fattura_acquisto, fattura_vendita,
            //         incasso, pagamento, giroconto,
            //         ammortamento, stipendi, apertura, chiusura

            // Conto di contropartita suggerito (es. Debiti v/fornitori)
            $table->foreignId('conto_contropartita_default_id')
                ->nullable()
                ->constrained('conti_contabili')
                ->nullOnDelete();

            $table->boolean('di_sistema')->default(false);
            $table->boolean('attivo')->default(true);

            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'codice']);
            $table->index(['tenant_id', 'attivo']);
            $table->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('causali_contabili');
    }
};
