<?php

namespace Database\Seeders;

use App\Models\EtsComplianceRule;
use Illuminate\Database\Seeder;

/**
 * Catalogo regole di compliance ETS.
 *
 * Basato su:
 *  - D.Lgs. 117/2017 (Codice del Terzo Settore)
 *  - D.M. 39/2020 (Modelli bilancio A/B/C/D)
 *  - D.M. 5/3/2020 (ETS-D Rendiconto)
 *  - L. 124/2017 artt. 1 c. 125-129 (trasparenza)
 *  - Circ. Min. 6/2026
 *
 * Idempotente: usa updateOrCreate sul codice.
 */
class EtsComplianceRulesSeeder extends Seeder
{
    public function run(): void
    {
        $formeEts = ['ets_odv', 'ets_aps', 'ets_fondazione', 'ets_generico'];
        $formeConVolontari = ['ets_odv', 'ets_aps'];

        $regole = [
            // ── Statuto ─────────────────────────────────────────────────────
            [
                'codice'             => 'CTS001',
                'titolo'             => 'Statuto approvato',
                'descrizione'        => 'L\'ETS deve disporre di uno statuto approvato dall\'assemblea o organo competente (art. 21 CTS).',
                'categoria'          => 'statuto',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'documento',
                'check_config'       => ['modello' => 'EtsStatuto', 'stato' => 'approvato', 'suggerimento' => 'Creare e approvare lo statuto nella sezione Ets → Statuto.'],
                'ordine'             => 10,
            ],
            [
                'codice'             => 'CTS002',
                'titolo'             => 'Data deposito statuto',
                'descrizione'        => 'Lo statuto approvato dovrebbe essere depositato presso il notaio o il registro.',
                'categoria'          => 'statuto',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'documento',
                'check_config'       => ['modello' => 'EtsStatuto', 'stato' => 'approvato', 'suggerimento' => 'Compilare la data di deposito nello statuto.'],
                'ordine'             => 15,
            ],

            // ── Atto costitutivo ────────────────────────────────────────────
            [
                'codice'             => 'CTA001',
                'titolo'             => 'Atto costitutivo registrato',
                'descrizione'        => 'L\'atto costitutivo deve essere registrato all\'Agenzia delle Entrate (art. 21 c.1 CTS).',
                'categoria'          => 'atto',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'documento',
                'check_config'       => ['modello' => 'EtsAttoCostituivo', 'stato' => 'registrato', 'suggerimento' => 'Registrare l\'atto costitutivo nella sezione Ets → Atto Costitutivo.'],
                'ordine'             => 20,
            ],
            [
                'codice'             => 'CTA002',
                'titolo'             => 'Dati notarili atto',
                'descrizione'        => 'Notaio e numero di repertorio dovrebbero essere compilati per atti notarili.',
                'categoria'          => 'atto',
                'forme_applicabili'  => ['ets_fondazione'],
                'severita'           => 'avviso',
                'check_type'         => 'documento',
                'check_config'       => ['modello' => 'EtsAttoCostituivo', 'stato' => 'registrato', 'suggerimento' => 'Completare i dati notarili nell\'atto costitutivo.'],
                'ordine'             => 25,
            ],

            // ── RUNTS ────────────────────────────────────────────────────────
            [
                'codice'             => 'CTR001',
                'titolo'             => 'Numero RUNTS compilato',
                'descrizione'        => 'Il numero di repertorio RUNTS è obbligatorio dopo l\'iscrizione (art. 45 CTS).',
                'categoria'          => 'runts',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'runts_numero', 'suggerimento' => 'Inserire il numero RUNTS nel wizard di configurazione.'],
                'ordine'             => 30,
            ],
            [
                'codice'             => 'CTR002',
                'titolo'             => 'Sezione RUNTS compilata',
                'descrizione'        => 'La sezione RUNTS (a-g) deve essere specificata (art. 46 CTS).',
                'categoria'          => 'runts',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'runts_sezione', 'suggerimento' => 'Specificare la sezione RUNTS nel wizard di configurazione.'],
                'ordine'             => 35,
            ],
            [
                'codice'             => 'CTR003',
                'titolo'             => 'Data iscrizione RUNTS',
                'descrizione'        => 'La data di iscrizione al RUNTS deve essere registrata.',
                'categoria'          => 'runts',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'runts_data_iscrizione', 'suggerimento' => 'Inserire la data di iscrizione RUNTS nel wizard.'],
                'ordine'             => 40,
            ],

            // ── Governance ───────────────────────────────────────────────────
            [
                'codice'             => 'CTG001',
                'titolo'             => 'Organo direttivo nominato',
                'descrizione'        => 'Il Consiglio Direttivo (o equivalente) deve essere nominato e le cariche attribuite (art. 26 CTS).',
                'categoria'          => 'governance',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'manuale',
                'check_config'       => ['suggerimento' => 'Verificare che le cariche del Consiglio Direttivo siano configurate nella sezione Organi.'],
                'ordine'             => 50,
            ],
            [
                'codice'             => 'CTG002',
                'titolo'             => 'Organo di controllo (> €220k)',
                'descrizione'        => 'Gli ETS con entrate > €220.000 devono nominare un revisore o collegio sindacale (art. 30 CTS).',
                'categoria'          => 'governance',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'manuale',
                'check_config'       => ['suggerimento' => 'Se le entrate superano €220.000, verificare la nomina dell\'organo di controllo.'],
                'ordine'             => 55,
            ],

            // ── Contabilità ──────────────────────────────────────────────────
            [
                'codice'             => 'CTC001',
                'titolo'             => 'Schema bilancio configurato',
                'descrizione'        => 'La dimensione del bilancio deve essere configurata per applicare lo schema corretto (D.M. 5/3/2020).',
                'categoria'          => 'contabilita',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'dimensione_bilancio', 'suggerimento' => 'Configurare la dimensione del bilancio nel wizard.'],
                'ordine'             => 60,
            ],
            [
                'codice'             => 'CTC002',
                'titolo'             => 'Almeno un ambito art. 5 CTS',
                'descrizione'        => 'L\'ETS deve svolgere almeno una delle 26 attività di interesse generale (art. 5 D.Lgs. 117/2017).',
                'categoria'          => 'contabilita',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'errore',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'ambiti_attivita', 'suggerimento' => 'Selezionare almeno un ambito di interesse generale nel wizard ETS.'],
                'ordine'             => 65,
            ],
            [
                'codice'             => 'CTC003',
                'titolo'             => 'Attività principale specificata',
                'descrizione'        => 'Deve essere identificata l\'attività di interesse generale principale.',
                'categoria'          => 'contabilita',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'attivita_principale', 'suggerimento' => 'Specificare l\'attività principale tra quelle selezionate.'],
                'ordine'             => 70,
            ],
            [
                'codice'             => 'CTC004',
                'titolo'             => 'Fascia entrate configurata',
                'descrizione'        => 'La fascia di entrate annue è necessaria per determinare obblighi di bilancio e governance.',
                'categoria'          => 'contabilita',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'fascia_entrate', 'suggerimento' => 'Selezionare la fascia di entrate annue nel wizard ETS.'],
                'ordine'             => 75,
            ],

            // ── Volontari ────────────────────────────────────────────────────
            [
                'codice'             => 'CTV001',
                'titolo'             => 'Polizza assicurativa volontari',
                'descrizione'        => 'OdV e APS devono stipulare una polizza assicurativa per i volontari (art. 18 D.Lgs. 117/2017).',
                'categoria'          => 'volontari',
                'forme_applicabili'  => $formeConVolontari,
                'severita'           => 'errore',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'assicurazione_volontari_polizza', 'suggerimento' => 'Inserire il numero di polizza assicurativa nel wizard ETS.'],
                'ordine'             => 80,
            ],
            [
                'codice'             => 'CTV002',
                'titolo'             => 'Scadenza polizza volontari',
                'descrizione'        => 'La polizza assicurativa volontari non deve essere scaduta (art. 18 CTS).',
                'categoria'          => 'volontari',
                'forme_applicabili'  => $formeConVolontari,
                'severita'           => 'errore',
                'check_type'         => 'scadenza',
                'check_config'       => [
                    'campo'                      => 'assicurazione_volontari_scadenza',
                    'giorni_preavviso'            => 60,
                    'suggerimento'                => 'Inserire la data di scadenza della polizza.',
                    'suggerimento_scaduto'         => 'Rinnovare immediatamente la polizza assicurativa.',
                    'suggerimento_in_scadenza'     => 'Procedere al rinnovo della polizza prima della scadenza.',
                ],
                'ordine'             => 85,
            ],

            // ── Trasparenza ──────────────────────────────────────────────────
            [
                'codice'             => 'CTT001',
                'titolo'             => 'Pubblicazione bilancio online',
                'descrizione'        => 'ETS con entrate > €220.000 devono pubblicare il bilancio sul sito istituzionale (L. 124/2017 art. 1 c. 125).',
                'categoria'          => 'trasparenza',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'presenza',
                'check_config'       => ['campo' => 'bilancio_url_pubblicazione', 'suggerimento' => 'Indicare l\'URL dove è pubblicato il bilancio.'],
                'ordine'             => 90,
            ],
            [
                'codice'             => 'CTT002',
                'titolo'             => 'Personalità giuridica — patrimonio minimo',
                'descrizione'        => 'Per ottenere la personalità giuridica: associazioni €15.000, fondazioni €30.000 (art. 22 c.4 CTS).',
                'categoria'          => 'trasparenza',
                'forme_applicabili'  => $formeEts,
                'severita'           => 'avviso',
                'check_type'         => 'manuale',
                'check_config'       => ['suggerimento' => 'Se l\'ente ha acquisito la personalità giuridica, verificare il patrimonio minimo ex art. 22 c.4 CTS.'],
                'ordine'             => 95,
            ],
        ];

        foreach ($regole as $regola) {
            EtsComplianceRule::updateOrCreate(
                ['codice' => $regola['codice']],
                $regola
            );
        }

        $this->command->info('✔  EtsComplianceRulesSeeder: '.count($regole).' regole caricate.');
    }
}
