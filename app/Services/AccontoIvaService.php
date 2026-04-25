<?php

namespace App\Services;

use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\LiquidazioneIva;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Calcola l'acconto IVA dovuto entro il 27 dicembre (art. 6, L. 405/1990).
 *
 * Tre metodi di calcolo:
 *
 *  storico      → 88% dell'IVA netta versata per l'analogo periodo dell'anno precedente.
 *                 Riferimento: dicembre (mese 12) o Q4 (trimestre 4) dell'anno n-1.
 *
 *  previsionale → 88% dell'IVA presumibilmente dovuta per dicembre/Q4 dell'anno corrente
 *                 (basata sulle fatture già emesse nel periodo 1-20 dicembre).
 *
 *  analitico    → IVA effettiva sulle operazioni del periodo 1-20 dicembre (100%).
 *                 Meno usato; incluso per completezza.
 *
 * Restituzione: array con dettagli di calcolo per ciascun metodo e il minimo raccomandato.
 */
class AccontoIvaService
{
    /** Percentuale di acconto (88%). */
    private const PERCENTUALE_ACCONTO = 0.88;

    /**
     * Calcola il prospetto acconto IVA per l'anno indicato.
     *
     * @return array{
     *   anno: int,
     *   scadenza: string,
     *   storico: array{base: float, acconto: float, fonte: string},
     *   previsionale: array{base: float, acconto: float, fonte: string},
     *   analitico: array{base: float, acconto: float, fonte: string},
     *   minimo_raccomandato: float,
     * }
     */
    public function calcola(Tenant $tenant, int $anno): array
    {
        $scadenza = "{$anno}-12-27";

        return [
            'anno'    => $anno,
            'scadenza' => $scadenza,
            'storico'      => $this->calcolaStorico($tenant, $anno),
            'previsionale' => $this->calcolaPrevisionale($tenant, $anno),
            'analitico'    => $this->calcolaAnalitico($tenant, $anno),
            'minimo_raccomandato' => $this->minimoRaccomandato($tenant, $anno),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Metodo storico
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 88% dell'IVA netta versata a dicembre/Q4 dell'anno precedente.
     */
    private function calcolaStorico(Tenant $tenant, int $anno): array
    {
        $annoPrecedente = $anno - 1;

        // Prima guarda se c'è una liquidazione definitiva per dic o Q4 dell'anno prec.
        $liq = $this->troveLiquidazioneDicembre($tenant, $annoPrecedente);

        if ($liq) {
            $base   = max(0.0, (float) $liq->saldo_finale);
            $fonte  = "Liquidazione definitiva {$liq->getPeriodoLabelAttribute()}";
        } else {
            // Stima da fatture
            $base  = $this->ivaNettaDicembre($tenant, $annoPrecedente);
            $fonte = "Calcolata da fatture dic. {$annoPrecedente} (nessuna liquidazione chiusa)";
        }

        return [
            'base'    => round($base, 2),
            'acconto' => round($base * self::PERCENTUALE_ACCONTO, 2),
            'fonte'   => $fonte,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Metodo previsionale
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 88% dell'IVA presunta per l'intero dicembre/Q4 dell'anno corrente.
     * Base: operazioni dal 1° al 20 dicembre proiettate sull'intero mese.
     */
    private function calcolaPrevisionale(Tenant $tenant, int $anno): array
    {
        $from = "{$anno}-12-01";
        $to   = "{$anno}-12-20";

        $ivaDebito  = $this->ivaAttive($tenant, $from, $to);
        $ivaCredito = $this->ivaPassive($tenant, $from, $to);
        $saldo = max(0.0, round($ivaDebito - $ivaCredito, 2));

        // Proietta ai 31 giorni di dicembre: × (31/20)
        $baseProiettata = round($saldo * (31 / 20), 2);

        return [
            'base'    => $baseProiettata,
            'acconto' => round($baseProiettata * self::PERCENTUALE_ACCONTO, 2),
            'fonte'   => "Proiezione operazioni 1-20 dic. {$anno} su intero mese",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Metodo analitico
    // ─────────────────────────────────────────────────────────────────────

    /**
     * IVA effettiva sulle operazioni 1-20 dicembre (100%, no proiezione).
     */
    private function calcolaAnalitico(Tenant $tenant, int $anno): array
    {
        $from = "{$anno}-12-01";
        $to   = "{$anno}-12-20";

        $ivaDebito  = $this->ivaAttive($tenant, $from, $to);
        $ivaCredito = $this->ivaPassive($tenant, $from, $to);
        $saldo = max(0.0, round($ivaDebito - $ivaCredito, 2));

        return [
            'base'    => $saldo,
            'acconto' => $saldo, // 100%, nessuna percentuale
            'fonte'   => "IVA effettiva operazioni 1-20 dic. {$anno}",
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Minimo raccomandato (il minore tra storico e previsionale > 0)
    // ─────────────────────────────────────────────────────────────────────

    private function minimoRaccomandato(Tenant $tenant, int $anno): float
    {
        $s = $this->calcolaStorico($tenant, $anno)['acconto'];
        $p = $this->calcolaPrevisionale($tenant, $anno)['acconto'];

        // Considera solo i metodi con valore positivo
        $valori = array_filter([$s, $p], fn (float $v) => $v > 0);

        return $valori ? min($valori) : 0.0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function troveLiquidazioneDicembre(Tenant $tenant, int $anno): ?LiquidazioneIva
    {
        return LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where(function ($q) {
                $q->where(fn ($q) => $q
                    ->where('tipo_periodo', LiquidazioneIva::TIPO_MENSILE)
                    ->where('periodo', 12)
                )
                ->orWhere(fn ($q) => $q
                    ->where('tipo_periodo', LiquidazioneIva::TIPO_TRIMESTRALE)
                    ->where('periodo', 4)
                );
            })
            ->whereIn('status', [LiquidazioneIva::STATUS_DEFINITIVA, LiquidazioneIva::STATUS_VERSATA])
            ->first();
    }

    private function ivaNettaDicembre(Tenant $tenant, int $anno): float
    {
        $from = "{$anno}-12-01";
        $to   = "{$anno}-12-31";

        $debito  = $this->ivaAttive($tenant, $from, $to);
        $credito = $this->ivaPassive($tenant, $from, $to);

        return max(0.0, round($debito - $credito, 2));
    }

    private function ivaAttive(Tenant $tenant, string $from, string $to): float
    {
        return (float) FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->whereBetween('data_fattura', [$from, $to])
            ->sum('iva_totale');
    }

    private function ivaPassive(Tenant $tenant, string $from, string $to): float
    {
        return (float) FatturaPassiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('data_registrazione', [$from, $to])
            ->sum('iva_totale');
    }
}
