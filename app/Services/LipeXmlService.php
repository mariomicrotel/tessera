<?php

namespace App\Services;

use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\LiquidazioneIva;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Genera il file XML LIPE (Comunicazione Liquidazioni Periodiche IVA)
 * nel formato v1.0 richiesto dall'Agenzia delle Entrate
 * (Provvedimento 2017 — schema ComunicazioneLiquidazioni_v1.1.xsd).
 *
 * Parametri accettati:
 *  - anno      : anno solare
 *  - trimestre : 1-4  (quarter; se mensile genera 3 moduli per trimestre)
 *
 * Il file prodotto è pronto per l'import in Entratel/Desktop Telematico.
 */
class LipeXmlService
{
    /**
     * Genera il contenuto XML LIPE per il tenant e il trimestre indicati.
     *
     * @param  int  $trimestre 1-4
     */
    public function genera(Tenant $tenant, int $anno, int $trimestre): string
    {
        if ($trimestre < 1 || $trimestre > 4) {
            throw new \InvalidArgumentException("Trimestre non valido: {$trimestre}. Valori ammessi: 1-4.");
        }

        $moduli = $this->costruisciModuli($tenant, $anno, $trimestre);
        return $this->renderXml($tenant, $anno, $trimestre, $moduli);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Costruisce i moduli (un modulo per periodo nel trimestre)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Determina tipo periodo predominante del tenant e costruisce 1-3 moduli.
     *
     * Logica:
     *  - Se esistono liquidazioni mensili nel trimestre → 3 moduli mensili
     *  - Se esiste una liquidazione trimestrale         → 1 modulo trimestrale
     *  - Se non esistono liquidazioni                  → 1-3 moduli con dati calcolati da fatture
     *
     * @return array<array{tipo: string, periodo: int, anno: int, dati: array}>
     */
    private function costruisciModuli(Tenant $tenant, int $anno, int $trimestre): array
    {
        // Cerca prima liquidazioni mensili nel trimestre
        $mesiDelTrimestre = $this->mesiDelTrimestre($trimestre);

        $liquidazioneMensili = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('tipo_periodo', LiquidazioneIva::TIPO_MENSILE)
            ->whereIn('periodo', $mesiDelTrimestre)
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        // Cerca liquidazione trimestrale
        $liquidazioneTrimestrale = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('tipo_periodo', LiquidazioneIva::TIPO_TRIMESTRALE)
            ->where('periodo', $trimestre)
            ->first();

        // Se esiste la trimestrale → 1 modulo
        if ($liquidazioneTrimestrale) {
            return [[
                'tipo'    => 'trimestrale',
                'periodo' => $trimestre,
                'anno'    => $anno,
                'dati'    => $this->datiDaLiquidazione($tenant, $liquidazioneTrimestrale),
            ]];
        }

        // Altrimenti 3 moduli mensili (da liquidazioni chiuse o calcolate on-the-fly)
        $moduli = [];
        foreach ($mesiDelTrimestre as $mese) {
            $liquidazione = $liquidazioneMensili->get($mese);
            $moduli[] = [
                'tipo'    => 'mensile',
                'periodo' => $mese,
                'anno'    => $anno,
                'dati'    => $liquidazione
                    ? $this->datiDaLiquidazione($tenant, $liquidazione)
                    : $this->datiCalcolatiDaFatture($tenant, $anno, $mese, LiquidazioneIva::TIPO_MENSILE),
            ];
        }

        return $moduli;
    }

    /**
     * Estrae i dati da una LiquidazioneIva già chiusa.
     */
    private function datiDaLiquidazione(Tenant $tenant, LiquidazioneIva $liq): array
    {
        // VP2 e VP3 (imponibili): non memorizzati, li calcoliamo dalle fatture del periodo
        $imponibileAttive  = $this->imponibileAttive($tenant, $liq->data_inizio->toDateString(), $liq->data_fine->toDateString());
        $imponibilePassive = $this->imponibilePassive($tenant, $liq->data_inizio->toDateString(), $liq->data_fine->toDateString());

        $ivaDebito  = (float) $liq->iva_debito;
        $ivaCredito = (float) $liq->iva_credito;
        $saldo      = round($ivaDebito - $ivaCredito, 2);

        return [
            'vp2_base' => round($imponibileAttive,  2),
            'vp2_iva'  => $ivaDebito,
            'vp3_base' => round($imponibilePassive, 2),
            'vp3_iva'  => $ivaCredito,
            'vp4'      => $ivaDebito,
            'vp5'      => $ivaCredito,
            'vp6_dovuta'  => max(0.0, $saldo),
            'vp6_credito' => max(0.0, -$saldo),
            'vp7'      => 0.0, // debito periodo prec. (già incluso in saldo_finale)
            'vp8'      => max(0.0, -(float) $liq->credito_periodo_precedente),
            'vp9'      => 0.0,
            'vp12'     => (float) ($liq->interessi_trimestrali ?? 0),
            'vp13'     => (float) ($liq->acconto_versato ?? 0),
            'vp14_da_versare' => max(0.0,  (float) $liq->saldo_finale),
            'vp14_a_credito'  => max(0.0, -(float) $liq->saldo_finale),
        ];
    }

    /**
     * Calcola i dati direttamente dalle fatture quando non c'è liquidazione chiusa.
     */
    private function datiCalcolatiDaFatture(Tenant $tenant, int $anno, int $periodo, string $tipoPeriodo): array
    {
        [$from, $to] = $this->rangeDate($anno, $periodo, $tipoPeriodo);

        $ivaDebito  = (float) FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->whereBetween('data_fattura', [$from, $to])
            ->sum('iva_totale');

        $ivaCredito = (float) FatturaPassiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('data_registrazione', [$from, $to])
            ->sum('iva_totale');

        $imponibileAttive  = $this->imponibileAttive($tenant, $from, $to);
        $imponibilePassive = $this->imponibilePassive($tenant, $from, $to);
        $saldo             = round($ivaDebito - $ivaCredito, 2);

        return [
            'vp2_base' => round($imponibileAttive,  2),
            'vp2_iva'  => round($ivaDebito,         2),
            'vp3_base' => round($imponibilePassive, 2),
            'vp3_iva'  => round($ivaCredito,        2),
            'vp4'      => round($ivaDebito,         2),
            'vp5'      => round($ivaCredito,        2),
            'vp6_dovuta'  => max(0.0, $saldo),
            'vp6_credito' => max(0.0, -$saldo),
            'vp7'      => 0.0,
            'vp8'      => 0.0,
            'vp9'      => 0.0,
            'vp12'     => 0.0,
            'vp13'     => 0.0,
            'vp14_da_versare' => max(0.0,  $saldo),
            'vp14_a_credito'  => max(0.0, -$saldo),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Render XML
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  array<array{tipo: string, periodo: int, anno: int, dati: array}>  $moduli
     */
    private function renderXml(Tenant $tenant, int $anno, int $trimestre, array $moduli): string
    {
        $cf          = htmlspecialchars($tenant->codice_fiscale ?? $tenant->partita_iva ?? '', ENT_XML1);
        $denominazione = htmlspecialchars($tenant->name, ENT_XML1);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(
            'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/liquidazioni/v1.0',
            'n1:ComunicazioneLiquidazioniPeriodiche'
        );
        $root->setAttribute('versione', 'DAT_01.1');
        $dom->appendChild($root);

        // Intestazione
        $intestazione = $dom->createElement('Intestazione');
        $intestazione->appendChild($dom->createElement('CodiceFiscaleDichiarante', $cf));
        $intestazione->appendChild($dom->createElement('CaricaDichiarante', '01'));
        $root->appendChild($intestazione);

        // Contribuente
        $contribuente = $dom->createElement('Contribuente');
        $dati = $dom->createElement('Dati');
        $dati->appendChild($dom->createElement('CodiceFiscale', $cf));
        $dati->appendChild($dom->createElement('Denominazione', $denominazione));
        $contribuente->appendChild($dati);
        $root->appendChild($contribuente);

        // DatiContabili
        $datiContabili = $dom->createElement('DatiContabili');
        $datiContabili->setAttribute('numero', '1');

        foreach ($moduli as $i => $modulo) {
            $moduloEl = $dom->createElement('Modulo');
            $moduloEl->setAttribute('numero', (string) ($i + 1));

            $d = $modulo['dati'];

            $moduloEl->appendChild($dom->createElement('VP1Anno', (string) $modulo['anno']));

            if ($modulo['tipo'] === 'mensile') {
                $moduloEl->appendChild($dom->createElement('VP1Mese', (string) $modulo['periodo']));
            } else {
                $moduloEl->appendChild($dom->createElement('VP1Trimestre', (string) $modulo['periodo']));
            }

            $this->appendIfNonZero($dom, $moduloEl, 'VP2Base', $d['vp2_base']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP2Iva',  $d['vp2_iva']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP3Base', $d['vp3_base']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP3Iva',  $d['vp3_iva']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP4',     $d['vp4']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP5',     $d['vp5']);

            if ($d['vp6_dovuta'] > 0) {
                $this->appendIfNonZero($dom, $moduloEl, 'VP6Dovuta',  $d['vp6_dovuta']);
            } elseif ($d['vp6_credito'] > 0) {
                $this->appendIfNonZero($dom, $moduloEl, 'VP6Credito', $d['vp6_credito']);
            }

            $this->appendIfNonZero($dom, $moduloEl, 'VP7',  $d['vp7']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP8',  $d['vp8']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP9',  $d['vp9']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP12', $d['vp12']);
            $this->appendIfNonZero($dom, $moduloEl, 'VP13', $d['vp13']);

            if ($d['vp14_da_versare'] > 0) {
                $this->appendIfNonZero($dom, $moduloEl, 'VP14DaVersare', $d['vp14_da_versare']);
            } elseif ($d['vp14_a_credito'] > 0) {
                $this->appendIfNonZero($dom, $moduloEl, 'VP14ACredito', $d['vp14_a_credito']);
            }

            $datiContabili->appendChild($moduloEl);
        }

        $root->appendChild($datiContabili);

        return $dom->saveXML();
    }

    private function appendIfNonZero(\DOMDocument $dom, \DOMElement $parent, string $tag, float $value): void
    {
        if ($value != 0) {
            $parent->appendChild($dom->createElement($tag, number_format(abs($value), 2, '.', '')));
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /** @return int[] mesi 1-3 del trimestre */
    private function mesiDelTrimestre(int $trimestre): array
    {
        return match ($trimestre) {
            1 => [1, 2, 3],
            2 => [4, 5, 6],
            3 => [7, 8, 9],
            4 => [10, 11, 12],
        };
    }

    /** @return array{0: string, 1: string} */
    private function rangeDate(int $anno, int $periodo, string $tipoPeriodo): array
    {
        if ($tipoPeriodo === LiquidazioneIva::TIPO_MENSILE) {
            $inizio = Carbon::create($anno, $periodo, 1);
            return [$inizio->toDateString(), $inizio->copy()->endOfMonth()->toDateString()];
        }

        // Trimestrale
        $mesePrimoMese = ($periodo - 1) * 3 + 1;
        $inizio = Carbon::create($anno, $mesePrimoMese, 1);
        $fine   = $inizio->copy()->addMonths(3)->subDay();
        return [$inizio->toDateString(), $fine->toDateString()];
    }

    private function imponibileAttive(Tenant $tenant, string $from, string $to): float
    {
        return (float) FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->whereBetween('data_fattura', [$from, $to])
            ->sum('imponibile_totale');
    }

    private function imponibilePassive(Tenant $tenant, string $from, string $to): float
    {
        return (float) FatturaPassiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('data_registrazione', [$from, $to])
            ->sum('imponibile_totale');
    }
}
