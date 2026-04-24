<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scadenze')) {
            return;
        }

        Schema::create('scadenze', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Classificazione
            $table->enum('tipo', [
                'fiscale',      // IVA, IRPEF, INPS, IRAP, …
                'contributi',   // INPS artigiani/commercianti, ENASARCO
                'affitto',      // canoni passivi
                'assicurazione',
                'noleggio',
                'abbonamento',
                'altra',        // generica
            ])->default('altra');

            $table->string('descrizione');
            $table->decimal('importo', 12, 2)->nullable();
            $table->date('data_scadenza');
            $table->date('data_pagamento')->nullable();

            $table->enum('stato', ['aperta', 'pagata', 'sospesa'])->default('aperta');

            // Riferimento libero (es. "F24 – cod. tributo 6012", "Contratto n.42")
            $table->string('riferimento')->nullable();

            // Eventuale conto contabile usato per il pagamento
            $table->unsignedBigInteger('conto_id')->nullable()->index();

            // Ricorrenza annuale
            $table->boolean('ricorrente')->default(false);
            $table->date('origine_anno_precedente')->nullable(); // data originale se ripresa

            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('conto_id')->references('id')->on('conti')->nullOnDelete();

            $table->index(['tenant_id', 'data_scadenza']);
            $table->index(['tenant_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scadenze');
    }
};
