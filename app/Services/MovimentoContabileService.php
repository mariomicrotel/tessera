<?php

namespace App\Services;

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\MovimentoContabile;
use App\Models\RigaMovimentoContabile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service per la gestione dei movimenti contabili in partita doppia.
 *
 * Responsabilità:
 *  - validazione del bilanciamento dare/avere
 *  - generazione del numero progressivo per esercizio
 *  - creazione, conferma e storno dei movimenti
 *  - calcolo saldo di un conto per un periodo
 *
 * Tutte le operazioni di scrittura avvengono in transazione DB.
 *
 * USO:
 *   $service = app(MovimentoContabileService::class);
 *
 *   $movimento = $service->crea($tenant, [
 *       'data_registrazione' => '2026-04-22',
 *       'causale_id'         => 5,
 *       'descrizione'        => 'Fattura fornitore Rossi n. 123',
 *       'numero_documento'   => 'FPA-2026-123',
 *   ], [
 *       ['conto_contabile_id' => 101, 'importo_dare'  => 1220.00, 'descrizione' => 'Merce c/acquisti'],
 *       ['conto_contabile_id' => 210, 'importo_avere' => 1000.00, 'descrizione' => 'Debiti v/fornitori'],
 *       ['conto_contabile_id' => 315, 'importo_avere' =>  220.00, 'descrizione' => 'IVA a credito'],
 *   ]);
 */
class MovimentoContabileService
{
    // ─────────────────────────────────────────────────────────────────────
    // Creazione
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crea un nuovo movimento contabile in stato "bozza".
     *
     * @param Tenant $tenant  Tenant corrente
     * @param array  $testata Attributi della testata (data, causale, descrizione, …)
     * @param array  $righe   Array di righe: [conto_contabile_id, importo_dare|avere, …]
     *
     * @throws InvalidArgumentException se le righe non sono bilanciate o i conti non sono movimentabili
     */
    public function crea(Tenant $tenant, array $testata, array $righe): MovimentoContabile
    {
        $this->validaRighe($tenant, $righe);

        return DB::transaction(function () use ($tenant, $testata, $righe) {
            $anno = $testata['anno_esercizio']
                ?? (int) ($testata['data_registrazione']
                    ? substr($testata['data_registrazione'], 0, 4)
                    : date('Y'));

            $numero = $this->prossimoNumero($tenant, $anno);

            $movimento = MovimentoContabile::create(array_merge($testata, [
                'tenant_id'     => $tenant->id,
                'anno_esercizio' => $anno,
                'numero'         => $numero,
                'stato'          => MovimentoContabile::STATO_BOZZA,
            ]));

            $this->salvaRighe($movimento, $righe);

            return $movimento->load('righe');
        });
    }

