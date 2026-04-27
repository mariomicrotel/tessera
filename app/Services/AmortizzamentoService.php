<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service CORE per il calcolo e la registrazione degli ammortamenti.
 *
 * Normativa di riferimento: DM 31/12/1988 (coefficienti ministeriali TUIR).
 *
 * Regole applicate:
 *  - Primo anno: 50% del coefficiente (se asset.primo_anno_ridotto = true)
 *  - Ordinario: coefficiente pieno negli anni successivi
 *  - Ridotto:   coefficiente personalizzato inferiore all'ordinario (scelta dell'impresa)
 *  - Accelerato: coefficiente × 2, massimo nei primi 3 anni
 *  - Anticipato: coefficiente × 2 per i primi 3 anni, poi ordinario
 *  - Stop automatico quando valore residuo ≤ 0
 *
 * Flusso tipico:
 *   1. generaRigheAnno()       → crea AssetDepreciationSchedule in stato "bozza"
 *   2. (admin rivede le quote) → può modificare quota_registrata
 *   3. registraQuotaInPrimaNota() → crea MovimentoContabile e lo conferma
 *   4. confermaEsercizio()     → batch: conferma tutte le bozze dell'anno
 */
class AmortizzamentoService
{
    public function __construct(
        private readonly MovimentoContabileService $movimentoService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Calcolo aliquota
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcola l'aliquota effettiva da applicare a un cespite per un dato esercizio.
     *
     * @param  Asset  $asset         Cespite
     * @param  int    $anno          Esercizio
     * @param  int    $annoInizio    Anno di inizio ammortamento
     * @param  float  $fondoCumulato Fondo già accumulato (pre-calcolo)
     * @param  float  $costoStorico  Costo storico del cespite
     * @return float                 Aliquota percentuale (0 se ammortizzato)
     */
    public function aliquotaAnno(Asset $asset, int $anno, int $annoInizio, float $fondoCumulato, float $costoStorico): float
    {
        // Già completamente ammortizzato
        if ($fondoCumulato >= $costoStorico) {
            return 0.0;
        }

        $coefBase = $asset->aliquotaEffettiva();
        if ($coefBase <= 0) {
            return 0.0;
        }

        $anniDallaPartenza = $anno - $annoInizio; // 0 = primo anno, 1 = secondo, ...
        $isPrimoAnno       = ($anniDallaPartenza === 0);

        // Aliquota grezza prima dell'eventuale riduzione 50% primo anno
        $aliquotaGrezza = match ($asset->metodo_ammortamento) {

            // Ridotto: l'impresa sceglie un'aliquota inferiore all'ordinario.
            // Usiamo aliquota_custom se presente; altrimenti 50% del coeff. base.
            Asset::METODO_RIDOTTO => $asset->aliquota_custom
                ? (float) $asset->aliquota_custom
                : round($coefBase / 2, 2),

            // Accelerato: doppio del coefficiente (nei limiti civilistici)
            Asset::METODO_ACCELERATO => min($coefBase * 2, 100.0),

            // Anticipato: doppio nei primi 3 anni solari (dall'anno di acquisto),
            // poi torna all'ordinario
            Asset::METODO_ANTICIPATO => ($anniDallaPartenza < 3)
                ? min($coefBase * 2, 100.0)
                : $coefBase,

            // Ordinario (default)
            default => $coefBase,
        };

        // Riduzione al 50% per il primo anno (DM 31/12/1988, art. 1 co. 3)
        if ($isPrimoAnno && $asset->primo_anno_ridotto) {
            $aliquotaGrezza = round($aliquotaGrezza / 2, 2);
        }

        return $aliquotaGrezza;
    }

    /**
     * Calcola la quota di ammortamento per un cespite in un dato esercizio.
     *
     * @return array{quota_calcolata:float, aliquota_applicata:float, fondo_inizio:float, fondo_fine:float, valore_residuo_fine:float}
     */
    public function calcolaQuotaEsercizio(Asset $asset, int $anno): array
    {
        $costoStorico  = (float) $asset->costo_storico;
        $annoInizio    = (int) ($asset->data_inizio_ammortamento ?? $asset->purchase_date)?->format('Y');

        // Fondo accumulato fino all'anno precedente (solo schedule definitive)
        $fondoPrecedente = (float) $asset->depreciationSchedules()
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->where('esercizio', '<', $anno)
            ->sum('quota_registrata');

        // Anche le schedule bozza di anni precedenti contribuiscono al fondo virtuale
        // (per il preview, usiamo tutte le bozze precedenti)
        $fondoPrecedenteConBozze = (float) $asset->depreciationSchedules()
            ->whereIn('stato', [AssetDepreciationSchedule::STATO_DEFINITIVO, AssetDepreciationSchedule::STATO_BOZZA])
            ->where('esercizio', '<', $anno)
            ->sum('quota_calcolata');

        $fondoInizio = $fondoPrecedente; // fondo "ufficiale" basato solo su definitivi

        $aliquota = $this->aliquotaAnno($asset, $anno, $annoInizio, $fondoPrecedenteConBozze, $costoStorico);

        $quotaCalcolata = 0.0;
        if ($aliquota > 0 && $fondoInizio < $costoStorico) {
            $quotaCalcolata = min(
                round($costoStorico * $aliquota / 100, 2),
                round($costoStorico - $fondoInizio, 2)
            );
        }

        $fondoFine   = round($fondoInizio + $quotaCalcolata, 2);
        $valoreResiduo = (float) max(0, round($costoStorico - $fondoFine, 2));

        return [
            'quota_calcolata'          => $quotaCalcolata,
            'aliquota_applicata'       => $aliquota,
            'fondo_inizio_anno'        => $fondoInizio,
            'fondo_fine_anno'          => $fondoFine,
            'valore_residuo_fine_anno' => $valoreResiduo,
            'deducibilita_applicata'   => (float) $asset->percentuale_deducibilita,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generazione batch schedules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera (o aggiorna) le AssetDepreciationSchedule in bozza per tutti i
     * cespiti attivi del tenant per l'esercizio specificato.
     *
     * Se una bozza esiste già per un cespite/anno, la sovrascrive con i nuovi calcoli.
     * Non tocca le schedule già in stato "definitivo".
     *
     * @param  int         $esercizio  Anno fiscale
     * @param  Tenant      $tenant     Tenant corrente
     * @return Collection<AssetDepreciationSchedule>  Schedule generate/aggiornate
     */
    public function generaRigheAnno(int $esercizio, Tenant $tenant): Collection
    {
        $cespiti = Asset::where('tenant_id', $tenant->id)
            ->where('stato', Asset::STATO_IN_USO)
            ->where('costo_storico', '>', 0)
            ->with(['category', 'depreciationSchedules'])
            ->get();

        $scheduleCreate = collect();

        DB::transaction(function () use ($cespiti, $esercizio, $tenant, &$scheduleCreate) {
            foreach ($cespiti as $asset) {
                // Salta se esiste già una schedule definitiva per quest'anno
                $esisteDefinitiva = $asset->depreciationSchedules
                    ->where('esercizio', $esercizio)
                    ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
                    ->isNotEmpty();

                if ($esisteDefinitiva) {
                    continue;
                }

                $calcolo = $this->calcolaQuotaEsercizio($asset, $esercizio);

                if ($calcolo['quota_calcolata'] <= 0) {
                    continue; // Cespite già completamente ammortizzato
                }

                $schedule = AssetDepreciationSchedule::updateOrCreate(
                    [
                        'tenant_id'  => $tenant->id,
                        'asset_id'   => $asset->id,
                        'esercizio'  => $esercizio,
                        'stato'      => AssetDepreciationSchedule::STATO_BOZZA,
                    ],
                    array_merge($calcolo, [
                        'quota_registrata' => $calcolo['quota_calcolata'], // default = calcolata
                        'stato'            => AssetDepreciationSchedule::STATO_BOZZA,
                    ])
                );

                $scheduleCreate->push($schedule);
            }
        });

        return $scheduleCreate;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Registrazione in Prima Nota
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registra la quota di ammortamento in prima nota (partita doppia).
     *
     * Scrittura generata:
     *   DARE:  Costo ammortamento (es. 7.25.15.001) — incremento costo
     *   AVERE: Fondo ammortamento (es. 1.25.15.001) — incremento fondo rettificativo
     *
     * Porta la schedule da "bozza" a "definitivo" e collega il movimento.
     *
     * @param  AssetDepreciationSchedule $schedule    Schedule da registrare
     * @param  Tenant                   $tenant      Tenant corrente
     * @param  string|null              $dataReg     Data registrazione (default: oggi)
     * @return MovimentoContabile
     *
     * @throws RuntimeException se la schedule non è in bozza o mancano i conti
     */
    public function registraQuotaInPrimaNota(
        AssetDepreciationSchedule $schedule,
        Tenant $tenant,
        ?string $dataReg = null
    ): MovimentoContabile {
        if (! $schedule->isBozza()) {
            throw new RuntimeException(
                "La schedule ID {$schedule->id} non è in bozza: impossibile registrare."
            );
        }

        $asset = $schedule->asset ?? $schedule->load('asset')->asset;

        if ($schedule->quota_registrata <= 0) {
            throw new RuntimeException(
                "La quota registrata del cespite '{$asset->name}' per l'esercizio {$schedule->esercizio} è zero."
            );
        }

        // Risolve i conti: cespite override → categoria default
        $contoAmmortamento = $this->resolveContoAmmortamento($asset, $tenant);
        $contoFondo        = $this->resolveContoFondo($asset, $tenant);

        if (! $contoAmmortamento || ! $contoFondo) {
            throw new RuntimeException(
                "Cespite '{$asset->name}': conti contabili mancanti (conto ammortamento o fondo)."
                ." Configurare i conti nella categoria o nel cespite."
            );
        }

        $causale = $this->trovaCausaleAMM($tenant);
        if (! $causale) {
            throw new RuntimeException(
                "Causale 'AMM' non trovata per il tenant. Eseguire CausaliContabiliDiSistemaSeeder."
            );
        }

        $quota   = (float) $schedule->quota_registrata;
        $dataReg = $dataReg ?? now()->toDateString();
        $desc    = "Ammortamento {$schedule->esercizio} — {$asset->name}";

        return DB::transaction(function () use (
            $schedule, $asset, $tenant, $causale,
            $contoAmmortamento, $contoFondo, $quota, $dataReg, $desc
        ) {
            // Crea movimento in prima nota
            $movimento = $this->movimentoService->crea($tenant, [
                'data_registrazione' => $dataReg,
                'causale_id'         => $causale->id,
                'descrizione'        => $desc,
                'numero_documento'   => "AMM-{$schedule->esercizio}-{$asset->code}",
                'anno_esercizio'     => $schedule->esercizio,
            ], [
                [
                    'conto_contabile_id' => $contoAmmortamento->id,
                    'importo_dare'       => $quota,
                    'descrizione'        => $desc,
                ],
                [
                    'conto_contabile_id' => $contoFondo->id,
                    'importo_avere'      => $quota,
                    'descrizione'        => $desc,
                ],
            ]);

            // Conferma immediatamente il movimento
            $this->movimentoService->conferma($movimento);

            // Aggiorna la schedule: definitiva, con FK al movimento
            $schedule->update([
                'stato'                   => AssetDepreciationSchedule::STATO_DEFINITIVO,
                'movimento_contabile_id'  => $movimento->id,
                'data_registrazione'      => $dataReg,
                'fondo_fine_anno'         => $schedule->fondo_inizio_anno + $quota,
                'valore_residuo_fine_anno' => max(0, (float) $asset->costo_storico - ($schedule->fondo_inizio_anno + $quota)),
            ]);

            return $movimento;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Conferma batch esercizio
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Registra in prima nota tutte le schedule in bozza per un dato esercizio.
     *
     * @param  int    $esercizio     Anno fiscale
     * @param  Tenant $tenant        Tenant corrente
     * @param  string $dataReg       Data registrazione (default: 31/12/esercizio)
     * @return array{ok:int, errori:array}  Conteggio successi e array errori
     */
    public function confermaEsercizio(int $esercizio, Tenant $tenant, ?string $dataReg = null): array
    {
        $dataReg ??= "{$esercizio}-12-31";

        $schedules = AssetDepreciationSchedule::where('tenant_id', $tenant->id)
            ->where('esercizio', $esercizio)
            ->where('stato', AssetDepreciationSchedule::STATO_BOZZA)
            ->with('asset.category')
            ->get();

        $ok     = 0;
        $errori = [];

        foreach ($schedules as $schedule) {
            try {
                $this->registraQuotaInPrimaNota($schedule, $tenant, $dataReg);
                $ok++;
            } catch (\Throwable $e) {
                $errori[] = [
                    'asset'   => $schedule->asset?->name ?? "ID {$schedule->asset_id}",
                    'errore'  => $e->getMessage(),
                ];
            }
        }

        return compact('ok', 'errori');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Riepilogo esercizio
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il riepilogo delle schedule per l'esercizio specificato.
     *
     * @return array{bozze:int, definitive:int, totale_quota:float, cespiti_ammortizzati:int}
     */
    public function riepilogoEsercizio(int $esercizio, Tenant $tenant): array
    {
        $schedules = AssetDepreciationSchedule::where('tenant_id', $tenant->id)
            ->where('esercizio', $esercizio)
            ->get();

        return [
            'bozze'               => $schedules->where('stato', AssetDepreciationSchedule::STATO_BOZZA)->count(),
            'definitive'          => $schedules->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)->count(),
            'totale_quota'        => round($schedules->sum('quota_registrata'), 2),
            'cespiti_ammortizzati' => $schedules->where('valore_residuo_fine_anno', '<=', 0)->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals: risoluzione conti
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Risolve il conto "Costo ammortamento" per il cespite.
     * Priorità: categoria.conto_ammortamento_default → fallback per codice '7.25'
     */
    private function resolveContoAmmortamento(Asset $asset, Tenant $tenant): ?ContoContabile
    {
        // Cerca il conto ammortamento dal default di categoria
        $categoria = $asset->category;
        if ($categoria && $categoria->conto_ammortamento_default_id) {
            return ContoContabile::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('id', $categoria->conto_ammortamento_default_id)
                ->first();
        }

        // Fallback: primo conto movimentabile con codice che inizia per '7.25'
        return ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'like', '7.25%')
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->first();
    }

    /**
     * Risolve il conto "Fondo ammortamento" per il cespite.
     * Priorità: cespite.conto_fondo_id → categoria.conto_fondo_default → fallback '1.25'
     */
    private function resolveContoFondo(Asset $asset, Tenant $tenant): ?ContoContabile
    {
        // Override diretto sul cespite
        if ($asset->conto_fondo_id) {
            return ContoContabile::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('id', $asset->conto_fondo_id)
                ->first();
        }

        // Default di categoria
        $categoria = $asset->category;
        if ($categoria && $categoria->conto_fondo_default_id) {
            return ContoContabile::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('id', $categoria->conto_fondo_default_id)
                ->first();
        }

        // Fallback: primo conto fondo ammortamento movimentabile
        return ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'like', '1.25%')
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->first();
    }

    /**
     * Trova la causale 'AMM' per il tenant.
     */
    private function trovaCausaleAMM(Tenant $tenant): ?CausaleContabile
    {
        return CausaleContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'AMM')
            ->first();
    }
}
