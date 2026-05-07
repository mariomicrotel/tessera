<?php

namespace App\Services;

use App\Exceptions\IvaAlreadyClosedException;
use App\Exceptions\IvaPeriodoNonValidoException;
use App\Models\Conto;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\LiquidazioneIva;
use App\Models\PrimaNotaEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servizio per la gestione del modulo IVA.
 *
 * Gestisce:
 *  - Calcolo liquidazioni periodiche (mensili e trimestrali)
 *  - Chiusura definitiva delle liquidazioni con registrazione in prima nota
 *  - Registro acquisti (fatture passive del periodo)
 *  - Registro vendite (fatture attive del periodo)
 *
 * NOTA: i metodi accettano `$tenantId` esplicitamente per permettere l'uso
 * sia da context web (tenant già in container) sia da Artisan commands / code
 * chiamati senza tenant nel container. Internamente si usa `withoutGlobalScope`
 * + filtro esplicito per evitare ambiguità.
 */
class IvaService
{
    // ─────────────────────────────────────────────────────────────────────────
    // 1. calcolaLiquidazione
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcola i saldi IVA per un determinato periodo senza persistere nulla.
     *
     * Algoritmo:
     *  - IVA debito = somma iva_totale delle fatture **attive** emesse nel periodo
     *  - IVA credito = somma iva_totale delle fatture **passive** registrate nel periodo
     *    (solo quota detraibile, al netto della parte indetraibile delle righe)
     *  - saldo_periodo = iva_debito − iva_credito
     *  - credito_precedente = |saldo_finale| della liquidazione precedente
     *    se negativo (= credito), altrimenti 0
     *  - saldo_finale = saldo_periodo − credito_precedente
     *    Se > 0: IVA a debito (da versare); se < 0: credito da riportare al prossimo periodo
     *
     * @param  int    $tenantId   UUID del tenant come stringa binaria/UUID nativo
     * @param  int    $anno       Esercizio fiscale (es. 2026)
     * @param  int    $periodo    Numero periodo: 1-12 per mensile, 1-4 per trimestrale
     * @param  string $tipoPeriodo 'mensile' | 'trimestrale'
     *
     * @return array{
     *   iva_debito: float,
     *   iva_credito: float,
     *   saldo_periodo: float,
     *   credito_periodo_precedente: float,
     *   saldo_finale: float,
     *   data_inizio: string,
     *   data_fine: string,
     * }
     *
     * @throws IvaPeriodoNonValidoException se i parametri non sono validi
     */
    public function calcolaLiquidazione(
        string $tenantId,
        int $anno,
        int $periodo,
        string $tipoPeriodo,
    ): array {
        [$dataInizio, $dataFine] = $this->rangeDate($anno, $periodo, $tipoPeriodo);

        // IVA debito: somma iva_totale fatture attive emesse nel periodo
        $ivaDebito = (float) FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->whereBetween('data_fattura', [$dataInizio, $dataFine])
            ->sum('iva_totale');

        // IVA credito: iva detraibile dalle fatture passive registrate nel periodo.
        // La quota indetraibile (iva_indetraibile) è già esclusa a livello di riga;
        // qui si somma iva_totale − totale iva_indetraibile delle righe.
        $ivaCredito = (float) FatturaPassiva::withoutGlobalScope('tenant')
            ->where('fatture_passive.tenant_id', $tenantId)
            ->whereNull('fatture_passive.deleted_at')
            ->where('fatture_passive.stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('fatture_passive.data_registrazione', [$dataInizio, $dataFine])
            ->leftJoin('righe_fattura_passiva', function ($join) use ($tenantId) {
                $join->on('righe_fattura_passiva.fattura_passiva_id', '=', 'fatture_passive.id')
                     ->where('righe_fattura_passiva.tenant_id', '=', $tenantId);
            })
            ->selectRaw('
                SUM(fatture_passive.iva_totale) -
                COALESCE(SUM(righe_fattura_passiva.iva_indetraibile), 0) AS iva_detraibile
            ')
            ->value('iva_detraibile') ?? 0.0;

        $ivaCredito = (float) $ivaCredito;

        // Credito dal periodo precedente (se l'ultimo saldo_finale era negativo = credito)
        $creditoPrecedente = $this->creditoPrecedente($tenantId, $anno, $periodo, $tipoPeriodo);

        $saldoPeriodo = round($ivaDebito - $ivaCredito, 2);
        $saldoFinale  = round($saldoPeriodo - $creditoPrecedente, 2);

        return [
            'iva_debito'                => round($ivaDebito, 2),
            'iva_credito'               => round($ivaCredito, 2),
            'saldo_periodo'             => $saldoPeriodo,
            'credito_periodo_precedente' => $creditoPrecedente,
            'saldo_finale'              => $saldoFinale,
            'data_inizio'               => $dataInizio,
            'data_fine'                 => $dataFine,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. chiudiLiquidazione
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Chiude definitivamente la liquidazione IVA del periodo.
     *
     * Passi:
     *  1. Verifica che non esista già una liquidazione definitiva (idempotenza)
     *  2. Calcola i saldi via calcolaLiquidazione()
     *  3. Upsert su liquidazioni_iva con status='definitiva'
     *  4. Aggancia le fatture del periodo (imposta liquidazione_iva_id)
     *  5. Registra in prima nota (IVA a debito o credito), se esiste un conto
     *
     * @throws IvaAlreadyClosedException   se la liquidazione è già definitiva o versata
     * @throws IvaPeriodoNonValidoException se i parametri non sono validi
     */
    public function chiudiLiquidazione(
        string $tenantId,
        int $anno,
        int $periodo,
        string $tipoPeriodo,
    ): LiquidazioneIva {
        // Controlla se esiste già una liquidazione chiusa per questo periodo
        $esistente = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('anno', $anno)
            ->where('periodo', $periodo)
            ->where('tipo_periodo', $tipoPeriodo)
            ->first();

        if ($esistente && ! $esistente->isBozza()) {
            throw new IvaAlreadyClosedException($anno, $periodo, $tipoPeriodo);
        }

        return DB::transaction(function () use ($tenantId, $anno, $periodo, $tipoPeriodo, $esistente) {
            $saldi = $this->calcolaLiquidazione($tenantId, $anno, $periodo, $tipoPeriodo);

            // Crea o aggiorna la liquidazione
            $liquidazione = $esistente ?? new LiquidazioneIva();
            $liquidazione->fill([
                'tenant_id'                  => $tenantId,
                'anno'                       => $anno,
                'periodo'                    => $periodo,
                'tipo_periodo'               => $tipoPeriodo,
                'data_inizio'                => $saldi['data_inizio'],
                'data_fine'                  => $saldi['data_fine'],
                'iva_debito'                 => $saldi['iva_debito'],
                'iva_credito'                => $saldi['iva_credito'],
                'credito_periodo_precedente' => $saldi['credito_periodo_precedente'],
                'saldo_periodo'              => $saldi['saldo_periodo'],
                'saldo_finale'               => $saldi['saldo_finale'],
                'status'                     => LiquidazioneIva::STATUS_DEFINITIVA,
                'data_chiusura'              => Carbon::today(),
            ]);
            $liquidazione->save();

            // Aggancia le fatture passive del periodo
            FatturaPassiva::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
                ->whereBetween('data_registrazione', [$saldi['data_inizio'], $saldi['data_fine']])
                ->update(['liquidazione_iva_id' => $liquidazione->id]);

            // Aggancia le fatture attive del periodo
            FatturaAttiva::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereNotIn('stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
                ->whereBetween('data_fattura', [$saldi['data_inizio'], $saldi['data_fine']])
                ->update(['liquidazione_iva_id' => $liquidazione->id]);

            // Prima nota: registra il saldo IVA (solo se esiste un conto)
            $this->registraPrimaNota($tenantId, $liquidazione, $saldi);

            return $liquidazione->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. getRegistroAcquisti
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il registro IVA acquisti per un mese specifico.
     *
     * Ogni elemento della Collection include:
     *  - dati della fattura passiva
     *  - righe raggruppate per aliquota con imponibile, iva, iva_indetraibile
     *
     * @param  int $periodo   Mese (1-12)
     *
     * @return Collection<int, array{
     *   id: int,
     *   numero_fattura: string,
     *   data_registrazione: string,
     *   data_fattura: string,
     *   esigibilita: string,
     *   tipo_documento: string,
     *   imponibile_totale: string,
     *   iva_totale: string,
     *   totale_documento: string,
     *   righe: Collection,
     * }>
     *
     * @throws IvaPeriodoNonValidoException se il mese non è valido
     */
    public function getRegistroAcquisti(string $tenantId, int $anno, int $periodo): Collection
    {
        $this->validaMese($periodo);
        [$dataInizio, $dataFine] = $this->rangeDate($anno, $periodo, LiquidazioneIva::TIPO_MENSILE);

        return FatturaPassiva::withoutGlobalScope('tenant')
            ->with([
                'righe' => fn ($q) => $q
                    ->with('codiceIva')
                    ->withoutGlobalScope('tenant')
                    ->where('righe_fattura_passiva.tenant_id', $tenantId),
            ])
            ->where('fatture_passive.tenant_id', $tenantId)
            ->whereNull('fatture_passive.deleted_at')
            ->where('fatture_passive.stato_pagamento', '!=', FatturaPassiva::STATO_ANNULLATA)
            ->whereBetween('fatture_passive.data_registrazione', [$dataInizio, $dataFine])
            ->orderBy('fatture_passive.data_registrazione')
            ->orderBy('fatture_passive.id')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. getRegistroVendite
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il registro IVA vendite per un mese specifico.
     *
     * @param  int $periodo   Mese (1-12)
     *
     * @return Collection<int, FatturaAttiva>
     *
     * @throws IvaPeriodoNonValidoException se il mese non è valido
     */
    public function getRegistroVendite(string $tenantId, int $anno, int $periodo): Collection
    {
        $this->validaMese($periodo);
        [$dataInizio, $dataFine] = $this->rangeDate($anno, $periodo, LiquidazioneIva::TIPO_MENSILE);

        return FatturaAttiva::withoutGlobalScope('tenant')
            ->with([
                'righe' => fn ($q) => $q
                    ->with('codiceIva')
                    ->withoutGlobalScope('tenant')
                    ->where('righe_fattura_attiva.tenant_id', $tenantId),
            ])
            ->where('fatture_attive.tenant_id', $tenantId)
            ->whereNull('fatture_attive.deleted_at')
            ->whereNotIn('fatture_attive.stato', [FatturaAttiva::STATO_ANNULLATA, FatturaAttiva::STATO_BOZZA])
            ->whereBetween('fatture_attive.data_fattura', [$dataInizio, $dataFine])
            ->orderBy('fatture_attive.data_fattura')
            ->orderBy('fatture_attive.progressivo')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper privati
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcola le date di inizio e fine del periodo IVA.
     *
     * @return array{0: string, 1: string}  [dataInizio, dataFine] in formato Y-m-d
     *
     * @throws IvaPeriodoNonValidoException
     */
    public function rangeDate(int $anno, int $periodo, string $tipoPeriodo): array
    {
        if ($anno < 2000 || $anno > 2100) {
            throw new IvaPeriodoNonValidoException("Anno non valido: {$anno}.");
        }

        return match ($tipoPeriodo) {
            LiquidazioneIva::TIPO_MENSILE     => $this->rangeMensile($anno, $periodo),
            LiquidazioneIva::TIPO_TRIMESTRALE => $this->rangeTrimestrale($anno, $periodo),
            LiquidazioneIva::TIPO_ANNUALE     => $this->rangeAnnuale($anno, $periodo),
            default => throw new IvaPeriodoNonValidoException("Tipo periodo sconosciuto: '{$tipoPeriodo}'."),
        };
    }

    /**
     * Range per periodo mensile (periodo = mese 1-12).
     *
     * @return array{0: string, 1: string}
     */
    private function rangeMensile(int $anno, int $periodo): array
    {
        $this->validaMese($periodo);

        $inizio = Carbon::create($anno, $periodo, 1)->startOfDay();
        $fine   = $inizio->copy()->endOfMonth();

        return [$inizio->toDateString(), $fine->toDateString()];
    }

    /**
     * Range per regime annuale (periodo sempre 1 = intero anno solare).
     *
     * @return array{0: string, 1: string}
     */
    private function rangeAnnuale(int $anno, int $periodo): array
    {
        if ($periodo !== 1) {
            throw new IvaPeriodoNonValidoException("Per il regime annuale il periodo deve essere 1.");
        }

        return [
            Carbon::create($anno, 1, 1)->startOfDay()->toDateString(),
            Carbon::create($anno, 12, 31)->endOfDay()->toDateString(),
        ];
    }

    /**
     * Range per periodo trimestrale (periodo = 1,2,3,4).
     *
     * @return array{0: string, 1: string}
     */
    private function rangeTrimestrale(int $anno, int $periodo): array
    {
        if ($periodo < 1 || $periodo > 4) {
            throw new IvaPeriodoNonValidoException("Periodo trimestrale non valido: {$periodo}. Valori ammessi: 1-4.");
        }

        // Q1=Jan-Mar, Q2=Apr-Jun, Q3=Jul-Sep, Q4=Oct-Dec
        $meseInizio = ($periodo - 1) * 3 + 1;
        $meseFine   = $meseInizio + 2;

        $inizio = Carbon::create($anno, $meseInizio, 1)->startOfDay();
        $fine   = Carbon::create($anno, $meseFine, 1)->endOfMonth();

        return [$inizio->toDateString(), $fine->toDateString()];
    }

    /**
     * Recupera il credito residuo dalla liquidazione precedente.
     *
     * Se il saldo_finale dell'ultimo periodo chiuso era negativo (credito d'imposta),
     * il suo valore assoluto viene riportato come credito_periodo_precedente.
     */
    private function creditoPrecedente(
        string $tenantId,
        int $anno,
        int $periodo,
        string $tipoPeriodo,
    ): float {
        // Calcola anno/periodo del periodo precedente
        [$annoPrecedente, $periodoPrecedente] = $this->periodoPrecedente($anno, $periodo, $tipoPeriodo);

        $liqPrecedente = LiquidazioneIva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('anno', $annoPrecedente)
            ->where('periodo', $periodoPrecedente)
            ->where('tipo_periodo', $tipoPeriodo)
            ->whereIn('status', [LiquidazioneIva::STATUS_DEFINITIVA, LiquidazioneIva::STATUS_VERSATA])
            ->first();

        if (! $liqPrecedente) {
            return 0.0;
        }

        $saldoPrec = (float) $liqPrecedente->saldo_finale;

        // Credito solo se saldo era negativo (ovvero IVA a credito del periodo precedente)
        return $saldoPrec < 0 ? abs($saldoPrec) : 0.0;
    }

    /**
     * Calcola il periodo immediatamente precedente.
     *
     * @return array{0: int, 1: int}  [anno, periodo]
     */
    private function periodoPrecedente(int $anno, int $periodo, string $tipoPeriodo): array
    {
        $maxPeriodi = $tipoPeriodo === LiquidazioneIva::TIPO_MENSILE ? 12 : 4;

        if ($periodo === 1) {
            return [$anno - 1, $maxPeriodi];
        }

        return [$anno, $periodo - 1];
    }

    /**
     * Valida che il mese sia compreso tra 1 e 12.
     *
     * @throws IvaPeriodoNonValidoException
     */
    private function validaMese(int $mese): void
    {
        if ($mese < 1 || $mese > 12) {
            throw new IvaPeriodoNonValidoException("Mese non valido: {$mese}. Valori ammessi: 1-12.");
        }
    }

    /**
     * Registra la chiusura della liquidazione in prima nota.
     *
     * Crea una voce con rendiconto_code='iva_liquidazione':
     *  - importo positivo → IVA a debito (da versare)
     *  - importo negativo → credito d'imposta (da riportare)
     *
     * Se non esiste nessun conto attivo, la registrazione viene saltata
     * (la liquidazione rimane comunque definitiva).
     */
    private function registraPrimaNota(
        string $tenantId,
        LiquidazioneIva $liquidazione,
        array $saldi,
    ): void {
        /** @var Conto|null $conto */
        $conto = Conto::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('attivo', true)
            ->orderBy('ordine')
            ->orderBy('id')
            ->first();

        if (! $conto) {
            return;
        }

        $saldoFinale = $saldi['saldo_finale'];

        if (abs($saldoFinale) < 0.01) {
            return; // Saldo zero: nessuna registrazione necessaria
        }

        $label = $liquidazione->periodo_label;

        if ($saldoFinale > 0) {
            $descrizione = "Liquidazione IVA {$label} — IVA a debito €" . number_format($saldoFinale, 2, ',', '.');
        } else {
            $descrizione = "Liquidazione IVA {$label} — Credito IVA €" . number_format(abs($saldoFinale), 2, ',', '.');
        }

        PrimaNotaEntry::create([
            'conto_id'        => $conto->id,
            'rendiconto_code' => 'iva_liquidazione',
            'entryable_type'  => LiquidazioneIva::class,
            'entryable_id'    => $liquidazione->id,
            'date'            => $liquidazione->data_chiusura ?? Carbon::today(),
            'amount'          => $saldoFinale,
            'description'     => $descrizione,
        ]);
    }
}
