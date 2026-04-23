<?php

namespace App\Services;

use App\Models\Conto;
use App\Models\CooperativeShare;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\PrimaNotaEntry;
use App\Models\Spesa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gestione del capitale sociale di una cooperativa.
 *
 * Orchestrazione di CooperativeShare, Incasso, Spesa e PrimaNotaEntry
 * per tracciare sottoscrizione, versamento e riscatto di quote.
 */
class CapitaleSocialeService
{
    /**
     * Restituisce il primo conto attivo del tenant (ordinato per ordine/id).
     * Usato internamente quando non viene fornito un conto specifico.
     *
     * @throws RuntimeException se non esiste alcun conto
     */
    private function conto(): Conto
    {
        $conto = Conto::where('attivo', true)->orderBy('ordine')->orderBy('id')->first();
        if (! $conto) {
            throw new RuntimeException('Nessun conto attivo trovato. Crea almeno un conto in Tesoreria.');
        }
        return $conto;
    }

    // ── a) Sottoscrivi quote ──────────────────────────────────────────────────

    /**
     * Registra la sottoscrizione di quote di capitale da parte di un socio.
     *
     * - Crea o aggiorna il record CooperativeShare del socio
     * - Crea un Incasso di tipo 'capitale' per la sottoscrizione
     * - Genera PrimaNotaEntry collegata all'Incasso
     * - Aggiorna members.numero_quote_capitale
     *
     * @throws RuntimeException se non esiste un conto attivo
     */
    public function sottoscriviQuote(
        Member $member,
        int $numeroQuote,
        float $valoreUnitario,
        Carbon $data,
    ): CooperativeShare {
        return DB::transaction(function () use ($member, $numeroQuote, $valoreUnitario, $data) {
            $totale = round($numeroQuote * $valoreUnitario, 2);
            $conto  = $this->conto();

            // Crea o aggiorna CooperativeShare (un record per socio)
            $share = CooperativeShare::updateOrCreate(
                ['member_id' => $member->id],
                [
                    'numero_quote'        => $numeroQuote,
                    'valore_unitario'     => $valoreUnitario,
                    'totale_sottoscritto' => $totale,
                    'totale_versato'      => 0,
                    'data_sottoscrizione' => $data,
                    'status'              => 'sottoscritta',
                    'data_versamento'     => null,
                    'data_riscatto'       => null,
                    'motivo_riscatto'     => null,
                ],
            );

            // Incasso tipo 'capitale' per la sottoscrizione (da versare)
            $incasso = Incasso::create([
                'member_id'          => $member->id,
                'amount'             => $totale,
                'paid_at'            => $data,
                'conto_id'           => $conto->id,
                'description'        => "Sottoscrizione {$numeroQuote} quote capitale × €{$valoreUnitario}",
                'genera_prima_nota'  => true,
                'type'               => Incasso::TYPE_CAPITALE,
            ]);

            // Prima nota: A01 = "Quote e contributi soci" (capitale sociale cooperativa)
            PrimaNotaEntry::create([
                'conto_id'        => $conto->id,
                'rendiconto_code' => RendicontoCassaSchemaCooperativa::CODE_CAPITALE_SOCIALE,
                'entryable_type'  => Incasso::class,
                'entryable_id'    => $incasso->id,
                'date'            => $data,
                'amount'          => $totale,
                'description'     => "Capitale sociale – sottoscrizione ({$member->nomeCompleto()})",
            ]);

            // Aggiorna contatore quote sul socio
            $member->update(['numero_quote_capitale' => $numeroQuote]);

            return $share->fresh();
        });
    }

    // ── b) Versa quote ───────────────────────────────────────────────────────

