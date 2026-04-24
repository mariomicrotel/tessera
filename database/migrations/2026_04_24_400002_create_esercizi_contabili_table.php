<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('esercizi_contabili')) {
            return;
        }

        Schema::create('esercizi_contabili', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            $table->smallInteger('anno')->unsigned();

            $table->enum('stato', ['aperto', 'chiuso'])->default('aperto');

            $table->date('data_apertura')->nullable();
            $table->date('data_chiusura')->nullable();

            // Conti di contropartita usati nelle scritture di chiusura/apertura.
            // - conto_chiusura_ce_id: conto transitorio che raccoglie il risultato CE
            //   (es. "Riepilogo Conto Economico" o "Utile/Perdita d'esercizio")
            // - conto_apertura_id: conto transitorio usato per bilanciare chiusura/apertura SP
            //   (es. "Conto Patrimoniale di Apertura")
            $table->unsignedBigInteger('conto_chiusura_ce_id')->nullable();
            $table->unsignedBigInteger('conto_apertura_id')->nullable();

            // Movimenti generati dalla procedura di chiusura/apertura.
            // Vengono memorizzati per poter annullare (riapri esercizio).
            $table->unsignedBigInteger('movimento_chiusura_ce_id')->nullable();
            $table->unsignedBigInteger('movimento_chiusura_sp_id')->nullable();
            $table->unsignedBigInteger('movimento_apertura_id')->nullable();

            $table->timestamp('locked_at')->nullable(); // quando è stato bloccato
            $table->text('note')->nullable();

            $table->timestamps();

            // Un solo esercizio per tenant per anno
            $table->unique(['tenant_id', 'anno']);

            $table->foreign('conto_chiusura_ce_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();
            $table->foreign('conto_apertura_id')
                ->references('id')->on('conti_contabili')->nullOnDelete();
            $table->foreign('movimento_chiusura_ce_id')
                ->references('id')->on('movimenti_contabili')->nullOnDelete();
            $table->foreign('movimento_chiusura_sp_id')
                ->references('id')->on('movimenti_contabili')->nullOnDelete();
            $table->foreign('movimento_apertura_id')
                ->references('id')->on('movimenti_contabili')->nullOnDelete();

            $table->index(['tenant_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esercizi_contabili');
    }
};
