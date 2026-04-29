<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W1 — Onboarding Wizard: estende la tabella tenants con i campi necessari
 * per la profilazione completa dell'azienda (forma giuridica, dimensione,
 * regimi fiscali/contabili, dati anagrafici estesi, tracciamento wizard).
 *
 * Backward-compatible: i campi organization_type e cooperative_type
 * esistenti restano intatti. I nuovi campi affiancano la struttura precedente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {

            // ── Forma giuridica completa ──────────────────────────────────
            $table->enum('forma_giuridica', [
                // ETS
                'ets_odv',          // Organizzazione di Volontariato (D.Lgs. 117/2017)
                'ets_aps',          // Associazione di Promozione Sociale
                'ets_fondazione',   // Fondazione ETS
                'ets_generico',     // Altro ETS (incluse ONLUS in transizione)
                // Cooperative
                'coop_lavoro',
                'coop_sociale_a',
                'coop_sociale_b',
                'coop_agricola',
                'coop_consortile',
                'coop_consumo',
                'coop_abitazione',
                'coop_comunita',
                // Società di capitali
                'srl',              // Società a Responsabilità Limitata
                'srls',             // S.r.l. Semplificata
                'spa',              // Società per Azioni
                'sapa',             // Società in Accomandita per Azioni
                // Società di persone
                'sas',              // Società in Accomandita Semplice
                'snc',              // Società in Nome Collettivo
                'ss',               // Società Semplice
                // Autonomi
                'ditta_individuale',
                'libero_professionista',
                'studio_professionale',
                // Enti non commerciali non ETS
                'associazione_non_ets',
                'fondazione_non_ets',
                // Regimi agevolati
                'forfettario',
                // Altro
                'altro',
            ])->nullable()->after('cooperative_type');

            // ── Dimensione bilancio ───────────────────────────────────────
            // Determina lo schema SP/CE applicabile secondo Codice Civile
            $table->enum('dimensione_bilancio', [
                'micro',          // art. 2435-ter c.c. (superano 1 solo limite)
                'abbreviato',     // art. 2435-bis c.c. (superano 2 limiti su 3)
                'ordinario_cee',  // bilancio ordinario completo CEE
                'ets_d',          // D.M. 5/3/2020 (Moduli A/B/C/D) per ETS
                'cooperativa',    // CEE + sezioni cooperative specifiche
                'non_applicabile',// forfettari, autonomi minimi, SS
            ])->default('non_applicabile')->after('forma_giuridica');

            // ── Regime contabile ──────────────────────────────────────────
            $table->enum('regime_contabile', [
                'ordinario',       // contabilità ordinaria (doppioentrata)
                'semplificato',    // art. 18 DPR 600/73 (< soglie ricavi)
                'forfettario',     // L. 190/2014 (sostitutiva 15%/5%)
                'non_applicabile', // SS, enti non commerciali puri
            ])->default('non_applicabile')->after('dimensione_bilancio');

            // ── Regime IVA ────────────────────────────────────────────────
            $table->enum('regime_iva', [
                'ordinario',       // regime normale
                'forfettario',     // escluso IVA (L. 190/2014)
                'agricolo',        // art. 34 DPR 633/72
                'margine',         // beni usati D.L. 41/1995
                'editoria',        // L. 62/2001
                'esente',          // attività esenti (art. 10)
                'non_applicabile', // enti non commerciali privi di P.IVA
            ])->default('non_applicabile')->after('regime_contabile');

            // ── Codice ATECO ──────────────────────────────────────────────
            $table->string('attivita_ateco', 10)->nullable()->after('regime_iva')
                ->comment('Codice ATECO 2007 dell\'attività prevalente (es. 47.11.10)');

            // ── Tracciamento wizard onboarding ────────────────────────────
            $table->timestamp('wizard_completato_at')->nullable()->after('attivita_ateco');
            $table->unsignedTinyInteger('wizard_step_corrente')->default(0)->after('wizard_completato_at');

            // ── Dati anagrafici estesi ────────────────────────────────────
            $table->string('pec', 255)->nullable()->after('wizard_step_corrente');
            $table->string('rea_numero', 20)->nullable();
            $table->string('rea_citta', 100)->nullable();
            $table->string('indirizzo', 255)->nullable();
            $table->string('cap', 10)->nullable();
            $table->string('citta', 100)->nullable();
            $table->string('provincia', 2)->nullable();
            $table->string('nazione', 2)->default('IT')->after('provincia');
            $table->string('telefono', 20)->nullable();
            $table->string('sito_web', 255)->nullable();

            // ── Indici ────────────────────────────────────────────────────
            $table->index('forma_giuridica');
            $table->index('dimensione_bilancio');
            $table->index('wizard_completato_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['forma_giuridica']);
            $table->dropIndex(['dimensione_bilancio']);
            $table->dropIndex(['wizard_completato_at']);
            $table->dropColumn([
                'forma_giuridica',
                'dimensione_bilancio',
                'regime_contabile',
                'regime_iva',
                'attivita_ateco',
                'wizard_completato_at',
                'wizard_step_corrente',
                'pec',
                'rea_numero',
                'rea_citta',
                'indirizzo',
                'cap',
                'citta',
                'provincia',
                'nazione',
                'telefono',
                'sito_web',
            ]);
        });
    }
};
