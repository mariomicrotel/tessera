<?php

namespace App\Services;

use App\Models\CausaleContabile;
use App\Models\CompensaTerzi;
use App\Models\Tenant;
use App\Models\VersamentoRitenuta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic per i compensi a terzi e le ritenute d'acconto.
 *
 * Flusso principale:
 *  1. crea()         — registra il compenso e genera la scrittura contabile
 *  2. versaRitenute() — aggrega le ritenute del mese e crea il VersamentoRitenuta
 *  3. riepilogoAnnuale() — dati per la Certificazione Unica (CU) per percipiente
 */
class CompensaTerziService
{
    public function __construct(
        private readonly MovimentoContabileService $movService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Creazione compenso
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra un compenso a terzo calcolando automaticamente la ritenuta.
     *
     * @param  array  $data  Campi del compenso + conto_costo_id?, conto_ritenute_id?
     * @throws InvalidArgumentException
     */
    public function crea(Tenant $tenant, array $data): CompensaTerzi
    {
        $this->validaDati($data);

        return DB::transaction(function () use ($tenant, $data) {
            // Calcola importi se non forniti
            $lordo      = (float) $data['compenso_lordo'];
            $base       = (float) ($data['base_imponibile_ritenuta'] ?? $lordo);
            $aliquota   = (float) ($data['aliquota_ritenuta'] ?? CompensaTerzi::ALIQUOTA_DEFAULT);
            $ritenuta   = round($base * $aliquota / 100, 2);
            $netto      = round($lordo - $ritenuta, 2);

            $compenso = CompensaTerzi::create(array_merge($data, [
                'tenant_id'                => $tenant->id,
                'base_imponibile_ritenuta' => $base,
                'aliquota_ritenuta'        => $aliquota,
                'ritenuta'                 => $ritenuta,
                'compenso_netto'           => $netto,
                'stato_ritenuta'           => CompensaTerzi::STATO_DA_VERSARE,
                'rimborsi_spese'           => $data['rimborsi_spese'] ?? 0,
                'contributo_inps_beneficiario' => $data['contributo_inps_beneficiario'] ?? 0,
                'contributo_inps_committente'  => $data['contributo_inps_committente'] ?? 0,
            ]));

            // Genera movimento contabile se conti forniti
            $contoCostoId    = $data['conto_costo_id']    ?? null;
            $contoRitenute   = $data['conto_ritenute_id'] ?? null;

            if ($contoCostoId) {
                $this->generaMovimentoCompensо($compenso, $tenant, $contoCostoId, $contoRitenute);
            }

            return $compenso->fresh(['member', 'contoCosto', 'contoRitenute']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggiornamento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggiorna un compenso non ancora versato.
     *
     * @throws InvalidArgumentException
     */
    public function aggiorna(CompensaTerzi $compenso, array $data): CompensaTerzi
    {
        if ($compenso->isVersata()) {
            throw new InvalidArgumentException(
                'Impossibile modificare: la ritenuta è già stata versata.'
            );
        }

        $this->validaDati(array_merge($compenso->toArray(), $data));

        return DB::transaction(function () use ($compenso, $data) {
            $lordo    = (float) ($data['compenso_lordo']              ?? $compenso->compenso_lordo);
            $base     = (float) ($data['base_imponibile_ritenuta']    ?? $lordo);
            $aliquota = (float) ($data['aliquota_ritenuta']           ?? $compenso->aliquota_ritenuta);
            $ritenuta = round($base * $aliquota / 100, 2);
            $netto    = round($lordo - $ritenuta, 2);

            $compenso->update(array_merge($data, [
                'base_imponibile_ritenuta' => $base,
                'aliquota_ritenuta'        => $aliquota,
                'ritenuta'                 => $ritenuta,
                'compenso_netto'           => $netto,
            ]));

            return $compenso->fresh(['member', 'contoCosto', 'contoRitenute']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Versamento ritenute del mese
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggrega le ritenute del mese/anno e crea il VersamentoRitenuta.
     *
     * Tutte le ritenute da_versare con data_pagamento nel mese indicato
     * vengono marcate come 'versata' e collegate al versamento creato.
     *
     * @param  array  $data  [mese, anno, data_versamento, codice_tributo?, note?]
     * @throws InvalidArgumentException
     */
    public function versaRitenute(Tenant $tenant, array $data): VersamentoRitenuta
    {
        $mese = (int) $data['mese'];
        $anno = (int) $data['anno'];

        if ($mese < 1 || $mese > 12) {
            throw new InvalidArgumentException('Mese non valido (1-12).');
        }

        return DB::transaction(function () use ($tenant, $data, $mese, $anno) {
            // Trova ritenute da versare nel mese
            $ritenute = CompensaTerzi::where('tenant_id', $tenant->id)
                ->where('stato_ritenuta', CompensaTerzi::STATO_DA_VERSARE)
                ->whereYear('data_pagamento', $anno)
                ->whereMonth('data_pagamento', $mese)
                ->get();

            if ($ritenute->isEmpty()) {
                throw new InvalidArgumentException(
                    "Nessuna ritenuta da versare per {$mese}/{$anno}."
                );
            }

            $totale = $ritenute->sum('ritenuta');

            $versamento = VersamentoRitenuta::create([
                'tenant_id'        => $tenant->id,
                'mese_riferimento' => $mese,
                'anno_riferimento' => $anno,
                'data_versamento'  => $data['data_versamento'],
                'codice_tributo'   => $data['codice_tributo'] ?? VersamentoRitenuta::CODICE_TRIBUTO_LAV_AUTONOMO,
                'importo_totale'   => $totale,
                'codice_ufficio'   => $data['codice_ufficio'] ?? null,
                'codice_atto'      => $data['codice_atto']    ?? null,
                'note'             => $data['note']           ?? null,
            ]);

            // Collega e segna tutte le ritenute come versate
            CompensaTerzi::whereIn('id', $ritenute->pluck('id'))
                ->update([
                    'stato_ritenuta'       => CompensaTerzi::STATO_VERSATA,
                    'versamento_ritenuta_id' => $versamento->id,
                ]);

            return $versamento->fresh(['compensi']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Riepilogo annuale per Certificazione Unica (CU)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il riepilogo aggregato per percipiente/anno per la CU.
     *
     * @return Collection  di oggetti con CF, nome, totali per tipo causale
     */
    public function riepilogoAnnuale(Tenant $tenant, int $anno): Collection
    {
        return CompensaTerzi::where('tenant_id', $tenant->id)
            ->where('anno_competenza', $anno)
            ->orderBy('codice_fiscale')
            ->get()
            ->groupBy('codice_fiscale')
            ->map(function (Collection $compensi, string $cf) {
                $primo = $compensi->first();

                return (object) [
                    'codice_fiscale'               => $cf,
                    'nome_percipiente'             => $primo->nome_percipiente,
                    'partita_iva'                  => $primo->partita_iva,
                    'indirizzo'                    => $primo->indirizzo,
                    'compensi'                     => $compensi,
                    'totale_compenso_lordo'        => round($compensi->sum('compenso_lordo'), 2),
                    'totale_base_imponibile'       => round($compensi->sum('base_imponibile_ritenuta'), 2),
                    'totale_ritenuta'              => round($compensi->sum('ritenuta'), 2),
                    'totale_netto'                 => round($compensi->sum('compenso_netto'), 2),
                    'totale_rimborsi'              => round($compensi->sum('rimborsi_spese'), 2),
                    'totale_inps_beneficiario'     => round($compensi->sum('contributo_inps_beneficiario'), 2),
                    'totale_inps_committente'      => round($compensi->sum('contributo_inps_committente'), 2),
                    'causali'                      => $compensi->pluck('codice_causale')->unique()->values()->all(),
                ];
            })
            ->values();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Scrittura contabile del compenso:
     *   DARE  conto_costo      → compenso_lordo
     *   AVERE conto_ritenute   → ritenuta        (se conto fornito)
     *   AVERE banca/cassa      → compenso_netto  (non creiamo qui — utente registra pagamento separatamente)
     *
     * In pratica generiamo solo la parte accantonamento ritenuta:
     *   DARE  conto_costo    → lordo
     *   AVERE conto_ritenute → ritenuta
     *   AVERE debiti         → netto  (semplificato: solo se conto ritenute presente)
     */
    private function generaMovimentoCompensо(
        CompensaTerzi $compenso,
        Tenant $tenant,
        int $contoCostoId,
        ?int $contoRitenute,
    ): void {
        $causale = CausaleContabile::firstOrCreate(
            ['codice' => 'RIT', 'di_sistema' => true],
            [
                'descrizione' => 'Ritenuta d\'acconto',
                'tipo'        => CausaleContabile::TIPO_GENERICO,
                'attivo'      => true,
            ]
        );

        $righe = [
            [
                'conto_contabile_id' => $contoCostoId,
                'importo_dare'       => (float) $compenso->compenso_lordo,
                'importo_avere'      => 0,
                'descrizione'        => "Compenso {$compenso->nome_percipiente}",
            ],
        ];

        if ($contoRitenute) {
            $righe[] = [
                'conto_contabile_id' => $contoRitenute,
                'importo_dare'       => 0,
                'importo_avere'      => (float) $compenso->ritenuta,
                'descrizione'        => "Ritenuta 20% {$compenso->nome_percipiente}",
            ];
        }

        // Serve almeno un AVERE: se non c'è conto ritenute, saltiamo il movimento
        if (count($righe) < 2) {
            return;
        }

        $mov = $this->movService->crea(
            tenant: $tenant,
            testata: [
                'anno_esercizio'     => $compenso->anno_competenza,
                'data_registrazione' => $compenso->data_pagamento->toDateString(),
                'causale_id'         => $causale->id,
                'descrizione'        => "Compenso a terzi: {$compenso->nome_percipiente}",
                'stato'              => 'bozza',
                'numero_documento'   => "CT-{$compenso->id}",
            ],
            righe: $righe,
        );

        $this->movService->conferma($mov);

        $compenso->update(['movimento_id' => $mov->id]);
    }

    private function validaDati(array $data): void
    {
        if (empty($data['codice_fiscale'])) {
            throw new InvalidArgumentException('Il codice fiscale del percipiente è obbligatorio.');
        }
        if (empty($data['nome_percipiente'])) {
            throw new InvalidArgumentException('Il nome del percipiente è obbligatorio.');
        }
        if (! isset($data['compenso_lordo']) || (float) $data['compenso_lordo'] <= 0) {
            throw new InvalidArgumentException('Il compenso lordo deve essere maggiore di zero.');
        }
    }
}
