<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relazioni_missione', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Periodo di riferimento
            $table->unsignedSmallInteger('anno');

            // Stato
            $table->string('stato', 20)->default('bozza'); // bozza | definitiva | approvata

            // Dati dell'assemblea che ha approvato il documento
            $table->string('organo_approvante', 200)->nullable(); // es. "Assemblea dei soci"
            $table->date('data_approvazione')->nullable();
            $table->string('luogo_approvazione', 200)->nullable();

            // Sezioni come JSON array di { titolo, testo }
            // Struttura conforme all'art. 13 D.Lgs. 117/2017
            $table->json('sezioni')->nullable();

            // Variabili automatiche snapshot al momento del salvataggio
            $table->json('variabili_snapshot')->nullable();

            // Note interne (non stampate)
            $table->text('note_interne')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'anno'], 'relazioni_tenant_anno_idx');
            $table->unique(['tenant_id', 'anno'], 'relazioni_tenant_anno_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relazioni_missione');
    }
};
