<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge al Tenant i campi richiesti dalla compliance ETS:
 *  - RUNTS: numero, sezione, data iscrizione, personalità giuridica, patrimonio minimo
 *  - Attività: ambiti di interesse generale (art. 5 CTS), entrate annue categoria
 *  - Volontari: polizza assicurativa (art. 18 CTS)
 *  - Trasparenza: pubblicazione bilancio
 *
 * Riferimenti normativi:
 *  - D.Lgs. 117/2017 (Codice del Terzo Settore)
 *  - D.M. 39/2020 (Modelli A/B/C/D)
 *  - L. 124/2017 art. 1 c. 125-129 (trasparenza)
 *  - Circ. min. 6/2026 (rendiconto cassa aggregato)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // ── RUNTS ────────────────────────────────────────────────────
            $table->string('runts_numero', 30)->nullable()->after('numero_iscrizione_albo_coop')
                ->comment('Numero di repertorio RUNTS (es. 12345)');

            $table->enum('runts_sezione', [
                'a',  // Organizzazioni di Volontariato (OdV)
                'b',  // Associazioni di Promozione Sociale (APS)
                'c',  // Enti Filantropici
                'd',  // Imprese sociali (incluse cooperative sociali)
                'e',  // Reti associative
                'f',  // Società di mutuo soccorso
                'g',  // Altri enti del Terzo settore
            ])->nullable()->after('runts_numero')
                ->comment('Sezione del RUNTS (art. 46 D.Lgs. 117/2017)');

            $table->date('runts_data_iscrizione')->nullable()->after('runts_sezione');

            $table->boolean('personalita_giuridica')->default(false)->after('runts_data_iscrizione')
                ->comment('Personalità giuridica acquisita ex art. 22 CTS (richiede patrimonio minimo)');

            $table->decimal('patrimonio_destinato', 12, 2)->nullable()->after('personalita_giuridica')
                ->comment('Patrimonio destinato per personalità giuridica (€15k associazioni, €30k fondazioni)');

            // ── Attività di interesse generale (art. 5 CTS) ──────────────
            $table->json('ambiti_attivita')->nullable()->after('patrimonio_destinato')
                ->comment('Array di lettere a-z degli ambiti art. 5 CTS (es. ["a","b","i"])');

            $table->string('attivita_principale', 5)->nullable()->after('ambiti_attivita')
                ->comment('Lettera dell\'ambito principale (es. "a" per servizi sociali)');

            // ── Entrate e dimensione (per soglie bilancio/organi controllo) ──
            $table->enum('fascia_entrate', [
                'sotto_60k',     // < €60.000
                'sotto_220k',    // €60k - €220k → rendiconto cassa
                'sotto_1m',      // €220k - €1M → SP + Rendiconto Gestionale
                'sopra_1m',      // > €1M → bilancio sociale obbligatorio
            ])->nullable()->after('attivita_principale')
                ->comment('Fascia entrate annue per soglie normative');

            // ── Volontari (artt. 17-18 CTS) ──────────────────────────────
            $table->string('assicurazione_volontari_polizza', 50)->nullable()->after('fascia_entrate')
                ->comment('Numero polizza assicurativa volontari (obbligo art. 18)');

            $table->string('assicurazione_volontari_compagnia', 100)->nullable()->after('assicurazione_volontari_polizza');

            $table->date('assicurazione_volontari_scadenza')->nullable()->after('assicurazione_volontari_compagnia');

            // ── Trasparenza (L. 124/2017) ────────────────────────────────
            $table->string('bilancio_url_pubblicazione', 500)->nullable()->after('assicurazione_volontari_scadenza')
                ->comment('URL pubblico dove è pubblicato il bilancio (obbligo trasparenza)');

            // Indici utili
            $table->index('runts_sezione');
            $table->index('fascia_entrate');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['runts_sezione']);
            $table->dropIndex(['fascia_entrate']);
            $table->dropColumn([
                'runts_numero',
                'runts_sezione',
                'runts_data_iscrizione',
                'personalita_giuridica',
                'patrimonio_destinato',
                'ambiti_attivita',
                'attivita_principale',
                'fascia_entrate',
                'assicurazione_volontari_polizza',
                'assicurazione_volontari_compagnia',
                'assicurazione_volontari_scadenza',
                'bilancio_url_pubblicazione',
            ]);
        });
    }
};
