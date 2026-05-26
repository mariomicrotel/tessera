<?php

namespace Database\Seeders;

use App\Models\Protocollo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seed del registro protocollo (inbox/outbox) per la demo
 * "Demo — Cooperativa di Comunità".
 *
 * Genera ~40 voci realistiche (entrata + uscita) distribuite
 * nell'anno corrente, taggabili con [DEMO] per la pulizia.
 *
 * Idempotente: se esistono già voci [DEMO] per questo tenant non aggiunge nulla.
 *
 * Esegui:
 *   php artisan db:seed --class=ProtocolloCoopComunityDemoSeeder
 */
class ProtocolloCoopComunityDemoSeeder extends Seeder
{
    private Tenant $tenant;
    private int    $userId;

    public function run(): void
    {
        $this->tenant = Tenant::where('slug', 'demo-coop-comunita')->first();

        if (! $this->tenant) {
            $this->command->error('Tenant "demo-coop-comunita" non trovato.');
            return;
        }

        // Idempotenza
        $already = Protocollo::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('oggetto', 'LIKE', '%[DEMO]%')
            ->exists();

        if ($already) {
            $this->command->warn('  Protocollo demo già presente per ' . $this->tenant->name . '. Skippo.');
            return;
        }

        // Utente admin del tenant (created_by)
        $user = User::where('email', 'admin-coop_comunita@demo.local')->first();
        $this->userId = $user?->id ?? 1;

        app()->instance('current_tenant', $this->tenant);

        $this->command->info('🌱 Seeding protocollo per: ' . $this->tenant->name);

        $this->seedProtocollo();

        $count = Protocollo::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->command->info("  Done! {$count} voci di protocollo create.");
    }

    private function seedProtocollo(): void
    {
        $anno = now()->year;
        $numero = 1;

        foreach ($this->voci($anno) as $voce) {
            Protocollo::create(array_merge($voce, [
                'tenant_id'          => $this->tenant->id,
                'anno'               => $anno,
                'numero'             => $numero++,
                'created_by'         => $this->userId,
            ]));
        }
    }

