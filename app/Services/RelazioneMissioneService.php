<?php

namespace App\Services;

use App\Models\CompensaTerzi;
use App\Models\Member;
use App\Models\MovimentoContabile;
use App\Models\RelazioneMissione;
use App\Models\Settings;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per la Relazione di Missione ETS.
 *
 * Funzioni principali:
 *  1. raccogliVariabili()  — snapshot dei dati contabili/soci dell'anno
 *  2. crea()               — nuova relazione con sezioni default pre-compilate
 *  3. aggiorna()           — salva sezioni e metadati
 *  4. approva()            — porta in stato "approvata"
 *  5. interpolaVariabili() — sostituisce {{variabile}} nel testo con i valori reali
 */
class RelazioneMissioneService
{
    // ─────────────────────────────────────────────────────────────────────
    // Raccolta variabili automatiche
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Raccoglie uno snapshot dei dati dell'anno dal sistema.
     *
     * Le variabili sono usate sia per pre-compilare il testo delle sezioni
     * tramite {{placeholder}}, sia per il riquadro "Dati automatici" nella UI.
     *
     * @return array<string, mixed>
     */
    public function raccogliVariabili(Tenant $tenant, int $anno): array
    {
        $tenantId = $tenant->id;

        // ── Soci ────────────────────────────────────────────────────────
        $totSoci     = Member::where('tenant_id', $tenantId)->attivi()->count();
        $nuoviSoci   = Member::where('tenant_id', $tenantId)
            ->whereYear('data_iscrizione', $anno)
            ->count();
        $sociCessati = Member::where('tenant_id', $tenantId)
            ->whereYear('data_cessazione', $anno)
            ->count();

        // ── Movimenti contabili ─────────────────────────────────────────
        $movBase = MovimentoContabile::where('tenant_id', $tenantId)
            ->where('anno_esercizio', $anno)
            ->where('stato', 'confermato');

        // Totali da righe movimento (DARE = costi/uscite, AVERE = ricavi/entrate)
        $totEntrate = (float) DB::table('righe_movimento_contabile as r')
            ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
            ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.anno_esercizio', $anno)
            ->where('m.stato', 'confermato')
            ->where('c.natura', 'ricavo')
            ->sum('r.importo_avere');

        $totUscite = (float) DB::table('righe_movimento_contabile as r')
            ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
            ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.anno_esercizio', $anno)
            ->where('m.stato', 'confermato')
            ->where('c.natura', 'costo')
            ->sum('r.importo_dare');

        $risultato = round($totEntrate - $totUscite, 2);

        // ── Quote associative ────────────────────────────────────────────
        $quoteAnno = (float) DB::table('incassi')
            ->whereYear('paid_at', $anno)
            ->whereIn('type', ['quota', 'quota_associativa', 'subscription'])
            ->sum('amount');

        // ── Erogazioni liberali / donazioni ─────────────────────────────
        $donazioni = (float) DB::table('incassi')
            ->whereYear('paid_at', $anno)
            ->whereIn('type', ['donation', 'donazione', 'liberalita'])
            ->sum('amount');

        // ── Compensi a terzi ─────────────────────────────────────────────
        $compensiTot = (float) CompensaTerzi::where('tenant_id', $tenantId)
            ->where('anno_competenza', $anno)
            ->sum('compenso_lordo');

        $ritenute = (float) CompensaTerzi::where('tenant_id', $tenantId)
            ->where('anno_competenza', $anno)
            ->sum('ritenuta');

        // ── Numero eventi e attività ─────────────────────────────────────
        $numEventi = 0;
        if (DB::getSchemaBuilder()->hasTable('events')) {
            $numEventi = (int) DB::table('events')
                ->where('tenant_id', $tenantId)
                ->whereYear('start_date', $anno)
                ->count();
        }

        $nomeTenant = Settings::get('nome_associazione', $tenant->name);

        return [
            // Identificativi
            'anno'                  => $anno,
            'nome_ente'             => $nomeTenant,
            'codice_fiscale_ente'   => $tenant->codice_fiscale ?? '',
            'indirizzo_ente'        => Settings::get('indirizzo_associazione', ''),

            // Soci
            'totale_soci'           => $totSoci,
            'nuovi_soci'            => $nuoviSoci,
            'soci_cessati'          => $sociCessati,

            // Economici
            'totale_entrate'        => round($totEntrate, 2),
            'totale_uscite'         => round($totUscite, 2),
            'risultato_esercizio'   => $risultato,
            'quote_associative'     => round($quoteAnno, 2),
            'donazioni_ricevute'    => round($donazioni, 2),
            'compensi_terzi'        => round($compensiTot, 2),
            'ritenute_versate'      => round($ritenute, 2),

            // Attività
            'numero_eventi'         => $numEventi,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crea una nuova relazione di missione con sezioni default pre-compilate.
     *
     * Se esiste già una relazione per quell'anno lancia eccezione.
     */
    public function crea(Tenant $tenant, int $anno, array $extra = []): RelazioneMissione
    {
        if (RelazioneMissione::where('tenant_id', $tenant->id)->where('anno', $anno)->exists()) {
            throw new InvalidArgumentException(
                "Esiste già una Relazione di Missione per l'anno {$anno}."
            );
        }

        $variabili = $this->raccogliVariabili($tenant, $anno);
        $sezioni   = $this->sezioniConVariabiliDefault($variabili);

        return RelazioneMissione::create([
            'tenant_id'           => $tenant->id,
            'anno'                => $anno,
            'stato'               => RelazioneMissione::STATO_BOZZA,
            'organo_approvante'   => $extra['organo_approvante']  ?? 'Assemblea dei soci',
            'data_approvazione'   => $extra['data_approvazione']  ?? null,
            'luogo_approvazione'  => $extra['luogo_approvazione'] ?? null,
            'sezioni'             => $sezioni,
            'variabili_snapshot'  => $variabili,
            'note_interne'        => $extra['note_interne']       ?? null,
        ]);
    }

    /**
     * Aggiorna sezioni e metadati. Riacquisisce le variabili dal sistema.
     */
    public function aggiorna(RelazioneMissione $relazione, array $data): RelazioneMissione
    {
        if ($relazione->isApprovata()) {
            throw new InvalidArgumentException(
                'Impossibile modificare una relazione già approvata.'
            );
        }

        $variabili = $this->raccogliVariabili(
            Tenant::find($relazione->tenant_id),
            $relazione->anno
        );

        $relazione->update([
            'stato'               => $data['stato']              ?? $relazione->stato,
            'organo_approvante'   => $data['organo_approvante']  ?? $relazione->organo_approvante,
            'data_approvazione'   => $data['data_approvazione']  ?? $relazione->data_approvazione,
            'luogo_approvazione'  => $data['luogo_approvazione'] ?? $relazione->luogo_approvazione,
            'sezioni'             => $data['sezioni']            ?? $relazione->sezioni,
            'variabili_snapshot'  => $variabili,
            'note_interne'        => $data['note_interne']       ?? $relazione->note_interne,
        ]);

        return $relazione->fresh();
    }

    /**
     * Porta la relazione in stato "approvata" con data e luogo assemblea.
     */
    public function approva(RelazioneMissione $relazione, string $dataApprovazione, string $organo, ?string $luogo = null): RelazioneMissione
    {
        if ($relazione->isApprovata()) {
            throw new InvalidArgumentException('La relazione è già approvata.');
        }

        $relazione->update([
            'stato'              => RelazioneMissione::STATO_APPROVATA,
            'data_approvazione'  => $dataApprovazione,
            'organo_approvante'  => $organo,
            'luogo_approvazione' => $luogo,
        ]);

        return $relazione->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Interpolazione variabili nel testo
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sostituisce {{variabile}} nel testo con i valori reali.
     *
     * Es. "L'ente conta {{totale_soci}} soci" → "L'ente conta 42 soci"
     */
    public function interpolaVariabili(string $testo, array $variabili): string
    {
        foreach ($variabili as $key => $value) {
            $formatted = is_float($value)
                ? number_format($value, 2, ',', '.')
                : (string) $value;

            $testo = str_replace('{{'.$key.'}}', $formatted, $testo);
        }

        return $testo;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Restituisce le sezioni default con testi pre-compilati usando variabili.
     */
    private function sezioniConVariabiliDefault(array $v): array
    {
        $anno = $v['anno'];
        $nome = $v['nome_ente'];

        return [
            [
                'id'     => 'presentazione',
                'titolo' => "1. Presentazione dell'ente e missione",
                'testo'  => "{$nome} è un ente del Terzo Settore costituito ai sensi del D.Lgs. 117/2017.\n"
                           ."Al {$anno} l'ente conta {{totale_soci}} soci attivi (di cui {{nuovi_soci}} nuovi iscritti nell'anno).",
            ],
            [
                'id'     => 'attivita_istituzionali',
                'titolo' => '2. Attività istituzionali svolte',
                'testo'  => "Nel corso dell'anno {$anno} l'ente ha svolto le attività di interesse generale previste dallo statuto.\n"
                           .(($v['numero_eventi'] > 0)
                               ? "Sono stati organizzati {{numero_eventi}} eventi/iniziative."
                               : 'Descrivere le principali attività svolte.'),
            ],
            [
                'id'     => 'attivita_diverse',
                'titolo' => '3. Attività diverse e accessorie',
                'testo'  => "Descrivere eventuali attività diverse da quelle di interesse generale svolte nell'anno {$anno}.",
            ],
            [
                'id'     => 'raccolta_fondi',
                'titolo' => '4. Raccolta fondi e liberalità',
                'testo'  => "Le quote associative riscosse nell'anno {$anno} ammontano a € {{quote_associative}}.\n"
                           .(($v['donazioni_ricevute'] > 0)
                               ? "Le erogazioni liberali ricevute ammontano a € {{donazioni_ricevute}}."
                               : 'Nessuna donazione/erogazione liberale registrata nell\'anno.'),
            ],
            [
                'id'     => 'situazione_patrimoniale',
                'titolo' => '5. Situazione patrimoniale e finanziaria',
                'testo'  => "Il totale delle entrate dell'esercizio {$anno} è pari a € {{totale_entrate}}.\n"
                           ."Il totale delle uscite è pari a € {{totale_uscite}}.\n"
                           ."Il risultato di esercizio è pari a € {{risultato_esercizio}}.",
            ],
            [
                'id'     => 'andamento_economico',
                'titolo' => '6. Andamento economico e gestionale',
                'testo'  => "Nell'anno {$anno} l'ente ha registrato entrate per € {{totale_entrate}} e uscite per € {{totale_uscite}}, "
                           ."con un risultato di esercizio di € {{risultato_esercizio}}.\n"
                           .(($v['compensi_terzi'] > 0)
                               ? "Sono stati corrisposti compensi a terzi per € {{compensi_terzi}} con ritenute d'acconto di € {{ritenute_versate}}."
                               : ''),
            ],
            [
                'id'     => 'fatti_rilevanti',
                'titolo' => '7. Fatti rilevanti successivi alla chiusura',
                'testo'  => "Non si segnalano fatti di rilievo intervenuti dopo la chiusura dell'esercizio {$anno}.",
            ],
            [
                'id'     => 'prospettive',
                'titolo' => '8. Prospettive future e obiettivi',
                'testo'  => "Il Consiglio Direttivo intende perseguire nel prossimo esercizio le finalità statutarie, "
                           ."continuando a sviluppare le attività di interesse generale a favore dei propri soci e della comunità.",
            ],
        ];
    }
}
