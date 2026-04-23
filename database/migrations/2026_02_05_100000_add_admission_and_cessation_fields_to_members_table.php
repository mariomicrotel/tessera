<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parte B: campi ammissione (domanda, decisione CD, rigetto, ricorso, assemblea).
 * Parte C: campi perdita qualità socio ( decesso, morosità, dimissioni, esclusione).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('domanda_presentata_at')->nullable()->after('note');
            $table->date('ammissione_decisa_at')->nullable()->after('domanda_presentata_at');
            $table->string('ammissione_esito', 20)->nullable()->after('ammissione_decisa_at');
            $table->text('rigetto_motivo')->nullable()->after('ammissione_esito');
            $table->date('rigetto_comunicato_at')->nullable()->after('rigetto_motivo');
            $table->date('ricorso_presentato_at')->nullable()->after('rigetto_comunicato_at');
            $table->date('assemblea_esame_data')->nullable()->after('ricorso_presentato_at');

            $table->date('data_cessazione')->nullable()->after('assemblea_esame_data');
            $table->string('cessazione_causa', 20)->nullable()->after('data_cessazione');
            $table->date('dimissioni_presentate_at')->nullable()->after('cessazione_causa');
            $table->text('motivo_esclusione')->nullable()->after('dimissioni_presentate_at');
            $table->date('deceduto_at')->nullable()->after('motivo_esclusione');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'domanda_presentata_at',
                'ammissione_decisa_at',
                'ammissione_esito',
                'rigetto_motivo',
                'rigetto_comunicato_at',
                'ricorso_presentato_at',
                'assemblea_esame_data',
                'data_cessazione',
                'cessazione_causa',
                'dimissioni_presentate_at',
                'motivo_esclusione',
                'deceduto_at',
            ]);
        });
    }
};
