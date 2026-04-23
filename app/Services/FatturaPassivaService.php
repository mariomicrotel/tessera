<?php

namespace App\Services;

use App\Models\FatturaPassiva;
use App\Models\RigaFatturaPassiva;
use App\Models\CodiceIva;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per il ciclo passivo fornitori.
 *
 * Gestisce la registrazione, l'aggiornamento e i cambi di stato
 * delle fatture passive. Calcola automaticamente i totali a partire
 * dalle righe (quantità × prezzo × aliquota IVA).
 *
 * Regola: una fattura agganciata a una liquidazione definitiva
 * è read-only e non può essere modificata né eliminata.
 */
class FatturaPassivaService
{
    // ─────────────────────────────────────────────────────────────────────
    // Creazione
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra una nuova fattura passiva con le relative righe.
     *
     * @param  array  $testata  Attributi della testata (numero, date, stato, ecc.)
     * @param  array  $righe    Array di righe: [codice_iva_id, descrizione, quantita, prezzo_unitario, ...]
     * @throws InvalidArgumentException se le righe sono vuote
     */
    public function registra(array $testata, array $righe): FatturaPassiva
    {
        if (empty($righe)) {
            throw new InvalidArgumentException('La fattura deve contenere almeno una riga.');
        }

        return DB::transaction(function () use ($testata, $righe) {
            $fattura = FatturaPassiva::create(array_merge($testata, [
                'imponibile_totale' => 0,
                'iva_totale'        => 0,
                'totale_documento'  => 0,
            ]));

            $this->salvaRighe($fattura, $righe);
            $fattura->ricalcolaTotali();
            $fattura->save();

            return $fattura->load('righe.codiceIva', 'supplier');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggiornamento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggiorna testata e righe di una fattura modificabile.
     *
     * @throws InvalidArgumentException se la fattura è read-only
     */
    public function aggiorna(FatturaPassiva $fattura, array $testata, array $righe): FatturaPassiva
    {
        $this->verificaModificabile($fattura);

        if (empty($righe)) {
            throw new InvalidArgumentException('La fattura deve contenere almeno una riga.');
        }

        return DB::transaction(function () use ($fattura, $testata, $righe) {
            $fattura->update($testata);
            $fattura->righe()->delete();
            $this->salvaRighe($fattura, $righe);
            $fattura->ricalcolaTotali();
            $fattura->save();

            return $fattura->load('righe.codiceIva', 'supplier');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cambi di stato
    // ─────────────────────────────────────────────────────────────────────

    public function marcaPagata(FatturaPassiva $fattura): void
    {
        if ($fattura->stato_pagamento === FatturaPassiva::STATO_ANNULLATA) {
            throw new InvalidArgumentException('Una fattura annullata non può essere pagata.');
        }

        $fattura->update(['stato_pagamento' => FatturaPassiva::STATO_PAGATA]);
    }

    public function marcaParzialmentePagata(FatturaPassiva $fattura): void
    {
        if ($fattura->stato_pagamento === FatturaPassiva::STATO_ANNULLATA) {
            throw new InvalidArgumentException('Una fattura annullata non può essere marcata parzialmente pagata.');
        }

        $fattura->update(['stato_pagamento' => FatturaPassiva::STATO_PARZIALMENTE_PAGATA]);
    }

    public function reimpostaDaPagare(FatturaPassiva $fattura): void
    {
        $fattura->update(['stato_pagamento' => FatturaPassiva::STATO_DA_PAGARE]);
    }

    public function annulla(FatturaPassiva $fattura): void
    {
        $this->verificaModificabile($fattura);
        $fattura->update(['stato_pagamento' => FatturaPassiva::STATO_ANNULLATA]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Calcoli (usabili anche da frontend via AJAX o Inertia shared data)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcola i totali per un array di righe senza persistenza.
     * Usato per preview lato controller (anteprima before save).
     *
     * @param  array  $righe  Ogni riga: [codice_iva_id, quantita, prezzo_unitario, indetraibile_percentuale?]
     * @return array  [imponibile_totale, iva_totale, iva_indetraibile_totale, totale_documento]
     */
    public function calcolaTotaliPreview(array $righe): array
    {
        $codiciIds = array_column($righe, 'codice_iva_id');
        $codici    = CodiceIva::whereIn('id', $codiciIds)->get()->keyBy('id');

        $totImponibile    = 0.0;
        $totIva           = 0.0;
        $totIvaIndedraibile = 0.0;

        foreach ($righe as $r) {
            $qta    = (float) ($r['quantita']       ?? 1);
            $prezzo = (float) ($r['prezzo_unitario'] ?? 0);
            $imp    = round($qta * $prezzo, 2);

            $codice = $codici[$r['codice_iva_id']] ?? null;
            $aliq   = $codice ? (float) $codice->percentuale : 0;
            $iva    = round($imp * $aliq / 100, 2);

            $indPct  = (float) ($r['indetraibile_percentuale'] ?? ($codice ? (float) $codice->indetraibile_percentuale : 0));
            $ivaInd  = round($iva * $indPct / 100, 2);

            $totImponibile      += $imp;
            $totIva             += $iva;
            $totIvaIndedraibile += $ivaInd;
        }

        return [
            'imponibile_totale'     => round($totImponibile, 2),
            'iva_totale'            => round($totIva, 2),
            'iva_indetraibile_totale' => round($totIvaIndedraibile, 2),
            'totale_documento'      => round($totImponibile + $totIva, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verifica che la fattura sia modificabile.
     *
     * @throws InvalidArgumentException se agganciata a liquidazione definitiva
     */
    public function verificaModificabile(FatturaPassiva $fattura): void
    {
        if ($fattura->isReadOnly()) {
            throw new InvalidArgumentException(
                "La fattura {$fattura->numero_fattura} è agganciata a una liquidazione definitiva e non può essere modificata."
            );
        }
    }

    /**
     * Persiste le righe della fattura calcolando i totali per ognuna.
     */
    private function salvaRighe(FatturaPassiva $fattura, array $righe): void
    {
        // Precarica i codici IVA usati
        $codiciIds = array_unique(array_column($righe, 'codice_iva_id'));
        $codici    = CodiceIva::whereIn('id', $codiciIds)->get()->keyBy('id');

        foreach ($righe as $dati) {
            $qta    = (float) ($dati['quantita']       ?? 1);
            $prezzo = (float) ($dati['prezzo_unitario'] ?? 0);
            $imp    = round($qta * $prezzo, 2);

            $codice = $codici[$dati['codice_iva_id']] ?? null;
            $aliq   = $codice ? (float) $codice->percentuale : 0;
            $iva    = round($imp * $aliq / 100, 2);

            $indPct = (float) ($dati['indetraibile_percentuale']
                ?? ($codice ? (float) $codice->indetraibile_percentuale : 0));
            $ivaInd = round($iva * $indPct / 100, 2);

            RigaFatturaPassiva::create([
                'tenant_id'                => $fattura->tenant_id,
                'fattura_passiva_id'       => $fattura->id,
                'codice_iva_id'            => $dati['codice_iva_id'],
                'conto_id'                 => $dati['conto_id'] ?? null,
                'descrizione'              => $dati['descrizione'],
                'quantita'                 => $qta,
                'prezzo_unitario'          => $prezzo,
                'imponibile'               => $imp,
                'iva'                      => $iva,
                'totale'                   => round($imp + $iva, 2),
                'indetraibile_percentuale' => $indPct,
                'iva_indetraibile'         => $ivaInd,
            ]);
        }
    }
}