    /**
     * Restituisce l'elenco delle voci di protocollo demo.
     * Oggetto sempre suffissato con "[DEMO]" per idempotenza e pulizia.
     */
    private function voci(int $anno): array
    {
        $y = $anno;

        return [
            // ── GENNAIO ─────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 1, 8)->toDateString(),
                'oggetto'            => 'Convenzione per gestione Mensa Scolastica — Comune di Valdoria [DEMO]',
                'mittente'           => 'Comune di Valdoria — Ufficio Servizi Sociali',
                'destinatario'       => null,
                'note'               => 'Allegata bozza convenzione triennale 2026-2028. Da valutare in CdA.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 1, 12)->toDateString(),
                'oggetto'            => 'Accettazione preventivo pulizie sede — Impresa Verdi [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Impresa Verdi di Giovanni Verdi',
                'note'               => 'Contratto annuale pulizie per importo € 4.800 + IVA.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 1, 20)->toDateString(),
                'oggetto'            => 'Richiesta partecipazione bando Regione — Servizi di prossimità [DEMO]',
                'mittente'           => 'Regione — Direzione Welfare',
                'destinatario'       => null,
                'note'               => 'Bando scadenza 28/02. Importo massimo € 50.000. Verificare requisiti.',
            ],

            // ── FEBBRAIO ────────────────────────────────────────────────────
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 2, 3)->toDateString(),
                'oggetto'            => 'Domanda di contributo — Bando Regione Servizi di prossimità [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Regione — Direzione Welfare',
                'note'               => 'Inviata tramite portale regionale. Numero domanda: 2026-REG-07841.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 2, 14)->toDateString(),
                'oggetto'            => 'Comunicazione INPS — Variazione aliquote contributive 2026 [DEMO]',
                'mittente'           => 'INPS — Direzione Regionale',
                'destinatario'       => null,
                'note'               => 'Nuove aliquote per lavoratori dipendenti a tempo parziale. Trasmettere al consulente del lavoro.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 2, 22)->toDateString(),
                'oggetto'            => 'Preventivo ristrutturazione bagno disabili — Studio Tecnico Mori [DEMO]',
                'mittente'           => 'Studio Tecnico Arch. Mori',
                'destinatario'       => null,
                'note'               => 'Importo preventivato € 12.400 + IVA. Valutare co-finanziamento.',
            ],

            // ── MARZO ───────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 3, 5)->toDateString(),
                'oggetto'            => 'Rinnovo convenzione Servizio Trasporto Anziani — Comune di Valdoria [DEMO]',
                'mittente'           => 'Comune di Valdoria — Assessorato alla Persona',
                'destinatario'       => null,
                'note'               => 'Proroga di 12 mesi con adeguamento ISTAT +2,1%.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 3, 10)->toDateString(),
                'oggetto'            => 'Relazione attività anno 2025 — Inviata a Comune di Valdoria [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Comune di Valdoria — Ufficio Politiche Sociali',
                'note'               => 'Relazione annuale con rendiconto ore erogate e beneficiari.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 3, 18)->toDateString(),
                'oggetto'            => 'Verbale ispezione Azienda USL — Servizio di Assistenza Domiciliare [DEMO]',
                'mittente'           => 'Azienda USL — Servizio di Vigilanza',
                'destinatario'       => null,
                'note'               => 'Ispezione senza rilievi. Raccomandazione: aggiornare registro farmaci.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 3, 25)->toDateString(),
                'oggetto'            => 'Proposta rinnovo contratto operatrici sociali — 4 unità [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Studio Consulenza del Lavoro Biondi',
                'note'               => 'Trasmesso elenco lavoratrici e ore contrattuali aggiornate.',
            ],

            // ── APRILE ──────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 4, 2)->toDateString(),
                'oggetto'            => 'Comunicazione Agenzia delle Entrate — Rimborso IVA 2024 [DEMO]',
                'mittente'           => 'Agenzia delle Entrate — Ufficio territoriale',
                'destinatario'       => null,
                'note'               => 'Accredito rimborso € 3.240 entro 60 gg. Codice pratica: 2026-AE-33218.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 4, 9)->toDateString(),
                'oggetto'            => 'Richiesta preventivo gestione asilo nido condominiale — Condominio Le Querce [DEMO]',
                'mittente'           => 'Condominio Le Querce — Amministratore Ferroni',
                'destinatario'       => null,
                'note'               => 'Sopralluogo fissato per 15/04. Potenziale contratto 2 anni.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 4, 16)->toDateString(),
                'oggetto'            => 'Preventivo gestione nido — Condominio Le Querce [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Condominio Le Querce — Amministratore Ferroni',
                'note'               => 'Proposta € 38.000/anno per 5 gg/settimana.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 4, 28)->toDateString(),
                'oggetto'            => 'Segnalazione socio — Disservizio servizio pasti a domicilio [DEMO]',
                'mittente'           => 'Sig.ra Carla Meningoni — Socia n. 0142',
                'destinatario'       => null,
                'note'               => 'Lamentela per ritardi nelle consegne del mese di aprile. Risposta da inviare entro 5 gg.',
            ],

            // ── MAGGIO ──────────────────────────────────────────────────────
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 5, 6)->toDateString(),
                'oggetto'            => 'Risposta formale a segnalazione Sig.ra Meningoni [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Sig.ra Carla Meningoni',
                'note'               => 'Lettera di scuse e piano di miglioramento servizio consegna pasti.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 5, 13)->toDateString(),
                'oggetto'            => 'Accettazione preventivo — Condominio Le Querce [DEMO]',
                'mittente'           => 'Condominio Le Querce — Amministratore Ferroni',
                'destinatario'       => null,
                'note'               => 'Accettato preventivo con piccola modifica: 4,5 gg/settimana € 35.500. Inviare bozza contratto.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 5, 20)->toDateString(),
                'oggetto'            => 'Circolare CCNL Cooperative Sociali — Adeguamento tabelle salariali [DEMO]',
                'mittente'           => 'Confcooperative — Federazione Solidarietà Sociale',
                'destinatario'       => null,
                'note'               => 'Adeguamento 3,2% da 1° giugno 2026. Aggiornare cedolini con consulente.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 5, 27)->toDateString(),
                'oggetto'            => 'Richiesta preventivo assicurazione RCT/RCO — rinnovo [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Broker Assicurativo Lombardi & Associati',
                'note'               => 'Richiesta comparativa per polizza scadente 30/06. Massimale RCT € 2M.',
            ],

            // ── GIUGNO ──────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 6, 3)->toDateString(),
                'oggetto'            => 'Preventivo assicurazione RCT/RCO — Generali SpA [DEMO]',
                'mittente'           => 'Generali SpA — Agenzia Castelli',
                'destinatario'       => null,
                'note'               => 'Premio annuo € 4.180. Confrontare con Zurich.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 6, 5)->toDateString(),
                'oggetto'            => 'Preventivo assicurazione RCT/RCO — Zurich Insurance [DEMO]',
                'mittente'           => 'Zurich Insurance — Ufficio imprese sociali',
                'destinatario'       => null,
                'note'               => 'Premio annuo € 3.920 con franchigia ridotta. Proposta più conveniente.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 6, 12)->toDateString(),
                'oggetto'            => 'Comunicazione esito bando Regione — AMMESSO con riserva [DEMO]',
                'mittente'           => 'Regione — Direzione Welfare',
                'destinatario'       => null,
                'note'               => 'Richiesta documentazione integrativa entro 30 giorni. Codice progetto: RS-2026-148.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 6, 18)->toDateString(),
                'oggetto'            => 'Documentazione integrativa bando Regione RS-2026-148 [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Regione — Direzione Welfare',
                'note'               => 'Trasmessi statuto aggiornato, bilancio 2025 approvato e CV coordinatrice.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 6, 25)->toDateString(),
                'oggetto'            => 'Convocazione Assemblea dei Soci — 15 luglio 2026 [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Tutti i soci — Registro soci n. 1-187',
                'note'               => 'ODG: approvazione rendiconto H1 2026, nomina nuovo consigliere, varie.',
            ],

            // ── LUGLIO ──────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 7, 4)->toDateString(),
                'oggetto'            => 'Richiesta certificato regolarità contributiva — Comune di Valdoria [DEMO]',
                'mittente'           => 'Comune di Valdoria — Ufficio Gare e Contratti',
                'destinatario'       => null,
                'note'               => 'DURC richiesto per rinnovo convenzione. Scadenza attuale: 30/09/2026.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 7, 7)->toDateString(),
                'oggetto'            => 'Trasmissione DURC al Comune di Valdoria [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Comune di Valdoria — Ufficio Gare e Contratti',
                'note'               => 'DURC regolare ottenuto il 06/07. Valido fino al 04/10/2026.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 7, 16)->toDateString(),
                'oggetto'            => 'Comunicazione esito bando — FINANZIATO € 42.000 [DEMO]',
                'mittente'           => 'Regione — Direzione Welfare',
                'destinatario'       => null,
                'note'               => 'Progetto RS-2026-148 finanziato per € 42.000. Prima tranche: € 16.800 entro 30 gg.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 7, 23)->toDateString(),
                'oggetto'            => 'Offerta di collaborazione — Ass. Volontari Territorio [DEMO]',
                'mittente'           => 'Associazione Volontari del Territorio APS',
                'destinatario'       => null,
                'note'               => 'Proposta di co-progettazione servizi di supporto scuolabus. Da valutare.',
            ],

            // ── AGOSTO ──────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 8, 6)->toDateString(),
                'oggetto'            => 'Comunicazione variazione orari apertura sportello — INPS [DEMO]',
                'mittente'           => 'INPS — Sede Territoriale',
                'destinatario'       => null,
                'note'               => 'Chiusura dal 10 al 21 agosto. Affissione in sede e comunicazione a operatori.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 8, 14)->toDateString(),
                'oggetto'            => 'Lettera di intenti — Co-progettazione con Ass. Volontari [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Associazione Volontari del Territorio APS',
                'note'               => 'Manifestazione di interesse a collaborare; proposto tavolo operativo a settembre.',
            ],

            // ── SETTEMBRE ───────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 9, 3)->toDateString(),
                'oggetto'            => 'Accredito prima tranche contributo regionale € 16.800 [DEMO]',
                'mittente'           => 'Regione — Ragioneria Generale',
                'destinatario'       => null,
                'note'               => 'Bonifico ricevuto. Imputare al progetto RS-2026-148.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 9, 10)->toDateString(),
                'oggetto'            => 'Denuncia sinistro — Caduta utente in struttura [DEMO]',
                'mittente'           => 'Generali SpA — Ufficio Sinistri',
                'destinatario'       => null,
                'note'               => 'Apertura sinistro n. 2026-GEN-45512. Raccogliere documentazione medica.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 9, 15)->toDateString(),
                'oggetto'            => 'Documentazione sinistro n. 2026-GEN-45512 — Generali SpA [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Generali SpA — Ufficio Sinistri',
                'note'               => 'Trasmesse: relazione incidente, verbale operatrici, referto PS.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 9, 24)->toDateString(),
                'oggetto'            => 'Richiesta piano formativo — Ente Bilaterale Cooperative [DEMO]',
                'mittente'           => 'Ente Bilaterale Cooperative Sociali',
                'destinatario'       => null,
                'note'               => 'Disponibili voucher formazione obbligatoria per 8 dipendenti. Scadenza: 31/10.',
            ],

            // ── OTTOBRE ─────────────────────────────────────────────────────
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 10, 2)->toDateString(),
                'oggetto'            => 'Piano formativo 2026 — Ente Bilaterale [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Ente Bilaterale Cooperative Sociali',
                'note'               => 'Trasmessi: elenco lavoratori, moduli di adesione e calendario corsi scelti.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 10, 10)->toDateString(),
                'oggetto'            => 'Comunicazione Agenzia delle Entrate — Controllo formale mod. 770 [DEMO]',
                'mittente'           => 'Agenzia delle Entrate — Ufficio controlli',
                'destinatario'       => null,
                'note'               => 'Richiesta chiarimenti su ritenute operate nel 2024. Risposta entro 30 gg.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 10, 22)->toDateString(),
                'oggetto'            => 'Risposta ad Agenzia Entrate — Controllo 770/2024 [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Agenzia delle Entrate — Ufficio controlli',
                'note'               => 'Trasmessa risposta con documentazione contabile a supporto. Redatta con consulente fiscale.',
            ],

            // ── NOVEMBRE ────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 11, 5)->toDateString(),
                'oggetto'            => 'Bando contributi per innovazione sociale — Fondazione del Territorio [DEMO]',
                'mittente'           => 'Fondazione del Territorio',
                'destinatario'       => null,
                'note'               => 'Scadenza domande: 15/12. Importo max € 25.000. Progetto digitale per anziani?',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 11, 12)->toDateString(),
                'oggetto'            => 'Comunicazione liquidazione sinistro — € 4.200 [DEMO]',
                'mittente'           => 'Generali SpA — Ufficio Sinistri',
                'destinatario'       => null,
                'note'               => 'Liquidazione accordata. Accredito entro 15 gg su c/c. Sinistro 2026-GEN-45512 chiuso.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 11, 20)->toDateString(),
                'oggetto'            => 'Proposta convenzione accoglienza tirocini — Università degli Studi [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Università degli Studi — Dipartimento Scienze Sociali',
                'note'               => 'Proposta accoglienza 4 tirocinanti laurea magistrale. Bozza allegata.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 11, 28)->toDateString(),
                'oggetto'            => 'Richiesta rendiconto intermedio progetto RS-2026-148 [DEMO]',
                'mittente'           => 'Regione — Direzione Welfare',
                'destinatario'       => null,
                'note'               => 'Rendiconto da presentare entro 20/12 tramite portale. Contattare commercialista.',
            ],

            // ── DICEMBRE ────────────────────────────────────────────────────
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 12, 3)->toDateString(),
                'oggetto'            => 'Accettazione convenzione tirocini — Università degli Studi [DEMO]',
                'mittente'           => 'Università degli Studi — Ufficio Placement',
                'destinatario'       => null,
                'note'               => 'Convenzione firmata. Inizio tirocini: febbraio 2027. Nominare referente aziendale.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 12, 10)->toDateString(),
                'oggetto'            => 'Rendiconto intermedio progetto RS-2026-148 — trasmissione [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Regione — Direzione Welfare',
                'note'               => 'Caricato su portale regionale. Spese rendicontate: € 28.400. Protocollato numero portale: 9947.',
            ],
            [
                'tipo'               => 'uscita',
                'data_registrazione' => Carbon::create($y, 12, 15)->toDateString(),
                'oggetto'            => 'Domanda contributo Fondazione del Territorio — Progetto Digitale Anziani [DEMO]',
                'mittente'           => null,
                'destinatario'       => 'Fondazione del Territorio',
                'note'               => 'Presentata domanda per € 18.500. Progetto "Nonni Connessi" allegato.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 12, 19)->toDateString(),
                'oggetto'            => 'Comunicazione proroga convenzione mensa scolastica — anno 2027 [DEMO]',
                'mittente'           => 'Comune di Valdoria — Ufficio Servizi Sociali',
                'destinatario'       => null,
                'note'               => 'Confermata proroga per anno scolastico 2026/27 alle stesse condizioni.',
            ],
            [
                'tipo'               => 'entrata',
                'data_registrazione' => Carbon::create($y, 12, 23)->toDateString(),
                'oggetto'            => 'Auguri e comunicazione chiusura festività — Confcooperative [DEMO]',
                'mittente'           => 'Confcooperative — Presidenza Nazionale',
                'destinatario'       => null,
                'note'               => 'Circolare con calendario chiusure uffici e numero emergenze.',
            ],
        ];
    }
}
