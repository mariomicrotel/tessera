<?php

namespace App\Services;

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\EsercizioContabile;
use App\Models\RateoRisconto;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gestisce la registrazione e lo storno di ratei e risconti.
 *
 * Schema delle scritture generate:
 *
 *  rateo_attivo     → DARE conto_rettifica (ratei attivi SP)  / AVERE conto_economico (ricavo CE)
 *  rateo_passivo    → DARE conto_economico (costo CE)         / AVERE conto_rettifica (ratei passivi SP)
 *  risconto_attivo  → DARE conto_rettifica (risconti attivi)  / AVERE conto_economico (costo CE)
 *  risconto_passivo → DARE conto_economico (ricavo CE)        / AVERE conto_rettifica (risconti passivi SP)
 *
 * Lo storno all'inizio dell'anno successivo inverte dare/avere.
 */
class RateiRiscontiService
{
    public function __construct(
        private readonly MovimentoContabileService $movService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Crea (senza registrare)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crea un record rateo/risconto in stato da_registrare.
     * La quota_esercizio viene calcolata automaticamente se non fornita.
     *
     * @throws \InvalidArgumentException
     */
    public function crea(Tenant $tenant, array $data): RateoRisconto
    {
        $this->validaDati($data);

        $quota = $data['quota_esercizio'] ?? null;

        $rateo = RateoRisconto::make([
            'tenant_id'         => $tenant->id,
            'anno_esercizio'    => $data['anno_esercizio'],
            'tipo'              => $data['tipo'],
            'descrizione'       => $data['descrizione'],
            'importo_totale'    => $data['importo_totale'] ?? 0,
            'quota_esercizio'   => 0, // calcolata dopo
            'data_inizio'       => $data['data_inizio'],
            'data_fine'         => $data['data_fine'],
            'conto_economico_id' => $data['conto_economico_id'],
            'conto_rettifica_id' => $data['conto_rettifica_id'],
            'stato'             => RateoRisconto::STATO_DA_REGISTRARE,
            'note'              => $data['note'] ?? null,
        ]);

        // Se quota_esercizio non passata, calcola da proporzione giorni
        if ($quota === null) {
            $rateo->quota_esercizio = $rateo->calcolaQuota();
        } else {
            $rateo->quota_esercizio = (float) $quota;
        }

        if ($rateo->quota_esercizio <= 0) {
            throw new \InvalidArgumentException(
                'La quota di competenza calcolata è zero o negativa. Verifica le date e l\'importo.'
            );
        }

        $rateo->save();
        return $rateo->fresh(['contoEconomico', 'contoRettifica']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Registra (genera scrittura contabile)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera la scrittura contabile per il rateo/risconto e porta lo stato a 'registrato'.
     *
     * @throws \InvalidArgumentException
     */
    public function registra(RateoRisconto $rateo): RateoRisconto
    {
        if (! $rateo->isDaRegistrare()) {
            throw new \InvalidArgumentException(
                "Il rateo/risconto è già in stato '{$rateo->stato}' e non può essere registrato di nuovo."
            );
        }

        return DB::transaction(function () use ($rateo) {
            $causale  = $this->trovaCausale();
            $righe    = $this->costruisciRighe($rateo);

            $esercizio = EsercizioContabile::where('tenant_id', $rateo->tenant_id)
                ->where('anno', $rateo->anno_esercizio)
                ->first();

            if ($esercizio && $esercizio->isChiuso()) {
                throw new \InvalidArgumentException(
                    "L'esercizio {$rateo->anno_esercizio} è chiuso. Impossibile registrare."
                );
            }

            $movimento = $this->movService->crea(
                tenant: Tenant::find($rateo->tenant_id),
                testata: [
                    'anno_esercizio'    => $rateo->anno_esercizio,
                    'data_registrazione' => Carbon::create($rateo->anno_esercizio, 12, 31)->toDateString(),
                    'causale_id'        => $causale->id,
                    'descrizione'       => "Rateo/Risconto: {$rateo->descrizione}",
                    'stato'             => 'bozza',
                ],
                righe: $righe,
            );

            $movimento = $this->movService->conferma($movimento);

            $rateo->update([
                'stato'       => RateoRisconto::STATO_REGISTRATO,
                'movimento_id' => $movimento->id,
            ]);

            return $rateo->fresh(['movimento', 'contoEconomico', 'contoRettifica']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Storna (inizio anno successivo)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera la scrittura di storno all'inizio dell'anno successivo.
     * Richiede che il rateo/risconto sia già in stato 'registrato'.
     *
     * @throws \InvalidArgumentException
     */
    public function storna(RateoRisconto $rateo): RateoRisconto
    {
        if (! $rateo->isRegistrato()) {
            throw new \InvalidArgumentException(
                "Il rateo/risconto deve essere in stato 'registrato' per essere stornato."
            );
        }

        return DB::transaction(function () use ($rateo) {
            $annoStorno = $rateo->anno_esercizio + 1;
            $causale    = $this->trovaCausale();

            // Righe invertite rispetto alla registrazione
            $righePrimarie = $this->costruisciRighe($rateo);
            $righeStorno   = array_map(fn (array $r) => [
                'conto_contabile_id' => $r['conto_contabile_id'],
                'importo_dare'       => $r['importo_avere'],
                'importo_avere'      => $r['importo_dare'],
                'descrizione'        => $r['descrizione'],
            ], $righePrimarie);

            $storno = $this->movService->crea(
                tenant: Tenant::find($rateo->tenant_id),
                testata: [
                    'anno_esercizio'     => $annoStorno,
                    'data_registrazione' => Carbon::create($annoStorno, 1, 1)->toDateString(),
                    'causale_id'         => $causale->id,
                    'descrizione'        => "Storno rateo/risconto: {$rateo->descrizione}",
                    'stato'              => 'bozza',
                ],
                righe: $righeStorno,
            );

            $storno = $this->movService->conferma($storno);

            $rateo->update([
                'stato'    => RateoRisconto::STATO_STORNATO,
                'storno_id' => $storno->id,
            ]);

            return $rateo->fresh(['storno', 'movimento']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Batch registra
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra tutti i ratei/risconti da_registrare di un esercizio.
     *
     * @return array{registrati: int, errori: array<string>}
     */
    public function registraBatch(Tenant $tenant, int $anno): array
    {
        $ratei = RateoRisconto::where('tenant_id', $tenant->id)
            ->perAnno($anno)
            ->daRegistrare()
            ->get();

        $registrati = 0;
        $errori     = [];

        foreach ($ratei as $rateo) {
            try {
                $this->registra($rateo);
                $registrati++;
            } catch (\Throwable $e) {
                $errori[] = "#{$rateo->id} {$rateo->descrizione}: {$e->getMessage()}";
            }
        }

        return compact('registrati', 'errori');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Aggiorna
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Aggiorna un rateo/risconto in stato da_registrare.
     *
     * @throws \InvalidArgumentException
     */
    public function aggiorna(RateoRisconto $rateo, array $data): RateoRisconto
    {
        if (! $rateo->isDaRegistrare()) {
            throw new \InvalidArgumentException(
                "Solo i ratei/risconti in stato 'da_registrare' possono essere modificati."
            );
        }

        $this->validaDati(array_merge($rateo->toArray(), $data));

        $rateo->fill($data);

        // Ricalcola quota se non fornita esplicitamente
        if (! isset($data['quota_esercizio'])) {
            $rateo->quota_esercizio = $rateo->calcolaQuota();
        }

        $rateo->save();
        return $rateo->fresh(['contoEconomico', 'contoRettifica']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Elimina
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Elimina un rateo/risconto solo se non ancora registrato.
     *
     * @throws \InvalidArgumentException
     */
    public function elimina(RateoRisconto $rateo): void
    {
        if (! $rateo->isDaRegistrare()) {
            throw new \InvalidArgumentException(
                "Impossibile eliminare: il rateo/risconto è già stato registrato. Usa lo storno."
            );
        }

        $rateo->delete();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Costruisce le righe partita doppia secondo il tipo.
     *
     * @return array<array{conto_contabile_id: int, importo_dare: float, importo_avere: float, descrizione: string}>
     */
    private function costruisciRighe(RateoRisconto $rateo): array
    {
        $q    = $rateo->quota_esercizio;
        $desc = $rateo->descrizione;

        return match ($rateo->tipo) {

            // DARE rettifica (ratei attivi SP) / AVERE economico (ricavo)
            RateoRisconto::TIPO_RATEO_ATTIVO => [
                ['conto_contabile_id' => $rateo->conto_rettifica_id,  'importo_dare' => $q,   'importo_avere' => 0,  'descrizione' => $desc],
                ['conto_contabile_id' => $rateo->conto_economico_id,  'importo_dare' => 0,    'importo_avere' => $q, 'descrizione' => $desc],
            ],

            // DARE economico (costo) / AVERE rettifica (ratei passivi SP)
            RateoRisconto::TIPO_RATEO_PASSIVO => [
                ['conto_contabile_id' => $rateo->conto_economico_id,  'importo_dare' => $q,   'importo_avere' => 0,  'descrizione' => $desc],
                ['conto_contabile_id' => $rateo->conto_rettifica_id,  'importo_dare' => 0,    'importo_avere' => $q, 'descrizione' => $desc],
            ],

            // DARE rettifica (risconti attivi SP) / AVERE economico (costo da stornare)
            RateoRisconto::TIPO_RISCONTO_ATTIVO => [
                ['conto_contabile_id' => $rateo->conto_rettifica_id,  'importo_dare' => $q,   'importo_avere' => 0,  'descrizione' => $desc],
                ['conto_contabile_id' => $rateo->conto_economico_id,  'importo_dare' => 0,    'importo_avere' => $q, 'descrizione' => $desc],
            ],

            // DARE economico (ricavo da stornare) / AVERE rettifica (risconti passivi SP)
            RateoRisconto::TIPO_RISCONTO_PASSIVO => [
                ['conto_contabile_id' => $rateo->conto_economico_id,  'importo_dare' => $q,   'importo_avere' => 0,  'descrizione' => $desc],
                ['conto_contabile_id' => $rateo->conto_rettifica_id,  'importo_dare' => 0,    'importo_avere' => $q, 'descrizione' => $desc],
            ],

            default => throw new \InvalidArgumentException("Tipo rateo/risconto sconosciuto: {$rateo->tipo}"),
        };
    }

    private function trovaCausale(): CausaleContabile
    {
        return CausaleContabile::firstOrCreate(
            ['codice' => 'RAT'],
            [
                'descrizione' => 'Rateo / Risconto',
                'tipo'        => 'generico',
                'di_sistema'  => true,
                'attivo'      => true,
            ]
        );
    }

    private function validaDati(array $data): void
    {
        if (! isset($data['data_inizio'], $data['data_fine'])) {
            return;
        }

        $inizio = Carbon::parse($data['data_inizio']);
        $fine   = Carbon::parse($data['data_fine']);

        if ($inizio->gt($fine)) {
            throw new \InvalidArgumentException(
                'La data di inizio competenza non può essere successiva alla data di fine.'
            );
        }
    }
}