    /**
     * Registra un versamento (totale o parziale) su quote già sottoscritte.
     *
     * - Aggiorna totale_versato sul CooperativeShare
     * - Aggiorna lo status (parzialmente_versata / versata)
     * - Crea Incasso tipo 'capitale' + PrimaNotaEntry
     *
     * @throws RuntimeException se l'importo supera il dovuto o il conto non esiste
     */
    public function versaQuote(
        CooperativeShare $share,
        float $importoVersato,
        Carbon $data,
    ): CooperativeShare {
        return DB::transaction(function () use ($share, $importoVersato, $data) {
            $nuovoTotaleVersato = round((float) $share->totale_versato + $importoVersato, 2);

            if ($nuovoTotaleVersato > (float) $share->totale_sottoscritto + 0.01) {
                throw new RuntimeException(
                    sprintf(
                        'Importo eccede il totale sottoscritto (ancora da versare: €%.2f).',
                        $share->ancora_da_versare,
                    )
                );
            }

            $nuovoStatus = $nuovoTotaleVersato >= (float) $share->totale_sottoscritto
                ? 'versata'
                : 'parzialmente_versata';

            $share->update([
                'totale_versato'  => $nuovoTotaleVersato,
                'status'          => $nuovoStatus,
                'data_versamento' => $nuovoStatus === 'versata' ? $data : null,
            ]);

            $conto = $this->conto();

            $incasso = Incasso::create([
                'member_id'         => $share->member_id,
                'amount'            => $importoVersato,
                'paid_at'           => $data,
                'conto_id'          => $conto->id,
                'description'       => "Versamento quote capitale (totale versato: €{$nuovoTotaleVersato})",
                'genera_prima_nota' => true,
                'type'              => Incasso::TYPE_CAPITALE,
            ]);

            $nomeSocio = $share->member?->nomeCompleto() ?? "socio #{$share->member_id}";
            PrimaNotaEntry::create([
                'conto_id'        => $conto->id,
                'rendiconto_code' => RendicontoCassaSchemaCooperativa::CODE_CAPITALE_SOCIALE,
                'entryable_type'  => Incasso::class,
                'entryable_id'    => $incasso->id,
                'date'            => $data,
                'amount'          => $importoVersato,
                'description'     => "Capitale sociale – versamento ({$nomeSocio})",
            ]);

            return $share->fresh();
        });
    }

    // ── c) Riscatta quote ────────────────────────────────────────────────────

    /**
     * Registra il riscatto delle quote al momento dell'uscita del socio.
     *
     * - Imposta status = 'riscattata', data_riscatto, motivo_riscatto
     * - Crea una Spesa per il rimborso del capitale al socio
     * - Aggiorna members.numero_quote_capitale = 0
     *
     * @throws RuntimeException se il conto non esiste
     */
    public function riscattaQuote(
        CooperativeShare $share,
        string $motivo,
        Carbon $data,
    ): CooperativeShare {
        return DB::transaction(function () use ($share, $motivo, $data) {
            $importoRimborso = (float) $share->totale_versato;

            $share->update([
                'status'          => 'riscattata',
                'data_riscatto'   => $data,
                'motivo_riscatto' => $motivo,
            ]);

            // Crea Spesa per il rimborso — EXP_A_5 = "Uscite diverse di gestione"
            if ($importoRimborso > 0) {
                $conto    = $this->conto();
                $nomeSoc  = $share->member?->nomeCompleto() ?? "socio #{$share->member_id}";
                $spesa = Spesa::create([
                    'date'               => $data,
                    'amount'             => $importoRimborso,
                    'description'        => "Riscatto quote capitale – {$motivo} ({$nomeSoc})",
                    'conto_id'           => $conto->id,
                    'genera_prima_nota'  => true,
                    'rendiconto_code'    => 'EXP_A_5',
                    'competenza_cassa'   => true,
                ]);

                PrimaNotaEntry::create([
                    'conto_id'        => $conto->id,
                    'rendiconto_code' => 'EXP_A_5',
                    'entryable_type'  => Spesa::class,
                    'entryable_id'    => $spesa->id,
                    'date'            => $data,
                    'amount'          => $importoRimborso,
                    'description'     => "Rimborso capitale riscattato – {$motivo}",
                ]);
            }

            // Azzera contatore quote sul socio
            if ($share->member) {
                $share->member->update(['numero_quote_capitale' => 0]);
            }

            return $share->fresh();
        });
    }

    // ── d) Situazione capitale ────────────────────────────────────────────────

    /**
     * Restituisce un riepilogo dello stato del capitale sociale del tenant.
     *
     * @return array{
     *   totale_sottoscritto: float,
     *   totale_versato: float,
     *   capitale_da_versare: float,
     *   numero_soci_con_quote: int,
     *   numero_quote_totali: int,
     * }
     */
    public function getSituazioneCapitale(): array
    {
        $query = CooperativeShare::attive();

        $totSottoscritto = (float) $query->sum('totale_sottoscritto');
        $totVersato      = (float) (clone $query)->sum('totale_versato');
        $numSoci         = (int)   (clone $query)->count();
        $numQuote        = (int)   (clone $query)->sum('numero_quote');

        return [
            'totale_sottoscritto'  => $totSottoscritto,
            'totale_versato'       => $totVersato,
            'capitale_da_versare'  => max(0, $totSottoscritto - $totVersato),
            'numero_soci_con_quote' => $numSoci,
            'numero_quote_totali'  => $numQuote,
        ];
    }
}