    /**
     * Porta un movimento da "bozza" a "definitivo".
     * Un movimento definitivo impatta i saldi (visible nei report).
     *
     * @throws InvalidArgumentException se il movimento non è in bozza o è locked
     */
    public function conferma(MovimentoContabile $movimento): MovimentoContabile
    {
        if (! $movimento->isBozza()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} non è in bozza: impossibile confermare."
            );
        }

        if ($movimento->isLocked()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} è bloccato (esercizio chiuso)."
            );
        }

        // Ricarica le righe e verifica bilanciamento
        $movimento->load('righe');
        if (! $movimento->isBilanciato()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} non è bilanciato: ".
                "dare={$movimento->totaleDare()}, avere={$movimento->totaleAvere()}."
            );
        }

        $movimento->update(['stato' => MovimentoContabile::STATO_DEFINITIVO]);

        return $movimento;
    }

    /**
     * Storna un movimento definitivo.
     *
     * Crea un nuovo movimento con righe dare/avere invertite e
     * marca l'originale come "stornato".
     *
     * @throws InvalidArgumentException se il movimento non è definitivo o è locked
     */
    public function storna(MovimentoContabile $movimento, ?string $descrizioneStorno = null): MovimentoContabile
    {
        if (! $movimento->isDefinitivo()) {
            throw new InvalidArgumentException(
                "Impossibile stornare: il movimento {$movimento->etichetta} non è definitivo."
            );
        }

        if ($movimento->isLocked()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} è bloccato: impossibile stornare."
            );
        }

        if ($movimento->isStornato()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} è già stato stornato."
            );
        }

        return DB::transaction(function () use ($movimento, $descrizioneStorno) {
            $tenant = Tenant::findOrFail($movimento->tenant_id);

            $anno   = (int) date('Y');
            $numero = $this->prossimoNumero($tenant, $anno);

            $descrizione = $descrizioneStorno
                ?? "Storno del movimento {$movimento->etichetta}: {$movimento->descrizione}";

            $storno = MovimentoContabile::create([
                'tenant_id'           => $movimento->tenant_id,
                'anno_esercizio'      => $anno,
                'numero'              => $numero,
                'data_registrazione'  => now()->toDateString(),
                'causale_id'          => $movimento->causale_id,
                'descrizione'         => $descrizione,
                'gestione'            => $movimento->gestione,
                'stato'               => MovimentoContabile::STATO_DEFINITIVO,
                'movimento_origine_id' => $movimento->id,
                'note'                => "Storno automatico del movimento n. {$movimento->numero}/{$movimento->anno_esercizio}",
            ]);

            // Righe invertite
            $movimento->load('righe');
            foreach ($movimento->righe as $ordine => $riga) {
                RigaMovimentoContabile::create([
                    'movimento_id'      => $storno->id,
                    'ordine'            => $ordine + 1,
                    'conto_contabile_id' => $riga->conto_contabile_id,
                    'descrizione'       => $riga->descrizione,
                    'importo_dare'      => $riga->importo_avere,  // ← inversione
                    'importo_avere'     => $riga->importo_dare,   // ← inversione
                    'centro_costo'      => $riga->centro_costo,
                    'gestione'          => $riga->gestione,
                ]);
            }

            // Marca l'originale come stornato
            $movimento->update(['stato' => MovimentoContabile::STATO_STORNATO]);

            return $storno->load('righe');
        });
    }

    /**
     * Aggiorna le righe di un movimento in bozza.
     *
     * @throws InvalidArgumentException se il movimento non è modificabile
     */
    public function aggiornaRighe(MovimentoContabile $movimento, array $righe): MovimentoContabile
    {
        if (! $movimento->isModificabile()) {
            throw new InvalidArgumentException(
                "Il movimento {$movimento->etichetta} non è modificabile."
            );
        }

        $this->validaRighe(Tenant::findOrFail($movimento->tenant_id), $righe);

        DB::transaction(function () use ($movimento, $righe) {
            $movimento->righe()->delete();
            $this->salvaRighe($movimento, $righe);
        });

        return $movimento->fresh('righe');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Saldo conto
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calcola il saldo (dare − avere) di un conto per un periodo.
     *
     * Considera solo i movimenti "definitivi".
     * Un saldo positivo = eccedenza dare; negativo = eccedenza avere.
     *
     * @param ContoContabile $conto
     * @param int            $anno
     * @param string|null    $dal   Data inizio (Y-m-d), null = inizio anno
     * @param string|null    $al    Data fine (Y-m-d), null = oggi
     * @return float
     */
    public function saldoConto(ContoContabile $conto, int $anno, ?string $dal = null, ?string $al = null): float
    {
        $dal ??= "{$anno}-01-01";
        $al  ??= now()->toDateString();

        $tenantId = $conto->tenant_id;
        $contoId  = $conto->id;

        $totale = RigaMovimentoContabile::query()
            ->join('movimenti_contabili', 'movimenti_contabili.id', '=', 'righe_movimento_contabile.movimento_id')
            ->where('movimenti_contabili.tenant_id', $tenantId)
            ->where('movimenti_contabili.stato', MovimentoContabile::STATO_DEFINITIVO)
            ->where('movimenti_contabili.anno_esercizio', $anno)
            ->whereBetween('movimenti_contabili.data_registrazione', [$dal, $al])
            ->where('righe_movimento_contabile.conto_contabile_id', $contoId)
            ->selectRaw('SUM(righe_movimento_contabile.importo_dare) as tot_dare, SUM(righe_movimento_contabile.importo_avere) as tot_avere')
            ->first();

        return (float) ($totale->tot_dare ?? 0) - (float) ($totale->tot_avere ?? 0);
    }

    /**
     * Calcola i saldi di più conti in una sola query (efficiente per listing).
     *
     * Restituisce: [ conto_contabile_id => saldo_float ]
     *
     * @param int[]      $contoIds
     * @param string     $tenantId
     * @param int        $anno
     * @param string|null $dal
     * @param string|null $al
     * @return array<int, float>
     */
    public function saldoConti(array $contoIds, string $tenantId, int $anno, ?string $dal = null, ?string $al = null): array
    {
        if (empty($contoIds)) {
            return [];
        }

        $dal ??= "{$anno}-01-01";
        $al  ??= now()->toDateString();

        $saldi = array_fill_keys($contoIds, 0.0);

        // Carica tutti i conti richiesti e calcola il saldo individuale
        // tramite lo stesso JOIN usato in saldoConto (una query per conto).
        // Per liste grandi, sostituire con un'unica query aggregata raggruppata.
        $conti = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $contoIds)
            ->get();

        foreach ($conti as $conto) {
            $saldi[$conto->id] = $this->saldoConto($conto, $anno, $dal, $al);
        }

        return $saldi;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Numero progressivo successivo per tenant + anno.
     * Usa un lock pessimistico per evitare race condition in concorrenza.
     */
    public function prossimoNumero(Tenant $tenant, int $anno): int
    {
        $max = MovimentoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('anno_esercizio', $anno)
            ->lockForUpdate()
            ->max('numero');

        return (int) $max + 1;
    }

    /**
     * Valida le righe del movimento:
     *  1. almeno 2 righe
     *  2. ogni riga ha XOR importo_dare/avere > 0
     *  3. tutti i conti sono movimentabili
     *  4. dare totale == avere totale
     *
     * @throws InvalidArgumentException
     */
    public function validaRighe(Tenant $tenant, array $righe): void
    {
        if (count($righe) < 2) {
            throw new InvalidArgumentException(
                'Un movimento deve avere almeno 2 righe (una dare e una avere).'
            );
        }

        $totDare  = 0.0;
        $totAvere = 0.0;

        $contoIds = array_column($righe, 'conto_contabile_id');

        // Carica tutti i conti in una sola query
        $conti = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $contoIds)
            ->get()
            ->keyBy('id');

        foreach ($righe as $i => $riga) {
            $nr = $i + 1;

            $dare  = (float) ($riga['importo_dare']  ?? 0);
            $avere = (float) ($riga['importo_avere'] ?? 0);

            // Verifica XOR: uno solo deve essere > 0
            if ($dare <= 0 && $avere <= 0) {
                throw new InvalidArgumentException(
                    "Riga {$nr}: importo_dare e importo_avere sono entrambi zero o negativi."
                );
            }

            if ($dare > 0 && $avere > 0) {
                throw new InvalidArgumentException(
                    "Riga {$nr}: importo_dare e importo_avere sono entrambi positivi. "
                    .'Solo uno dei due può essere valorizzato per riga.'
                );
            }

            // Verifica conto movimentabile
            $contoId = $riga['conto_contabile_id'] ?? null;

            if (! $contoId || ! isset($conti[$contoId])) {
                throw new InvalidArgumentException(
                    "Riga {$nr}: conto_contabile_id={$contoId} non trovato nel piano dei conti del tenant."
                );
            }

            $conto = $conti[$contoId];

            if (! $conto->isMovimentabile()) {
                throw new InvalidArgumentException(
                    "Riga {$nr}: il conto '{$conto->codice} – {$conto->descrizione}' "
                    .'non è movimentabile (solo i sottoconti di livello 4 sono ammessi).'
                );
            }

            $totDare  += $dare;
            $totAvere += $avere;
        }

        // Bilanciamento (tolleranza mezzo centesimo per floating point)
        if (abs($totDare - $totAvere) >= 0.005) {
            throw new InvalidArgumentException(
                sprintf(
                    'Movimento non bilanciato: totale dare %.2f ≠ totale avere %.2f (differenza: %.2f).',
                    $totDare,
                    $totAvere,
                    $totDare - $totAvere
                )
            );
        }
    }

    /**
     * Persiste le righe sul movimento.
     */
    private function salvaRighe(MovimentoContabile $movimento, array $righe): void
    {
        foreach ($righe as $i => $dati) {
            RigaMovimentoContabile::create(array_merge($dati, [
                'movimento_id' => $movimento->id,
                'ordine'       => $dati['ordine'] ?? ($i + 1),
                'importo_dare'  => (float) ($dati['importo_dare']  ?? 0),
                'importo_avere' => (float) ($dati['importo_avere'] ?? 0),
            ]));
        }
    }
}
