<?php

namespace App\Services;

use App\Models\CausaleContabile;
use App\Models\ContoContabile;
use App\Models\EsercizioContabile;
use App\Models\MovimentoContabile;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service per la gestione dell'apertura e chiusura dell'esercizio contabile.
 *
 * FLUSSO DI CHIUSURA ESERCIZIO (anno N):
 *  1. Scrittura di chiusura CE → azzera tutti i conti economici (costi/ricavi)
 *     verso il conto di riepilogo (conto_chiusura_ce_id), di norma un conto
 *     di Patrimonio Netto "Utile/Perdita d'esercizio" o transitorio.
 *  2. Scrittura di chiusura SP → azzera tutti i conti patrimoniali
 *     (attivo/passivo/PN incluso il conto_chiusura_ce) verso il conto di apertura.
 *  3. Lock retroattivo: tutti i movimenti definitivi dell'anno N vengono bloccati
 *     (locked = true): nessuna ulteriore modifica.
 *  4. Scrittura di apertura → primo movimento dell'anno N+1 che riapre
 *     i saldi patrimoniali (inverso della chiusura SP).
 *
 * FLUSSO DI RIAPERTURA ESERCIZIO (rollback, solo anno più recente):
 *  1. Cancella i movimenti di chiusura CE, chiusura SP, apertura N+1.
 *  2. Sblocca tutti i movimenti dell'anno (locked = false).
 *  3. Riporta l'esercizio allo stato "aperto".
 */
