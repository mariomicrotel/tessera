<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSchedule;
use App\Models\FatturaPassiva;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service per il CRUD avanzato dei cespiti e la gestione del loro ciclo di vita.
 *
 * Responsabilità:
 * - Creazione/aggiornamento cespiti con applicazione automatica dei default di categoria
 * - Preview del piano di ammortamento (senza salvare)
 * - Calcolo del valore netto contabile e del fondo cumulato in un dato momento
 * - Helper per agganciare un cespite a una riga di fattura passiva
 */
class CespitiService
{
    public function __construct(
        private readonly AmortizzamentoService $amortizzamentoService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea un nuovo cespite applicando i default della categoria selezionata.
     *
     * Se la categoria ha conti di default e il cespite non li sovrascrive,
     * vengono ereditati automaticamente.
     *
     * @param  Tenant $tenant   Tenant corrente
     * @param  array  $dati     Attributi del cespite (validati dal controller)
     * @return Asset            Cespite creato
     */
    public function crea(Tenant $tenant, array $dati): Asset
    {
        $dati = $this->applicaDefaultCategoria($dati);
        $dati = $this->normalizzaCostoStorico($dati);

        $asset = Asset::create(array_merge($dati, [
            'tenant_id' => $tenant->id,
            'stato'     => Asset::STATO_IN_USO,
        ]));

        return $asset->fresh(['category', 'supplier', 'fatturaPassiva']);
    }

    /**
     * Aggiorna un cespite esistente.
     * Non permette la modifica di campi fiscali "congelati" se esistono schedule definitive.
     *
     * @throws \RuntimeException se si tenta di modificare campi locked
     */
    public function aggiorna(Asset $asset, array $dati): Asset
    {
        if ($asset->depreciationSchedules()->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)->exists()) {
            // Campi non modificabili dopo la prima registrazione definitiva
            $campiLocked = ['costo_storico', 'data_inizio_ammortamento', 'asset_category_id'];

            foreach ($campiLocked as $campo) {
                if (isset($dati[$campo]) && $dati[$campo] != $asset->{$campo}) {
                    throw new \RuntimeException(
                        "Il campo '{$campo}' non può essere modificato dopo la registrazione di ammortamenti definitivi."
                    );
                }
            }
        }

        $dati = $this->applicaDefaultCategoria($dati, $asset);
        $asset->update($dati);

        return $asset->fresh(['category', 'supplier', 'fatturaPassiva']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Preview piano ammortamento
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcola il piano di ammortamento completo senza salvare (per preview UI).
     *
     * Restituisce un array di righe con:
     *   esercizio, aliquota, quota_calcolata, fondo_fine, valore_residuo
     *
     * @param  Asset    $asset   Cespite (deve avere costo_storico e aliquotaEffettiva)
     * @param  int|null $dal     Anno di inizio (default: anno data_inizio_ammortamento)
     * @return array<int, array{esercizio:int, aliquota:float, quota:float, fondo:float, residuo:float}>
     */
    public function previewPianoAmmortamento(Asset $asset, ?int $dal = null): array
    {
        $costoStorico = (float) $asset->costo_storico;
        if ($costoStorico <= 0) {
            return [];
        }

        $annoInizio = $dal ?? (int) ($asset->data_inizio_ammortamento ?? $asset->purchase_date)?->format('Y');
        if (! $annoInizio) {
            return [];
        }

        $aliquotaBase = $asset->aliquotaEffettiva();
        if ($aliquotaBase <= 0) {
            return [];
        }

        $piano       = [];
        $fondoCumulato = 0.0;
        $anno        = $annoInizio;
        $maxAnni     = 100; // guardia contro loop infinito

        for ($i = 0; $i < $maxAnni; $i++) {
            $aliquota = $this->amortizzamentoService->aliquotaAnno(
                $asset, $anno, $annoInizio, $fondoCumulato, $costoStorico
            );

            if ($aliquota <= 0) {
                break;
            }

            $quota = min(
                round($costoStorico * $aliquota / 100, 2),
                $costoStorico - $fondoCumulato
            );

            if ($quota <= 0) {
                break;
            }

            $fondoCumulato = round($fondoCumulato + $quota, 2);
            $residuo       = max(0, round($costoStorico - $fondoCumulato, 2));

            $piano[] = [
                'esercizio' => $anno,
                'aliquota'  => $aliquota,
                'quota'     => $quota,
                'fondo'     => $fondoCumulato,
                'residuo'   => $residuo,
            ];

            if ($residuo <= 0) {
                break;
            }

            $anno++;
        }

        return $piano;
    }

    /**
     * Calcola il valore netto contabile in una data specifica (o oggi).
     */
    public function calcolaValoreResiduo(Asset $asset, ?Carbon $data = null): float
    {
        $data ??= Carbon::now();

        // Somma le quote definitive fino alla data richiesta
        $fondoCumulato = (float) $asset->depreciationSchedules()
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->where('esercizio', '<=', (int) $data->format('Y'))
            ->sum('quota_registrata');

        return max(0, (float) $asset->costo_storico - $fondoCumulato);
    }

    /**
     * Calcola il fondo ammortamento cumulato in una data specifica.
     */
    public function calcolaFondoCumulato(Asset $asset, ?Carbon $data = null): float
    {
        $data ??= Carbon::now();

        return (float) $asset->depreciationSchedules()
            ->where('stato', AssetDepreciationSchedule::STATO_DEFINITIVO)
            ->where('esercizio', '<=', (int) $data->format('Y'))
            ->sum('quota_registrata');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integrazione FatturaPassiva
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Crea un cespite a partire da una riga di fattura passiva.
     * Precompila: fornitore, fattura, costo storico, data acquisto.
     *
     * @param  FatturaPassiva $fattura   Fattura di acquisto
     * @param  array          $datiExtra Campi aggiuntivi (category, name, ecc.)
     * @return Asset
     */
    public function creaRaFattura(Tenant $tenant, FatturaPassiva $fattura, array $datiExtra = []): Asset
    {
        $dati = array_merge([
            'supplier_id'               => $fattura->supplier_id,
            'fattura_passiva_id'        => $fattura->id,
            'costo_storico'             => (float) $fattura->imponibile_totale,
            'value'                     => (float) $fattura->imponibile_totale,
            'purchase_date'             => $fattura->data_fattura,
            'data_inizio_ammortamento'  => $fattura->data_fattura,
            'primo_anno_ridotto'        => true,
            'metodo_ammortamento'       => Asset::METODO_ORDINARIO,
            'percentuale_deducibilita'  => 100,
        ], $datiExtra);

        return $this->crea($tenant, $dati);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Applica i default della categoria al nuovo cespite (se non già specificati).
     */
    private function applicaDefaultCategoria(array $dati, ?Asset $asset = null): array
    {
        $categoryId = $dati['asset_category_id'] ?? $asset?->asset_category_id;
        if (! $categoryId) {
            return $dati;
        }

        $categoria = AssetCategory::find($categoryId);
        if (! $categoria) {
            return $dati;
        }

        // Applica solo se il campo non è già valorizzato
        $dati['percentuale_deducibilita'] ??= $categoria->percentuale_deducibilita_default;
        $dati['primo_anno_ridotto']       ??= $categoria->primo_anno_ridotto_default;
        $dati['metodo_ammortamento']      ??= Asset::METODO_ORDINARIO;

        // Conti: usa i default di categoria solo se non già impostati
        if (empty($dati['conto_bene_id']) && $categoria->conto_bene_default_id) {
            $dati['conto_bene_id'] = $categoria->conto_bene_default_id;
        }
        if (empty($dati['conto_fondo_id']) && $categoria->conto_fondo_default_id) {
            $dati['conto_fondo_id'] = $categoria->conto_fondo_default_id;
        }

        return $dati;
    }

    /**
     * Se costo_storico non è fornito ma `value` sì, li sincronizza.
     */
    private function normalizzaCostoStorico(array $dati): array
    {
        if (! isset($dati['costo_storico']) && isset($dati['value'])) {
            $dati['costo_storico'] = $dati['value'];
        } elseif (! isset($dati['value']) && isset($dati['costo_storico'])) {
            $dati['value'] = $dati['costo_storico'];
        }

        return $dati;
    }
}
