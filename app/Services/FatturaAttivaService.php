<?php

namespace App\Services;

use App\Models\CausaleContabile;
use App\Models\FatturaAttiva;
use App\Models\RigaFatturaAttiva;
use App\Models\Scadenza;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per il ciclo attivo di vendita.
 *
 * Gestisce creazione, aggiornamento, emissione, pagamento e storno
 * delle fatture attive. Genera automaticamente:
 *  - Movimento contabile di emissione (se conto_crediti_id fornito)
 *  - Scadenza nel libro clienti (se data_scadenza presente)
 *  - Movimento di incasso (registraPagamento)
 *  - Nota di credito TD04 (storna)
 */
class FatturaAttivaService
{
    public function __construct(
        private readonly MovimentoContabileService $movService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Creazione
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crea una fattura attiva con relative righe.
     *
     * Se $data['stato'] === 'emessa' e $data['conto_crediti_id'] è valorizzato,
     * genera automaticamente il movimento contabile di emissione e la scadenza.
     *
     * @param  array  $data  Attributi testata + conto_crediti_id?, conto_ricavi_id?, conto_iva_debito_id?
     * @param  array  $righe Array [codice_iva_id, descrizione, quantita, prezzo_unitario, sconto_percentuale?, conto_id?]
     * @throws InvalidArgumentException
     */
    public function crea(Tenant $tenant, array $data, array $righe): FatturaAttiva
    {
        if (empty($righe)) {
            throw new InvalidArgumentException('La fattura deve contenere almeno una riga.');
        }

        return DB::transaction(function () use ($tenant, $data, $righe) {
            // Estrai campi non-fillable della testata
            $contoCreditiId  = $data['conto_crediti_id']   ?? null;
            $contoRicaviId   = $data['conto_ricavi_id']    ?? null;
            $contoIvaDebitoId = $data['conto_iva_debito_id'] ?? null;

            $testata = array_diff_key($data, array_flip([
                'conto_crediti_id', 'conto_ricavi_id', 'conto_iva_debito_id',
            ]));

            // Calcola numero progressivo se non esplicitato
            if (empty($testata['progressivo'])) {
                $testata['progressivo'] = $this->prossimoProgressivo(
                    $tenant,
                    (int) ($testata['anno'] ?? now()->year),
                    $testata['sezionale'] ?? '',
                );
            }
            if (empty($testata['numero_fattura'])) {
                $testata['numero_fattura'] = $this->formatNumero(
                    $testata['anno'] ?? now()->year,
                    $testata['progressivo'],
                    $testata['sezionale'] ?? '',
                );
            }

            $fattura = FatturaAttiva::create(array_merge($testata, [
                'tenant_id'         => $tenant->id,
                'imponibile_totale' => 0,
                'iva_totale'        => 0,
                'totale_documento'  => 0,
            ]));

            $this->salvaRighe($fattura, $righe);
            $this->ricalcolaTotali($fattura);

            // Movimento contabile e scadenza solo per fatture emesse
            if ($fattura->stato === FatturaAttiva::STATO_EMESSA && $contoCreditiId) {
                $this->generaMovimentoEmissione($fattura, $tenant, $contoCreditiId, $contoRicaviId, $contoIvaDebitoId);
                $this->creaScadenza($fattura, $tenant);
            }

            return $fattura->fresh(['righe.codiceIva']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggiornamento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggiorna testata e righe di una fattura modificabile (non read-only).
     *
     * @throws InvalidArgumentException se fattura è read-only o ha pagamenti
     */
    public function aggiorna(FatturaAttiva $fattura, array $data, array $righe): FatturaAttiva
    {
        if ($fattura->isReadOnly()) {
            throw new InvalidArgumentException(
                'Impossibile modificare: la fattura è agganciata a una liquidazione IVA definitiva.'
            );
        }

        if ($fattura->stato_pagamento !== FatturaAttiva::STATO_PAG_DA_INCASSARE) {
            throw new InvalidArgumentException(
                'Impossibile modificare: la fattura ha già pagamenti registrati.'
            );
        }

        if (empty($righe)) {
            throw new InvalidArgumentException('La fattura deve contenere almeno una riga.');
        }

        return DB::transaction(function () use ($fattura, $data, $righe) {
            $testata = array_diff_key($data, array_flip([
                'conto_crediti_id', 'conto_ricavi_id', 'conto_iva_debito_id',
            ]));

            $fattura->update($testata);
            $fattura->righe()->delete();
            $this->salvaRighe($fattura, $righe);
            $this->ricalcolaTotali($fattura);

            return $fattura->fresh(['righe.codiceIva']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Pagamento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra un pagamento (totale o parziale) sulla fattura.
     *
     * @param  array  $pagamento  [data_pagamento, importo, conto_incasso_id, conto_crediti_id?]
     * @throws InvalidArgumentException
     */
    public function registraPagamento(FatturaAttiva $fattura, array $pagamento): void
    {
        if ($fattura->stato === FatturaAttiva::STATO_ANNULLATA) {
            throw new InvalidArgumentException('Impossibile registrare pagamento su fattura annullata.');
        }

        if (! in_array($fattura->stato, [
            FatturaAttiva::STATO_EMESSA,
            FatturaAttiva::STATO_INVIATA_SDI,
            FatturaAttiva::STATO_ACCETTATA,
        ], true)) {
            throw new InvalidArgumentException(
                "La fattura deve essere emessa per registrare un pagamento (stato attuale: {$fattura->stato})."
            );
        }

        $importo = (float) ($pagamento['importo'] ?? $fattura->totale_documento);
        $totale  = (float) $fattura->totale_documento;

        if ($importo <= 0) {
            throw new InvalidArgumentException('L\'importo del pagamento deve essere positivo.');
        }
        if ($importo > $totale) {
            throw new InvalidArgumentException("L'importo {$importo} supera il totale documento {$totale}.");
        }

        DB::transaction(function () use ($fattura, $pagamento, $importo, $totale) {
            $nuovoStato = $importo >= $totale
                ? FatturaAttiva::STATO_PAG_INCASSATA
                : FatturaAttiva::STATO_PAG_PARZIALMENTE_INCASSATA;

            $fattura->update(['stato_pagamento' => $nuovoStato]);

            // Movimento contabile incasso (opzionale)
            if (! empty($pagamento['conto_incasso_id'])) {
                $this->generaMovimentoIncasso(
                    $fattura,
                    Tenant::find($fattura->tenant_id),
                    (int) $pagamento['conto_incasso_id'],
                    $pagamento['conto_crediti_id'] ?? null,
                    $importo,
                    $pagamento['data_pagamento'] ?? now()->toDateString(),
                );
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Storno (nota di credito TD04)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera una nota di credito TD04 e annulla la fattura originale.
     *
     * @throws InvalidArgumentException se la fattura non è emessa
     */
    public function storna(FatturaAttiva $fattura): FatturaAttiva
    {
        if (! in_array($fattura->stato, [
            FatturaAttiva::STATO_EMESSA,
            FatturaAttiva::STATO_INVIATA_SDI,
            FatturaAttiva::STATO_ACCETTATA,
        ], true)) {
            throw new InvalidArgumentException(
                "La fattura deve essere emessa per poter essere stornata (stato: {$fattura->stato})."
            );
        }

        if ($fattura->stato_pagamento !== FatturaAttiva::STATO_PAG_DA_INCASSARE) {
            throw new InvalidArgumentException(
                'Impossibile stornare: la fattura ha già pagamenti registrati.'
            );
        }

        return DB::transaction(function () use ($fattura) {
            $tenant = Tenant::find($fattura->tenant_id);

            $anno      = (int) now()->year;
            $prog      = $this->prossimoProgressivo($tenant, $anno, 'NC');
            $numero    = $this->formatNumero($anno, $prog, 'NC');

            // Crea nota di credito
            $notaCredito = FatturaAttiva::create([
                'tenant_id'         => $fattura->tenant_id,
                'cliente_id'        => $fattura->cliente_id,
                'sezionale'         => 'NC',
                'anno'              => $anno,
                'progressivo'       => $prog,
                'numero_fattura'    => $numero,
                'data_fattura'      => now()->toDateString(),
                'data_scadenza'     => null,
                'imponibile_totale' => $fattura->imponibile_totale,
                'iva_totale'        => $fattura->iva_totale,
                'totale_documento'  => $fattura->totale_documento,
                'esigibilita'       => $fattura->esigibilita,
                'tipo_documento'    => 'TD04',
                'stato'             => FatturaAttiva::STATO_EMESSA,
                'stato_pagamento'   => FatturaAttiva::STATO_PAG_DA_INCASSARE,
                'note'              => "Nota di credito per storno fattura {$fattura->numero_fattura}",
            ]);

            // Copia righe
            $fattura->load('righe');
            foreach ($fattura->righe as $riga) {
                RigaFatturaAttiva::create([
                    'tenant_id'          => $riga->tenant_id,
                    'fattura_attiva_id'  => $notaCredito->id,
                    'codice_iva_id'      => $riga->codice_iva_id,
                    'conto_id'           => $riga->conto_id,
                    'descrizione'        => $riga->descrizione,
                    'quantita'           => $riga->quantita,
                    'prezzo_unitario'    => $riga->prezzo_unitario,
                    'sconto_percentuale' => $riga->sconto_percentuale,
                    'imponibile'         => $riga->imponibile,
                    'iva'                => $riga->iva,
                    'totale'             => $riga->totale,
                ]);
            }

            // Annulla fattura originale
            $fattura->update([
                'stato'          => FatturaAttiva::STATO_ANNULLATA,
                'stato_pagamento' => FatturaAttiva::STATO_PAG_DA_INCASSARE,
            ]);

            return $notaCredito->fresh(['righe.codiceIva']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Numerazione
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcola il prossimo numero progressivo per anno + sezionale.
     */
    public function calcolaNumeroProgressivo(Tenant $tenant, int $anno, string $sezionale = ''): string
    {
        $prog = $this->prossimoProgressivo($tenant, $anno, $sezionale);
        return $this->formatNumero($anno, $prog, $sezionale);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function prossimoProgressivo(Tenant $tenant, int $anno, string $sezionale = ''): int
    {
        $max = FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno', $anno)
            ->where('sezionale', $sezionale)
            ->max('progressivo') ?? 0;

        return $max + 1;
    }

    private function formatNumero(int $anno, int $progressivo, string $sezionale = ''): string
    {
        $prefix = $sezionale ? strtoupper($sezionale) . '-' : 'FT-';
        return "{$prefix}{$anno}-" . str_pad((string) $progressivo, 4, '0', STR_PAD_LEFT);
    }

    private function salvaRighe(FatturaAttiva $fattura, array $righe): void
    {
        foreach ($righe as $r) {
            $riga = RigaFatturaAttiva::make([
                'tenant_id'          => $fattura->tenant_id,
                'fattura_attiva_id'  => $fattura->id,
                'codice_iva_id'      => $r['codice_iva_id'],
                'conto_id'           => $r['conto_id'] ?? null,
                'descrizione'        => $r['descrizione'],
                'quantita'           => $r['quantita']           ?? 1,
                'prezzo_unitario'    => $r['prezzo_unitario']    ?? 0,
                'sconto_percentuale' => $r['sconto_percentuale'] ?? 0,
                'imponibile'         => 0,
                'iva'                => 0,
                'totale'             => 0,
            ]);
            $riga->calcolaTotali();
            $riga->save();
        }
    }

    private function ricalcolaTotali(FatturaAttiva $fattura): void
    {
        $fattura->imponibile_totale = $fattura->righe()->sum('imponibile');
        $fattura->iva_totale        = $fattura->righe()->sum('iva');
        $fattura->totale_documento  = $fattura->righe()->sum('totale');
        $fattura->save();
    }

    /**
     * Genera scrittura contabile emissione fattura:
     *   DARE  conto_crediti     → totale_documento
     *   AVERE conto_ricavi      → imponibile_totale  (se fornito)
     *   AVERE conto_iva_debito  → iva_totale         (se > 0 e conto fornito)
     */
    private function generaMovimentoEmissione(
        FatturaAttiva $fattura,
        Tenant $tenant,
        int $contoCreditiId,
        ?int $contoRicaviId,
        ?int $contoIvaDebitoId,
    ): void {
        $causale = $this->trovaCausale(CausaleContabile::TIPO_FATTURA_VENDITA);

        $righe = [
            [
                'conto_contabile_id' => $contoCreditiId,
                'importo_dare'       => (float) $fattura->totale_documento,
                'importo_avere'      => 0,
                'descrizione'        => "Ft. {$fattura->numero_fattura}",
            ],
        ];

        $imponibile = (float) $fattura->imponibile_totale;
        $iva        = (float) $fattura->iva_totale;

        if ($contoRicaviId && $imponibile > 0) {
            $righe[] = [
                'conto_contabile_id' => $contoRicaviId,
                'importo_dare'       => 0,
                'importo_avere'      => $imponibile,
                'descrizione'        => "Ricavi ft. {$fattura->numero_fattura}",
            ];
        }

        if ($contoIvaDebitoId && $iva > 0) {
            $righe[] = [
                'conto_contabile_id' => $contoIvaDebitoId,
                'importo_dare'       => 0,
                'importo_avere'      => $iva,
                'descrizione'        => "IVA ft. {$fattura->numero_fattura}",
            ];
        }

        // Se non ci sono AVERE separati, crea AVERE unico = totale
        if (count($righe) === 1 && (! $contoRicaviId)) {
            return; // Nessun conto AVERE: skip movimento incompleto
        }

        $mov = $this->movService->crea(
            tenant: $tenant,
            testata: [
                'anno_esercizio'     => (int) $fattura->anno,
                'data_registrazione' => $fattura->data_fattura->toDateString(),
                'causale_id'         => $causale->id,
                'descrizione'        => "Emissione ft. {$fattura->numero_fattura}",
                'stato'              => 'bozza',
                'numero_documento'   => $fattura->numero_fattura,
                'data_documento'     => $fattura->data_fattura->toDateString(),
            ],
            righe: $righe,
        );

        $this->movService->conferma($mov);
    }

    /**
     * Genera scrittura contabile incasso:
     *   DARE  conto_incasso   → importo
     *   AVERE conto_crediti   → importo (se contoCreditiId fornito)
     */
    private function generaMovimentoIncasso(
        FatturaAttiva $fattura,
        Tenant $tenant,
        int $contoIncassoId,
        ?int $contoCreditiId,
        float $importo,
        string $dataPagamento,
    ): void {
        if (! $contoCreditiId) {
            return; // Senza conto crediti non creiamo movimento sbilanciato
        }

        $causale = $this->trovaCausale(CausaleContabile::TIPO_INCASSO);

        $mov = $this->movService->crea(
            tenant: $tenant,
            testata: [
                'anno_esercizio'     => (int) Carbon::parse($dataPagamento)->year,
                'data_registrazione' => $dataPagamento,
                'causale_id'         => $causale->id,
                'descrizione'        => "Incasso ft. {$fattura->numero_fattura}",
                'stato'              => 'bozza',
                'numero_documento'   => $fattura->numero_fattura,
            ],
            righe: [
                [
                    'conto_contabile_id' => $contoIncassoId,
                    'importo_dare'       => $importo,
                    'importo_avere'      => 0,
                    'descrizione'        => "Incasso ft. {$fattura->numero_fattura}",
                ],
                [
                    'conto_contabile_id' => $contoCreditiId,
                    'importo_dare'       => 0,
                    'importo_avere'      => $importo,
                    'descrizione'        => "Storno crediti ft. {$fattura->numero_fattura}",
                ],
            ],
        );

        $this->movService->conferma($mov);
    }

    private function creaScadenza(FatturaAttiva $fattura, Tenant $tenant): void
    {
        if (! $fattura->data_scadenza) {
            return;
        }

        Scadenza::create([
            'tenant_id'    => $tenant->id,
            'tipo'         => 'altra',
            'descrizione'  => "Incasso ft. {$fattura->numero_fattura}",
            'importo'      => $fattura->totale_documento,
            'data_scadenza' => $fattura->data_scadenza->toDateString(),
            'stato'        => Scadenza::STATO_APERTA,
            'riferimento'  => $fattura->numero_fattura,
        ]);
    }

    private function trovaCausale(string $tipo): CausaleContabile
    {
        return CausaleContabile::firstOrCreate(
            ['tipo' => $tipo, 'di_sistema' => true],
            [
                'codice'      => strtoupper(substr($tipo, 0, 3)),
                'descrizione' => ucfirst(str_replace('_', ' ', $tipo)),
                'attivo'      => true,
            ]
        );
    }
}
