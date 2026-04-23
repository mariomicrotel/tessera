<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende la tabella members per supportare soci persone giuridiche (cooperative)
 * e aggiunge campi specifici della gestione cooperativa.
 *
 * Nuovi campi:
 * - tipo_persona: 'fisica' (default) | 'giuridica'
 * - ragione_sociale: nome dell'azienda/ente se persona giuridica
 * - partita_iva: numero partita IVA
 * - referente_nome/cognome: contatto per persona giuridica
 * - socio_lavoratore: indicatore per coop. di lavoro
 * - socio_sovventore: socio che solo investe
 * - socio_onorario: socio onorario
 * - data_ammissione_cda: data di approvazione CDA (distinta da data_iscrizione)
 * - numero_quote_capitale: numero quote sottoscritte (aggiornato automaticamente)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Dopo tenant_id: tipo di persona
            $table->enum('tipo_persona', ['fisica', 'giuridica'])
                ->default('fisica')
                ->after('tenant_id');

            // Dopo cognome: dati persona giuridica
            $table->string('ragione_sociale', 200)
                ->nullable()
                ->after('cognome');

            $table->string('partita_iva', 11)
                ->nullable()
                ->after('ragione_sociale');

            $table->string('referente_nome', 100)
                ->nullable()
                ->after('partita_iva');

            $table->string('referente_cognome', 100)
                ->nullable()
                ->after('referente_nome');

            // Flag per tipologie soci cooperativa
            $table->boolean('socio_lavoratore')
                ->default(false)
                ->after('referente_cognome');

            $table->boolean('socio_sovventore')
                ->default(false)
                ->after('socio_lavoratore');

            $table->boolean('socio_onorario')
                ->default(false)
                ->after('socio_sovventore');

            // Data approvazione CDA (distinta da data_iscrizione)
            $table->date('data_ammissione_cda')
                ->nullable()
                ->after('socio_onorario');

            // Numero quote di capitale (per cooperative)
            $table->unsignedInteger('numero_quote_capitale')
                ->default(0)
                ->after('data_ammissione_cda');

            // Indici
            $table->index(['tenant_id', 'tipo_persona']);
            $table->index(['tenant_id', 'socio_lavoratore']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'socio_lavoratore']);
            $table->dropIndex(['tenant_id', 'tipo_persona']);

            $table->dropColumn([
                'tipo_persona',
                'ragione_sociale',
                'partita_iva',
                'referente_nome',
                'referente_cognome',
                'socio_lavoratore',
                'socio_sovventore',
                'socio_onorario',
                'data_ammissione_cda',
                'numero_quote_capitale',
            ]);
        });
    }
};
