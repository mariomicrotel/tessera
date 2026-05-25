<?php

namespace Database\Seeders;

use App\Models\AdempimentoTemplate;
use Illuminate\Database\Seeder;

/**
 * Catalogo dei principali adempimenti fiscali / amministrativi per ETS e
 * cooperative italiane. Idempotente per codice.
 *
 * Riferimenti normativi citati per ogni adempimento. Date e scadenze ricontrolliate
 * dalla circolare AdE / TUIR vigente a maggio 2026.
 *
 * NOTA: La logica di applicabilità in regime forfettario / 398 e altre
 * casistiche specifiche è gestita poi a livello di items via stato
 * 'non_applicabile' impostato dal consulente.
 */
class AdempimentoTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = $this->definizioni();
        $created = 0;
        $updated = 0;

        foreach ($templates as $t) {
            $existing = AdempimentoTemplate::where('codice', $t['codice'])->first();
            if ($existing) {
                $existing->fill($t);
                $existing->attivo = true;
                $existing->save();
                $updated++;
            } else {
                AdempimentoTemplate::create($t);
                $created++;
            }
        }

        $this->command->info("AdempimentoTemplate: {$created} creati, {$updated} aggiornati.");
    }

    /**
     * Definizioni dei template. Una funzione separata per facilità di review.
     */
    private function definizioni(): array
    {
        return [
            // ╔══ IVA ════════════════════════════════════════════════════════
            [
                'codice' => 'lipe',
                'nome'   => 'Comunicazione Liquidazione Periodica IVA (LIPE)',
                'descrizione' => 'Comunicazione trimestrale dei dati di liquidazione IVA da inviare telematicamente all\'Agenzia Entrate.',
                'riferimento_normativo' => 'D.L. 193/2016 art.4; provvedimento AdE 27/03/2017',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'iva',
                'periodicita'   => 'trimestrale',
                'scadenza_kind' => 'relative_to_period_end',
                'scadenza_mesi_dopo_periodo' => 2,    // ultimo giorno del 2° mese dopo
                'priorita'      => 'alta',
                'documenti_richiesti' => ['XML LIPE generato', 'Ricevuta invio Entratel'],
            ],
            [
                'codice' => 'iva_versamento_mensile',
                'nome'   => 'Versamento IVA mensile (F24)',
                'descrizione' => 'Versamento dell\'IVA a debito mensile con F24, codice tributo 60XX.',
                'riferimento_normativo' => 'DPR 633/72 art.27',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'iva',
                'periodicita'   => 'mensile',
                'scadenza_kind' => 'fixed_monthly',
                'scadenza_giorno' => 16,
                'priorita'      => 'alta',
                'documenti_richiesti' => ['F24 compilato', 'Ricevuta versamento'],
            ],
            [
                'codice' => 'iva_versamento_trimestrale',
                'nome'   => 'Versamento IVA trimestrale (F24)',
                'descrizione' => 'Versamento IVA trimestrale con maggiorazione 1% (codici tributo 6031, 6032, 6033, 6034). Esclude il 4° trimestre che confluisce in dichiarazione annuale.',
                'riferimento_normativo' => 'DPR 633/72 art.7 D.M. 24/12/1993',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'iva',
                'periodicita'   => 'trimestrale',
                'scadenza_kind' => 'relative_to_period_end',
                'scadenza_mesi_dopo_periodo' => 1,
                'scadenza_giorno' => 16,
                'priorita'      => 'alta',
            ],
            [
                'codice' => 'acconto_iva',
                'nome'   => 'Acconto IVA dicembre',
                'descrizione' => 'Acconto IVA da versare entro il 27 dicembre (codice tributo 6013 mensili / 6035 trimestrali). Calcolabile con metodo storico, previsionale o analitico.',
                'riferimento_normativo' => 'L. 405/1990 art.6',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'iva',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 12,
                'scadenza_giorno' => 27,
                'priorita'      => 'alta',
            ],
            [
                'codice' => 'dichiarazione_iva',
                'nome'   => 'Dichiarazione IVA annuale',
                'descrizione' => 'Dichiarazione annuale IVA per l\'anno precedente, da presentare entro il 30 aprile.',
                'riferimento_normativo' => 'DPR 322/98 art.8',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'iva',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 4,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['Modello IVA presentato', 'Ricevuta Entratel'],
            ],

            // ╔══ RITENUTE ═══════════════════════════════════════════════════
            [
                'codice' => 'ritenute_versamento',
                'nome'   => 'Versamento ritenute mensili (F24)',
                'descrizione' => 'Versamento ritenute alla fonte su lavoro autonomo (1040), provvigioni (1038), redditi di lavoro dipendente e altri tributi correlati.',
                'riferimento_normativo' => 'DPR 600/73 art.25',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'ritenute',
                'periodicita'   => 'mensile',
                'scadenza_kind' => 'fixed_monthly',
                'scadenza_giorno' => 16,
                'priorita'      => 'alta',
            ],
            [
                'codice' => 'cu_lavoratori',
                'nome'   => 'Consegna Certificazione Unica ai lavoratori',
                'descrizione' => 'Consegna ai sostituiti delle Certificazioni Uniche relative all\'anno precedente.',
                'riferimento_normativo' => 'DPR 322/98 art.4 c.6-quater',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'ritenute',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 3,
                'scadenza_giorno' => 16,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'alta',
            ],
            [
                'codice' => 'cu_telematica',
                'nome'   => 'Invio telematico CU all\'Agenzia Entrate',
                'descrizione' => 'Trasmissione telematica delle Certificazioni Uniche all\'AdE entro il 31 marzo (16 marzo per CU contenenti dati rilevanti per dichiarazione precompilata).',
                'riferimento_normativo' => 'DPR 322/98 art.4 c.6-quinquies',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'ritenute',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 3,
                'scadenza_giorno' => 31,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['Modelli CU', 'Ricevuta invio Entratel'],
            ],
            [
                'codice' => 'modello_770',
                'nome'   => 'Modello 770',
                'descrizione' => 'Dichiarazione annuale del sostituto d\'imposta. Riepiloga ritenute operate e versate nell\'anno precedente.',
                'riferimento_normativo' => 'DPR 322/98 art.4',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'ritenute',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 10,
                'scadenza_giorno' => 31,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['Modello 770', 'Ricevuta Entratel'],
            ],

            // ╔══ DICHIARATIVI ═══════════════════════════════════════════════
            [
                'codice' => 'modello_redditi',
                'nome'   => 'Modello Redditi ENC / SC',
                'descrizione' => 'Dichiarazione dei redditi per Enti Non Commerciali (ENC) o Società Cooperative (SC). Da presentare entro il 30 settembre.',
                'riferimento_normativo' => 'DPR 322/98 art.2',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'dichiarativi',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 9,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
            ],
            [
                'codice' => 'modello_irap',
                'nome'   => 'Dichiarazione IRAP',
                'descrizione' => 'Dichiarazione IRAP da presentare con il Modello Redditi entro il 30 settembre.',
                'riferimento_normativo' => 'D.Lgs. 446/97; DPR 322/98',
                'applicabile_a' => 'entrambi',
                'categoria'     => 'dichiarativi',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 9,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'alta',
            ],

            // ╔══ BILANCIO / RUNTS (specifico ETS) ═══════════════════════════
            [
                'codice' => 'bilancio_ets',
                'nome'   => 'Bilancio annuale ETS',
                'descrizione' => 'Redazione e approvazione del bilancio d\'esercizio (CEE) entro 4 mesi dalla chiusura dell\'esercizio. Per ETS con ricavi > 220.000€ è richiesta anche la Relazione di Missione.',
                'riferimento_normativo' => 'D.Lgs. 117/2017 art.13; D.M. 5/3/2020',
                'applicabile_a' => 'ets',
                'categoria'     => 'bilancio',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 4,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['Bilancio CEE firmato', 'Relazione di Missione (se applicabile)', 'Verbale di approvazione assemblea'],
            ],
            [
                'codice' => 'bilancio_deposito_runts',
                'nome'   => 'Deposito bilancio nel RUNTS',
                'descrizione' => 'Deposito del bilancio approvato nel Registro Unico Nazionale del Terzo Settore entro 30 giorni dall\'approvazione.',
                'riferimento_normativo' => 'D.Lgs. 117/2017 art.48; D.M. 106/2020',
                'applicabile_a' => 'ets',
                'categoria'     => 'bilancio',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 6,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['PDF bilancio depositato', 'Ricevuta RUNTS'],
            ],

            // ╔══ ETS-SPECIFICI ══════════════════════════════════════════════
            [
                'codice' => 'modello_eas',
                'nome'   => 'Modello EAS',
                'descrizione' => 'Comunicazione delle informazioni rilevanti ai fini fiscali per gli enti associativi. Da inviare ENTRO 60 giorni dalla costituzione e ad OGNI variazione dei dati comunicati.',
                'riferimento_normativo' => 'D.L. 185/2008 art.30',
                'applicabile_a' => 'ets',
                'categoria'     => 'ets',
                'periodicita'   => 'una_tantum',  // solo in caso di variazioni
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 3,
                'scadenza_giorno' => 31,
                'priorita'      => 'normale',
                'documenti_richiesti' => ['Modello EAS firmato', 'Ricevuta Entratel'],
            ],
            [
                'codice' => '5x1000_iscrizione',
                'nome'   => 'Iscrizione elenco beneficiari 5x1000',
                'descrizione' => 'Domanda di iscrizione al riparto del 5x1000 (per nuovi enti) entro il 10 aprile.',
                'riferimento_normativo' => 'D.P.C.M. 23/4/2010',
                'applicabile_a' => 'ets',
                'categoria'     => 'ets',
                'periodicita'   => 'una_tantum',  // solo prima iscrizione o variazioni
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 4,
                'scadenza_giorno' => 10,
                'priorita'      => 'alta',
            ],
            [
                'codice' => '5x1000_rendiconto',
                'nome'   => 'Rendicontazione 5x1000',
                'descrizione' => 'Rendiconto dell\'utilizzo dei fondi 5x1000 percepiti due anni prima. Soglia 20.000€: relazione descrittiva obbligatoria.',
                'riferimento_normativo' => 'D.P.C.M. 23/4/2010; provvedimento Min. Lavoro',
                'applicabile_a' => 'ets',
                'categoria'     => 'ets',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 6,
                'scadenza_giorno' => 30,
                'priorita'      => 'alta',
                'documenti_richiesti' => ['Rendiconto 5x1000', 'Relazione descrittiva (se > 20.000€)'],
            ],

            // ╔══ COOPERATIVE-SPECIFICI ══════════════════════════════════════
            [
                'codice' => 'revisione_cooperativa',
                'nome'   => 'Revisione cooperativa biennale',
                'descrizione' => 'Verifica della natura mutualistica della cooperativa, svolta da revisori del Ministero dello Sviluppo Economico (o associazioni di rappresentanza). Cadenza biennale.',
                'riferimento_normativo' => 'D.Lgs. 220/2002; D.M. 6/12/2004',
                'applicabile_a' => 'cooperativa',
                'categoria'     => 'cooperative',
                'periodicita'   => 'biennale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 12,
                'scadenza_giorno' => 31,
                'priorita'      => 'alta',
                'documenti_richiesti' => ['Verbale di revisione'],
            ],
            [
                'codice' => 'bilancio_cooperativa',
                'nome'   => 'Bilancio annuale cooperativa',
                'descrizione' => 'Bilancio d\'esercizio approvato dall\'assemblea entro 4 mesi (o 6 mesi per cooperative redigenti bilancio consolidato) dalla chiusura dell\'esercizio.',
                'riferimento_normativo' => 'Codice civile art.2364, art.2519',
                'applicabile_a' => 'cooperativa',
                'categoria'     => 'bilancio',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 4,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['Bilancio approvato', 'Verbale assemblea', 'Relazione amministratori'],
            ],
            [
                'codice' => 'deposito_bilancio_camera',
                'nome'   => 'Deposito bilancio Camera di Commercio',
                'descrizione' => 'Deposito telematico del bilancio approvato presso il Registro delle Imprese entro 30 giorni dall\'approvazione assembleare.',
                'riferimento_normativo' => 'Codice civile art.2435',
                'applicabile_a' => 'cooperativa',
                'categoria'     => 'bilancio',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 5,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'critica',
                'documenti_richiesti' => ['PDF bilancio depositato', 'Ricevuta Camera Commercio'],
            ],
            [
                'codice' => 'assemblea_ristorni',
                'nome'   => 'Delibera assembleare sui ristorni',
                'descrizione' => 'Assemblea per la delibera di ristorni ai soci. Solitamente contestuale all\'approvazione del bilancio.',
                'riferimento_normativo' => 'L. 904/77, D.Lgs. 6/2003',
                'applicabile_a' => 'cooperativa',
                'categoria'     => 'cooperative',
                'periodicita'   => 'annuale',
                'scadenza_kind' => 'fixed_yearly',
                'scadenza_mese' => 4,
                'scadenza_giorno' => 30,
                'scadenza_anno_offset' => 1,
                'priorita'      => 'normale',
                'documenti_richiesti' => ['Verbale assembleare ristorni'],
            ],
        ];
    }
}
