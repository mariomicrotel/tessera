<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\AssetDisposal;
use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service per la dismissione e vendita di cespiti.
 *
 * La dismissione storna il cespite dal patrimonio rilevando
 * l'eventuale plus/minusvalenza da cessione.
 *
 * Scrittura contabile generata:
 *
 *   Caso VENDITA con plusvalenza (realizzo > VNC):
 *     DARE:  Crediti v/clienti (realizzo)
 *     DARE:  Fondo ammortamento (storno fondo cumulato)
 *     AVERE: Cespite (costo storico)
 *     AVERE: Plusvalenze da alienazione (differenza)
 *
 *   Caso VENDITA con minusvalenza (realizzo < VNC):
 *     DARE:  Crediti v/clienti (realizzo)
 *     DARE:  Fondo ammortamento (storno fondo cumulato)
 *     DARE:  Minusvalenze da alienazione (differenza)
 *     AVERE: Cespite (costo storico)
 *
 *   Caso ROTTAMAZIONE / DONAZIONE / FURTO (realizzo = 0):
 *     DARE:  Fondo ammortamento (storno)
 *     DARE:  Minusvalenze (= VNC residuo)
 *     AVERE: Cespite (costo storico)
 */
class DismissioneCespitiService
{
    public function __construct(
        private readonly MovimentoContabileService $movimentoService,
        private readonly CespitiService            $cespitiService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // API principale
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Esegue la dismissione del cespite:
     *   1. Calcola VNC e plus/minusvalenza
     *   2. Crea AssetDisposal
     *   3. Genera e conferma il movimento contabile
     *   4. Aggiorna lo stato del cespite
     *
     * @param  Asset   $asset  Cespite da dismettere
     * @param  Tenant  $tenant Tenant corrente
     * @param  array   $dati   {
     *     tipo: string,                // vendita|rottamazione|donazione|furto
     *     data_dismissione: string,    // Y-m-d
     *     valore_realizzo: float,      // 0 per rottamazione/furto
     *     note: ?string
     * }
     * @return AssetDisposal
     *
     * @throws RuntimeException
     */
    public function dismetti(Asset $asset, Tenant $tenant, array $dati): AssetDisposal
    {
        if ($asset->stato === Asset::STATO_DISMESSO || $asset->stato === Asset::STATO_VENDUTO) {
            throw new RuntimeException("Il cespite '{$asset->name}' è già dismesso o venduto.");
        }

        if ($asset->disposal()->exists()) {
            throw new RuntimeException("Esiste già una dismissione registrata per '{$asset->name}'.");
        }

        $dataDismissione = $dati['data_dismissione'];
        $valorRealizzo   = (float) ($dati['valore_realizzo'] ?? 0);
        $tipo            = $dati['tipo'];

        // Calcola VNC al giorno della dismissione
        $vnc           = $this->cespitiService->calcolaValoreResiduo($asset, \Carbon\Carbon::parse($dataDismissione));
        $fondoCumulato = $this->cespitiService->calcolaFondoCumulato($asset, \Carbon\Carbon::parse($dataDismissione));
        $plusMinus     = round($valorRealizzo - $vnc, 2);

        return DB::transaction(function () use ($asset, $tenant, $dati, $tipo, $dataDismissione, $valorRealizzo, $vnc, $fondoCumulato, $plusMinus) {

            // Crea la dismissione
            $disposal = AssetDisposal::create([
                'tenant_id'                => $tenant->id,
                'asset_id'                 => $asset->id,
                'tipo'                     => $tipo,
                'data_dismissione'         => $dataDismissione,
                'valore_realizzo'          => $valorRealizzo,
                'valore_netto_contabile'   => $vnc,
                'plusvalenza_minusvalenza' => $plusMinus,
                'note'                     => $dati['note'] ?? null,
            ]);

            // Genera il movimento contabile
            try {
                $movimento = $this->registraScrittura($disposal, $asset, $tenant, $fondoCumulato, $plusMinus, $dataDismissione);
                $disposal->update(['movimento_contabile_id' => $movimento->id]);
            } catch (\Throwable $e) {
                // La scrittura contabile è opzionale se i conti non sono configurati:
                // la dismissione viene comunque registrata, ma senza movimento.
                // Il controller può decidere come gestire il warning.
                report($e);
            }

            // Aggiorna stato cespite
            $nuovoStato = ($tipo === AssetDisposal::TIPO_VENDITA) ? Asset::STATO_VENDUTO : Asset::STATO_DISMESSO;
            $asset->update(['stato' => $nuovoStato]);

            return $disposal->fresh('asset');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scrittura contabile di dismissione
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera e conferma il movimento contabile per la dismissione.
     *
     * @throws RuntimeException se mancano i conti necessari
     */
    public function registraScrittura(
        AssetDisposal $disposal,
        Asset $asset,
        Tenant $tenant,
        float $fondoCumulato,
        float $plusMinus,
        string $dataReg
    ): \App\Models\MovimentoContabile {
        $costoStorico  = (float) $asset->costo_storico;
        $valorRealizzo = (float) $disposal->valore_realizzo;

        // Risolve i conti necessari
        $contoFondo      = $this->resolveContoFondo($asset, $tenant);
        $contoCespite    = $this->resolveContoBene($asset, $tenant);
        $contoCrediti    = $this->resolveContoCrediti($tenant);
        $contoPlusMinus  = $plusMinus >= 0
            ? $this->resolveContoPlusvalenze($tenant)
            : $this->resolveContoMinusvalenze($tenant);

        if (! $contoFondo || ! $contoCespite) {
            throw new RuntimeException(
                "Conti mancanti per la dismissione di '{$asset->name}': configurare conto bene e conto fondo."
            );
        }

        $causale = $this->trovaCausaleRET($tenant);
        if (! $causale) {
            throw new RuntimeException("Causale 'RET' (rettifica) non trovata per il tenant.");
        }

        $righe = [];
        $desc  = "Dismissione cespite: {$asset->name} ({$disposal->tipoLabel()})";

        // DARE: Fondo ammortamento (storno del fondo cumulato)
        if ($fondoCumulato > 0) {
            $righe[] = [
                'conto_contabile_id' => $contoFondo->id,
                'importo_dare'       => $fondoCumulato,
                'descrizione'        => 'Storno fondo ammortamento',
            ];
        }

        // DARE: Crediti v/clienti o Cassa (realizzo, se > 0)
        if ($valorRealizzo > 0 && $contoCrediti) {
            $righe[] = [
                'conto_contabile_id' => $contoCrediti->id,
                'importo_dare'       => $valorRealizzo,
                'descrizione'        => 'Corrispettivo cessione cespite',
            ];
        }

        // DARE: Minusvalenza (se realizzo < VNC)
        if ($plusMinus < 0 && $contoPlusMinus) {
            $righe[] = [
                'conto_contabile_id' => $contoPlusMinus->id,
                'importo_dare'       => abs($plusMinus),
                'descrizione'        => 'Minusvalenza da alienazione',
            ];
        }

        // AVERE: Cespite (costo storico)
        $righe[] = [
            'conto_contabile_id' => $contoCespite->id,
            'importo_avere'      => $costoStorico,
            'descrizione'        => 'Eliminazione cespite (costo storico)',
        ];

        // AVERE: Plusvalenza (se realizzo > VNC)
        if ($plusMinus > 0 && $contoPlusMinus) {
            $righe[] = [
                'conto_contabile_id' => $contoPlusMinus->id,
                'importo_avere'      => $plusMinus,
                'descrizione'        => 'Plusvalenza da alienazione',
            ];
        }

        $movimento = $this->movimentoService->crea($tenant, [
            'data_registrazione' => $dataReg,
            'causale_id'         => $causale->id,
            'descrizione'        => $desc,
            'numero_documento'   => "DIS-{$asset->code}-" . date('Y'),
        ], $righe);

        $this->movimentoService->conferma($movimento);

        return $movimento;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview (calcola senza salvare)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcola l'impatto economico della dismissione senza salvare nulla.
     *
     * @return array{vnc:float, fondo_cumulato:float, plusvalenza_minusvalenza:float}
     */
    public function preview(Asset $asset, float $valorRealizzo, string $dataRif): array
    {
        $data          = \Carbon\Carbon::parse($dataRif);
        $vnc           = $this->cespitiService->calcolaValoreResiduo($asset, $data);
        $fondoCumulato = $this->cespitiService->calcolaFondoCumulato($asset, $data);

        return [
            'vnc'                     => $vnc,
            'fondo_cumulato'          => $fondoCumulato,
            'plusvalenza_minusvalenza' => round($valorRealizzo - $vnc, 2),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals: risoluzione conti
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveContoFondo(Asset $asset, Tenant $tenant): ?ContoContabile
    {
        $id = $asset->conto_fondo_id ?? $asset->category?->conto_fondo_default_id;
        return $id ? ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->find($id) : null;
    }

    private function resolveContoBene(Asset $asset, Tenant $tenant): ?ContoContabile
    {
        $id = $asset->conto_bene_id ?? $asset->category?->conto_bene_default_id;
        return $id ? ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->find($id) : null;
    }

    /** Crediti v/clienti o conto transitorio. Fallback: cerca codice '3.05'. */
    private function resolveContoCrediti(Tenant $tenant): ?ContoContabile
    {
        return ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'like', '3.05%')
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->first();
    }

    /** Conto plusvalenze. Fallback: cerca '5.30'. */
    private function resolveContoPlusvalenze(Tenant $tenant): ?ContoContabile
    {
        return ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'like', '5.30%')
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->first();
    }

    /** Conto minusvalenze. Fallback: cerca '7.60'. */
    private function resolveContoMinusvalenze(Tenant $tenant): ?ContoContabile
    {
        return ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'like', '7.60%')
            ->where('movimentabile', true)
            ->orderBy('codice')
            ->first();
    }

    private function trovaCausaleRET(Tenant $tenant): ?CausaleContabile
    {
        return CausaleContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('codice', 'RET')
            ->first();
    }
}