class AperturaChiusuraService
{
    public function __construct(
        private readonly MovimentoContabileService $movService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Apertura esercizio
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra l'apertura di un nuovo esercizio contabile.
     * Se esiste già il record, restituisce quello esistente.
     *
     * @throws InvalidArgumentException se l'anno è già chiuso
     */
    public function apriEsercizio(Tenant $tenant, int $anno): EsercizioContabile
    {
        $esistente = EsercizioContabile::perAnno($anno)->first();
        if ($esistente) {
            return $esistente;
        }

        return EsercizioContabile::create([
            'tenant_id'      => $tenant->id,
            'anno'           => $anno,
            'stato'          => EsercizioContabile::STATO_APERTO,
            'data_apertura'  => "{$anno}-01-01",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Chiusura esercizio
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Esegue la procedura completa di chiusura dell'esercizio.
     *
     * Prerequisiti:
     *  - L'esercizio deve essere in stato "aperto"
     *  - conto_chiusura_ce_id e conto_apertura_id devono essere configurati
     *
     * @throws InvalidArgumentException per stato o configurazione non valida
     */
    public function chiudiEsercizio(EsercizioContabile $esercizio): EsercizioContabile
    {
        if ($esercizio->isChiuso()) {
            throw new InvalidArgumentException(
                "L'esercizio {$esercizio->anno} è già chiuso."
            );
        }

        if (! $esercizio->isConfiguratoPerChiusura()) {
            throw new InvalidArgumentException(
                "Configurare i conti di chiusura (Riepilogo CE e Conto Apertura) prima di procedere."
            );
        }

        return DB::transaction(function () use ($esercizio) {
            $tenant = Tenant::findOrFail($esercizio->tenant_id);
            $anno   = $esercizio->anno;

            // 1. Causali di sistema per chiusura e apertura
            $causaleChiusura = $this->trovaCausale($tenant, CausaleContabile::TIPO_CHIUSURA);
            $causaleApertura = $this->trovaCausale($tenant, CausaleContabile::TIPO_APERTURA);

            // 2. Chiusura CE
            $movChiusuraCe = $this->generaChiusuraCe($tenant, $esercizio, $causaleChiusura, $anno);

            // 3. Chiusura SP (i saldi ora includono il risultato CE nel conto_chiusura_ce)
            $movChiusuraSp = $this->generaChiusuraSp($tenant, $esercizio, $causaleChiusura, $anno);

            // 4. Lock retroattivo movimenti dell'anno
            MovimentoContabile::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('anno_esercizio', $anno)
                ->where('stato', MovimentoContabile::STATO_DEFINITIVO)
                ->update(['locked' => true]);

            // 5. Scrittura di apertura per l'anno successivo (inverso chiusura SP)
            $movApertura = $this->generaApertura($tenant, $esercizio, $causaleApertura, $movChiusuraSp, $anno);

            // 6. Aggiorna esercizio
            $esercizio->update([
                'stato'                    => EsercizioContabile::STATO_CHIUSO,
                'data_chiusura'            => "{$anno}-12-31",
                'movimento_chiusura_ce_id' => $movChiusuraCe?->id,
                'movimento_chiusura_sp_id' => $movChiusuraSp?->id,
                'movimento_apertura_id'    => $movApertura?->id,
                'locked_at'                => now(),
            ]);

            return $esercizio->refresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Riapertura esercizio (rollback)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Riapre un esercizio chiuso, annullando le scritture automatiche e sbloccando i movimenti.
     *
     * Consentito solo se non esiste un esercizio chiuso nell'anno successivo
     * (impedisce di "rimescolando" la catena contabile).
     *
     * @throws InvalidArgumentException se l'esercizio è aperto o l'anno successivo è già chiuso
     */
    public function riaperiEsercizio(EsercizioContabile $esercizio): EsercizioContabile
    {
        if ($esercizio->isAperto()) {
            throw new InvalidArgumentException(
                "L'esercizio {$esercizio->anno} è già aperto."
            );
        }

        // Impedisci riapertura se l'anno successivo è già stato chiuso
        $annoSuccessivoChiuso = EsercizioContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $esercizio->tenant_id)
            ->where('anno', $esercizio->anno + 1)
            ->where('stato', EsercizioContabile::STATO_CHIUSO)
            ->exists();

        if ($annoSuccessivoChiuso) {
            throw new InvalidArgumentException(
                "Impossibile riaprire l'esercizio {$esercizio->anno}: l'anno " .
                ($esercizio->anno + 1) . " è già stato chiuso."
            );
        }

        return DB::transaction(function () use ($esercizio) {
            $tenant = Tenant::findOrFail($esercizio->tenant_id);
            $anno   = $esercizio->anno;

            // Elimina movimenti generati dall'apertura dell'anno successivo
            if ($esercizio->movimento_apertura_id) {
                MovimentoContabile::withoutGlobalScope('tenant')
                    ->where('id', $esercizio->movimento_apertura_id)
                    ->delete();
            }

            // Elimina movimenti di chiusura CE e SP
            foreach (['movimento_chiusura_sp_id', 'movimento_chiusura_ce_id'] as $campo) {
                if ($esercizio->{$campo}) {
                    MovimentoContabile::withoutGlobalScope('tenant')
                        ->where('id', $esercizio->{$campo})
                        ->delete();
                }
            }

            // Sblocca tutti i movimenti dell'anno
            MovimentoContabile::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('anno_esercizio', $anno)
                ->update(['locked' => false]);

            // Riporta esercizio ad aperto
            $esercizio->update([
                'stato'                    => EsercizioContabile::STATO_APERTO,
                'data_chiusura'            => null,
                'movimento_chiusura_ce_id' => null,
                'movimento_chiusura_sp_id' => null,
                'movimento_apertura_id'    => null,
                'locked_at'                => null,
            ]);

            return $esercizio->refresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Genera il movimento di chiusura del Conto Economico.
     *
     * Per ogni conto CE (costo/ricavo) con saldo ≠ 0:
     *  - Costo (saldo dare):  AVERE il conto, DARE il conto_chiusura_ce
     *  - Ricavo (saldo avere): DARE il conto, AVERE il conto_chiusura_ce
     *
     * @return MovimentoContabile|null  null se non ci sono saldi CE
     */
    private function generaChiusuraCe(
        Tenant $tenant,
        EsercizioContabile $esercizio,
        CausaleContabile $causale,
        int $anno
    ): ?MovimentoContabile {
        $saldi = $this->calcolaSaldiPerNatura(
            $tenant->id,
            $anno,
            [ContoContabile::NATURA_COSTO, ContoContabile::NATURA_RICAVO]
        );

        if ($saldi->isEmpty()) {
            return null;
        }

        $righe = [];
        $totaleDareCe  = 0.0;
        $totaleAvereCe = 0.0;

        foreach ($saldi as $riga) {
            $saldo = round($riga->saldo, 2); // dare - avere
            if (abs($saldo) < 0.005) {
                continue;
            }

            if ($saldo > 0) {
                // Conto con saldo dare (tipicamente: costi) → chiudo con AVERE
                $righe[] = [
                    'conto_contabile_id' => $riga->conto_contabile_id,
                    'importo_avere'      => $saldo,
                    'importo_dare'       => 0,
                    'descrizione'        => "Chiusura CE {$anno}: {$riga->codice}",
                ];
                $totaleDareCe += $saldo;
            } else {
                // Conto con saldo avere (tipicamente: ricavi) → chiudo con DARE
                $importo = abs($saldo);
                $righe[] = [
                    'conto_contabile_id' => $riga->conto_contabile_id,
                    'importo_dare'       => $importo,
                    'importo_avere'      => 0,
                    'descrizione'        => "Chiusura CE {$anno}: {$riga->codice}",
                ];
                $totaleAvereCe += $importo;
            }
        }

        if (empty($righe)) {
            return null;
        }

        // Contropartita: conto_chiusura_ce
        // totaleDareCe = totale AVERE aggiunte per chiudere conti con saldo dare (costi)
        // totaleAvereCe = totale DARE aggiunte per chiudere conti con saldo avere (ricavi)
        // Movimento corrente: DARE = totaleAvereCe, AVERE = totaleDareCe
        // Per bilanciare: se DARE > AVERE → serve AVERE nel conto_chiusura_ce (utile)
        //                 se AVERE > DARE → serve DARE nel conto_chiusura_ce (perdita)
        $differenza = round($totaleDareCe - $totaleAvereCe, 2);
        if ($differenza > 0) {
            // AVERE_CE > DARE_CE → costi > ricavi → perdita
            // Serve DARE conto_chiusura_ce per bilanciare
            $righe[] = [
                'conto_contabile_id' => $esercizio->conto_chiusura_ce_id,
                'importo_dare'       => $differenza,
                'importo_avere'      => 0,
                'descrizione'        => "Risultato d'esercizio {$anno} (perdita)",
            ];
        } elseif ($differenza < 0) {
            // DARE_CE > AVERE_CE → ricavi > costi → utile
            // Serve AVERE conto_chiusura_ce per bilanciare
            $righe[] = [
                'conto_contabile_id' => $esercizio->conto_chiusura_ce_id,
                'importo_avere'      => abs($differenza),
                'importo_dare'       => 0,
                'descrizione'        => "Risultato d'esercizio {$anno} (utile)",
            ];
        }
        // Se differenza = 0: CE perfettamente in pareggio, nessuna riga per il conto di chiusura

        if (empty($righe)) {
            return null;
        }

        $movimento = $this->movService->crea($tenant, [
            'data_registrazione' => "{$anno}-12-31",
            'data_competenza'    => "{$anno}-12-31",
            'causale_id'         => $causale->id,
            'descrizione'        => "Chiusura Conto Economico {$anno}",
            'anno_esercizio'     => $anno,
        ], $righe);

        return $this->movService->conferma($movimento);
    }

    /**
     * Genera il movimento di chiusura dello Stato Patrimoniale.
     *
     * Per ogni conto SP (attivo/passivo/PN incluso conto_chiusura_ce) con saldo ≠ 0:
     *  - Saldo dare (attivo):  AVERE il conto, DARE conto_apertura
     *  - Saldo avere (passivo/PN): DARE il conto, AVERE conto_apertura
     *
     * @return MovimentoContabile|null  null se non ci sono saldi SP
     */
    private function generaChiusuraSp(
        Tenant $tenant,
        EsercizioContabile $esercizio,
        CausaleContabile $causale,
        int $anno
    ): ?MovimentoContabile {
        $nature = [
            ContoContabile::NATURA_ATTIVO,
            ContoContabile::NATURA_PASSIVO,
            ContoContabile::NATURA_PATRIMONIO_NETTO,
            ContoContabile::NATURA_TRANSITORIO,
        ];

        $saldi = $this->calcolaSaldiPerNatura($tenant->id, $anno, $nature);

        if ($saldi->isEmpty()) {
            return null;
        }

        $righe = [];
        $totaleDare  = 0.0;
        $totaleAvere = 0.0;

        foreach ($saldi as $riga) {
            $saldo = round($riga->saldo, 2);
            if (abs($saldo) < 0.005) {
                continue;
            }

            if ($saldo > 0) {
                // Saldo dare → chiudo con AVERE
                $righe[] = [
                    'conto_contabile_id' => $riga->conto_contabile_id,
                    'importo_avere'      => $saldo,
                    'importo_dare'       => 0,
                    'descrizione'        => "Chiusura SP {$anno}: {$riga->codice}",
                ];
                $totaleAvere += $saldo;
            } else {
                // Saldo avere → chiudo con DARE
                $importo = abs($saldo);
                $righe[] = [
                    'conto_contabile_id' => $riga->conto_contabile_id,
                    'importo_dare'       => $importo,
                    'importo_avere'      => 0,
                    'descrizione'        => "Chiusura SP {$anno}: {$riga->codice}",
                ];
                $totaleDare += $importo;
            }
        }

        if (empty($righe)) {
            return null;
        }

        // Bilanciamento con conto_apertura
        if ($totaleDare > $totaleAvere) {
            $diff = round($totaleDare - $totaleAvere, 2);
            $righe[] = [
                'conto_contabile_id' => $esercizio->conto_apertura_id,
                'importo_avere'      => $diff,
                'importo_dare'       => 0,
                'descrizione'        => "Saldo patrimoniale {$anno}",
            ];
        } elseif ($totaleAvere > $totaleDare) {
            $diff = round($totaleAvere - $totaleDare, 2);
            $righe[] = [
                'conto_contabile_id' => $esercizio->conto_apertura_id,
                'importo_dare'       => $diff,
                'importo_avere'      => 0,
                'descrizione'        => "Saldo patrimoniale {$anno}",
            ];
        }

        if (empty($righe)) {
            return null;
        }

        $movimento = $this->movService->crea($tenant, [
            'data_registrazione' => "{$anno}-12-31",
            'data_competenza'    => "{$anno}-12-31",
            'causale_id'         => $causale->id,
            'descrizione'        => "Chiusura Stato Patrimoniale {$anno}",
            'anno_esercizio'     => $anno,
        ], $righe);

        return $this->movService->conferma($movimento);
    }

    /**
     * Genera il movimento di apertura dell'anno successivo (inverso della chiusura SP).
     *
     * Per ogni riga del movimento di chiusura SP:
     *  - DARE → AVERE (specchio)
     *  - AVERE → DARE (specchio)
     *
     * @return MovimentoContabile|null  null se non c'è una chiusura SP
     */
    private function generaApertura(
        Tenant $tenant,
        EsercizioContabile $esercizio,
        CausaleContabile $causale,
        ?MovimentoContabile $movChiusuraSp,
        int $annoChiuso
    ): ?MovimentoContabile {
        if (! $movChiusuraSp) {
            return null;
        }

        $annoNuovo = $annoChiuso + 1;
        $movChiusuraSp->load('righe');

        if ($movChiusuraSp->righe->isEmpty()) {
            return null;
        }

        $righe = $movChiusuraSp->righe->map(function ($riga) use ($annoNuovo) {
            return [
                'conto_contabile_id' => $riga->conto_contabile_id,
                'importo_dare'       => $riga->importo_avere, // specchio
                'importo_avere'      => $riga->importo_dare,  // specchio
                'descrizione'        => "Apertura {$annoNuovo}: {$riga->descrizione}",
            ];
        })->toArray();

        $movimento = $this->movService->crea($tenant, [
            'data_registrazione' => "{$annoNuovo}-01-01",
            'data_competenza'    => "{$annoNuovo}-01-01",
            'causale_id'         => $causale->id,
            'descrizione'        => "Apertura esercizio {$annoNuovo}",
            'anno_esercizio'     => $annoNuovo,
        ], $righe);

        return $this->movService->conferma($movimento);
    }

    /**
     * Calcola il saldo (dare - avere) per tutti i conti movimentabili
     * di una data lista di nature contabili, per l'anno indicato.
     *
     * Considera solo movimenti DEFINITIVI. Esclude i movimenti locked
     * (di chiusura già effettuata) tramite il flag `locked`.
     *
     * @param  string   $tenantId
     * @param  int      $anno
     * @param  string[] $nature
     * @return Collection  collezione di oggetti {conto_contabile_id, codice, saldo}
     */
    private function calcolaSaldiPerNatura(string $tenantId, int $anno, array $nature): Collection
    {
        return DB::table('righe_movimento_contabile as r')
            ->join('movimenti_contabili as m', 'm.id', '=', 'r.movimento_id')
            ->join('conti_contabili as c', 'c.id', '=', 'r.conto_contabile_id')
            ->where('m.tenant_id', $tenantId)
            ->where('m.anno_esercizio', $anno)
            ->where('m.stato', MovimentoContabile::STATO_DEFINITIVO)
            ->where('m.locked', false)   // esclude movimenti già bloccati da precedenti chiusure
            ->whereIn('c.natura', $nature)
            ->where('c.movimentabile', true)
            ->groupBy('r.conto_contabile_id', 'c.codice')
            ->select(
                'r.conto_contabile_id',
                'c.codice',
                DB::raw('COALESCE(SUM(r.importo_dare),0) - COALESCE(SUM(r.importo_avere),0) as saldo')
            )
            ->get();
    }

    /**
     * Trova o crea una causale di sistema del tipo indicato.
     */
    private function trovaCausale(Tenant $tenant, string $tipo): CausaleContabile
    {
        // Cerca causale di sistema per tipo nel tenant corrente
        $causale = CausaleContabile::where('tipo', $tipo)
            ->where('di_sistema', true)
            ->first();

        if ($causale) {
            return $causale;
        }

        // Crea causale di sistema se non esiste
        $labels = [
            CausaleContabile::TIPO_CHIUSURA => ['CHIUS', 'Chiusura esercizio'],
            CausaleContabile::TIPO_APERTURA => ['APERT', 'Apertura esercizio'],
        ];

        [$codice, $descrizione] = $labels[$tipo] ?? [$tipo, $tipo];

        return CausaleContabile::create([
            'tenant_id'   => $tenant->id,
            'codice'      => $codice,
            'descrizione' => $descrizione,
            'tipo'        => $tipo,
            'di_sistema'  => true,
            'attivo'      => true,
        ]);
    }
}
