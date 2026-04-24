<?php

namespace App\Services;

use App\Models\Conto;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\PrestitoSocialeLibretto;
use App\Models\PrestitoSocialeMovimento;
use App\Models\PrimaNotaEntry;
use App\Models\Spesa;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Gestione del prestito sociale cooperativo.
 *
 * Il socio deposita su un libretto somme che la cooperativa impiega
 * nell'attività caratteristica. Sulle somme depositate matura un interesse
 * annuo, soggetto a ritenuta fiscale del 26% (DPR 600/73 art. 26).
 *
 * Regole di legge principali:
 *  - Prelievi > €5.000 richiedono preavviso di almeno 24h (parametro $prenotato)
 *  - Saldo massimo per socio: €40.000 circa (non applicato qui a livello codice)
 *  - Calcolo interessi su saldo medio ponderato per giorni di valuta
 *
 * Tutti i metodi che scrivono su più tabelle sono avvolti in DB::transaction.
 */
class PrestitoSocialeService
{
    /** Aliquota ritenuta fiscale su interessi (DPR 600/73 art. 26). */
    public const ALIQUOTA_RITENUTA = 0.26;

    /** Soglia oltre la quale un prelievo richiede prenotazione/preavviso. */
    public const SOGLIA_PRELIEVO_PRENOTATO = 5000.00;

    /**
     * Restituisce il primo conto attivo del tenant.
     *
     * @throws RuntimeException
     */
    private function conto(): Conto
    {
        $conto = Conto::where('attivo', true)->orderBy('ordine')->orderBy('id')->first();
        if (! $conto) {
            throw new RuntimeException('Nessun conto attivo trovato. Crea almeno un conto in Tesoreria.');
        }
        return $conto;
    }

    // ── a) Apri libretto ──────────────────────────────────────────────────────

    /**
     * Apre un nuovo libretto di prestito sociale per un socio.
     *
     * Il numero libretto ha il formato "PS-{ANNO}-{5 cifre progressive per tenant}".
     */
    public function apriLibretto(
        Member $member,
        float $tassoAnnuo,
        Carbon $data,
    ): PrestitoSocialeLibretto {
        return DB::transaction(function () use ($member, $tassoAnnuo, $data) {
            $anno = $data->year;

            // Trova il progressivo più alto per l'anno corrente
            $prefix = "PS-{$anno}-";
            $ultimo = PrestitoSocialeLibretto::where('numero_libretto', 'LIKE', $prefix.'%')
                ->orderByDesc('numero_libretto')
                ->value('numero_libretto');

            $progressivo = 1;
            if ($ultimo) {
                $num = (int) substr($ultimo, strlen($prefix));
                $progressivo = $num + 1;
            }

            $numeroLibretto = sprintf('%s%05d', $prefix, $progressivo);

            return PrestitoSocialeLibretto::create([
                'member_id'             => $member->id,
                'numero_libretto'       => $numeroLibretto,
                'saldo_attuale'         => 0,
                'tasso_interesse_annuo' => round($tassoAnnuo, 4),
                'data_apertura'         => $data,
                'status'                => 'attivo',
            ]);
        });
    }

    // ── b) Deposita ───────────────────────────────────────────────────────────

    /**
     * Registra un deposito sul libretto.
     *
     * - Crea movimento tipo 'deposito', segno 'avere'
     * - Aggiorna saldo_attuale del libretto
     * - Crea Incasso tipo 'prestito_sociale' con generazione prima nota
     *
     * @throws RuntimeException se libretto non attivo o conto mancante
     */
    public function deposita(
        PrestitoSocialeLibretto $lib,
        float $importo,
        Carbon $data,
        string $desc = '',
    ): PrestitoSocialeMovimento {
        if ($importo <= 0) {
            throw new RuntimeException('L\'importo del deposito deve essere positivo.');
        }
        if (! $lib->isAttivo()) {
            throw new RuntimeException("Il libretto {$lib->numero_libretto} non è attivo.");
        }

        return DB::transaction(function () use ($lib, $importo, $data, $desc) {
            $conto       = $this->conto();
            $nuovoSaldo  = round((float) $lib->saldo_attuale + $importo, 2);
            $nomeSocio   = $lib->member?->nomeCompleto() ?? "socio #{$lib->member_id}";
            $descrizione = $desc !== '' ? $desc : "Deposito su libretto {$lib->numero_libretto}";

            // Incasso tipo 'prestito_sociale'
            $incasso = Incasso::create([
                'member_id'         => $lib->member_id,
                'amount'            => $importo,
                'paid_at'           => $data,
                'conto_id'          => $conto->id,
                'description'       => $descrizione." ({$nomeSocio})",
                'genera_prima_nota' => true,
                'type'              => Incasso::TYPE_PRESTITO_SOCIALE,
            ]);

            // Prima nota (rendiconto_code dedicato)
            PrimaNotaEntry::create([
                'conto_id'        => $conto->id,
                'rendiconto_code' => 'prestito_sociale',
                'entryable_type'  => Incasso::class,
                'entryable_id'    => $incasso->id,
                'date'            => $data,
                'amount'          => $importo,
                'description'     => "Prestito sociale – deposito ({$nomeSocio}, libretto {$lib->numero_libretto})",
            ]);

            // Movimento sul libretto
            $mov = PrestitoSocialeMovimento::create([
                'libretto_id'        => $lib->id,
                'tipo'               => PrestitoSocialeMovimento::TIPO_DEPOSITO,
                'importo'            => $importo,
                'segno'              => PrestitoSocialeMovimento::SEGNO_AVERE,
                'saldo_dopo'         => $nuovoSaldo,
                'data_valuta'        => $data,
                'data_registrazione' => Carbon::today(),
                'descrizione'        => $descrizione,
                'incasso_id'         => $incasso->id,
            ]);

            // Aggiorna saldo libretto
            $lib->update(['saldo_attuale' => $nuovoSaldo]);

            return $mov;
        });
    }

    // ── c) Preleva ────────────────────────────────────────────────────────────

    /**
     * Registra un prelievo dal libretto.
     *
     * Regole:
     *  - Il saldo disponibile deve essere >= importo
     *  - Prelievi > €5.000 richiedono prenotazione (preavviso 24h)
     *
     * @throws ValidationException per violazione regole (saldo, prenotazione)
     * @throws RuntimeException    per condizioni di sistema (libretto non attivo, conto mancante)
     */
    public function preleva(
        PrestitoSocialeLibretto $lib,
        float $importo,
        Carbon $data,
        string $desc = '',
        bool $prenotato = false,
    ): PrestitoSocialeMovimento {
        if ($importo <= 0) {
            throw new RuntimeException('L\'importo del prelievo deve essere positivo.');
        }
        if (! $lib->isAttivo()) {
            throw new RuntimeException("Il libretto {$lib->numero_libretto} non è attivo.");
        }

        // Regola soglia prelievo prenotato
        if ($importo > self::SOGLIA_PRELIEVO_PRENOTATO && ! $prenotato) {
            throw ValidationException::withMessages([
                'importo' => sprintf(
                    'I prelievi superiori a €%s richiedono prenotazione con preavviso di 24 ore.',
                    number_format(self::SOGLIA_PRELIEVO_PRENOTATO, 2, ',', '.'),
                ),
            ]);
        }

        // Verifica saldo sufficiente
        $saldoCorrente = (float) $lib->saldo_attuale;
        if ($saldoCorrente + 0.01 < $importo) {
            throw ValidationException::withMessages([
                'importo' => sprintf(
                    'Saldo insufficiente: disponibili €%.2f, richiesti €%.2f.',
                    $saldoCorrente,
                    $importo,
                ),
            ]);
        }

        return DB::transaction(function () use ($lib, $importo, $data, $desc) {
            $conto       = $this->conto();
            $nuovoSaldo  = round((float) $lib->saldo_attuale - $importo, 2);
            $nomeSocio   = $lib->member?->nomeCompleto() ?? "socio #{$lib->member_id}";
            $descrizione = $desc !== '' ? $desc : "Prelievo da libretto {$lib->numero_libretto}";

            // Spesa per l'uscita di cassa
            $spesa = Spesa::create([
                'date'              => $data,
                'amount'            => $importo,
                'description'       => $descrizione." ({$nomeSocio})",
                'conto_id'          => $conto->id,
                'genera_prima_nota' => true,
                'rendiconto_code'   => 'prestito_sociale',
                'competenza_cassa'  => true,
            ]);

            PrimaNotaEntry::create([
                'conto_id'        => $conto->id,
                'rendiconto_code' => 'prestito_sociale',
                'entryable_type'  => Spesa::class,
                'entryable_id'    => $spesa->id,
                'date'            => $data,
                'amount'          => -$importo, // uscita: importo negativo
                'description'     => "Prestito sociale – prelievo ({$nomeSocio}, libretto {$lib->numero_libretto})",
            ]);

            $mov = PrestitoSocialeMovimento::create([
                'libretto_id'        => $lib->id,
                'tipo'               => PrestitoSocialeMovimento::TIPO_PRELIEVO,
                'importo'            => $importo,
                'segno'              => PrestitoSocialeMovimento::SEGNO_DARE,
                'saldo_dopo'         => $nuovoSaldo,
                'data_valuta'        => $data,
                'data_registrazione' => Carbon::today(),
                'descrizione'        => $descrizione,
                'spesa_id'           => $spesa->id,
            ]);

            $lib->update(['saldo_attuale' => $nuovoSaldo]);

            return $mov;
        });
    }

    // ── d) Calcola interessi ──────────────────────────────────────────────────

    /**
     * Calcola e accredita gli interessi mensili maturati per tutti i libretti
     * attivi del tenant.
     *
     * Per ciascun libretto:
     *  1. Calcola il saldo medio del mese ponderato per giorni di valuta
     *  2. Interessi lordi = saldo_medio × tasso_annuo / 12
     *  3. Ritenuta = interessi × 26%
     *  4. Interessi netti = lordi − ritenuta
     *  5. Crea movimento 'interessi' (avere, importo lordo, saldo aumenta di netto)
     *  6. Crea movimento 'ritenuta_fiscale' (dare, importo ritenuta)
     *
     * Il saldo finale del libretto viene incrementato dell'importo NETTO
     * (lordo + deposito virtuale, ritenuta − deduzione virtuale).
     *
     * @return Collection di array con riepilogo per libretto:
     *   [libretto_id, numero_libretto, saldo_medio, interessi_lordi,
     *    ritenuta, interessi_netti, nuovo_saldo]
     *
     * @throws RuntimeException se anno/mese non validi
     */
    public function calcolaInteressi(int $anno, int $mese): Collection
    {
        if ($mese < 1 || $mese > 12) {
            throw new RuntimeException("Mese non valido: {$mese}.");
        }
        if ($anno < 2000 || $anno > 2100) {
            throw new RuntimeException("Anno non valido: {$anno}.");
        }

        $inizioMese = Carbon::create($anno, $mese, 1)->startOfDay();
        $fineMese   = $inizioMese->copy()->endOfMonth();
        $giorniMese = $inizioMese->daysInMonth;

        return DB::transaction(function () use ($anno, $mese, $inizioMese, $fineMese, $giorniMese) {
            $risultati = collect();

            $libretti = PrestitoSocialeLibretto::attivi()
                ->where('data_apertura', '<=', $fineMese)
                ->get();

            foreach ($libretti as $lib) {
                // Idempotenza: se esiste già un movimento interessi per lo stesso (anno,mese), salta
                $exists = PrestitoSocialeMovimento::where('libretto_id', $lib->id)
                    ->where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)
                    ->where('anno_competenza', $anno)
                    ->where('mese_competenza', $mese)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $saldoMedio = $this->calcolaSaldoMedioMensile($lib, $inizioMese, $fineMese, $giorniMese);
                $tasso      = (float) $lib->tasso_interesse_annuo;

                if ($saldoMedio <= 0 || $tasso <= 0) {
                    continue;
                }

                $interessiLordi = round($saldoMedio * $tasso / 12, 2);
                if ($interessiLordi <= 0) {
                    continue;
                }
                $ritenuta       = round($interessiLordi * self::ALIQUOTA_RITENUTA, 2);
                $interessiNetti = round($interessiLordi - $ritenuta, 2);

                $dataValuta = $fineMese->copy();

                // Saldo dopo interessi lordi (come fossero depositati)
                $saldoDopoLordi = round((float) $lib->saldo_attuale + $interessiLordi, 2);

                PrestitoSocialeMovimento::create([
                    'libretto_id'        => $lib->id,
                    'tipo'               => PrestitoSocialeMovimento::TIPO_INTERESSI,
                    'importo'            => $interessiLordi,
                    'segno'              => PrestitoSocialeMovimento::SEGNO_AVERE,
                    'saldo_dopo'         => $saldoDopoLordi,
                    'data_valuta'        => $dataValuta,
                    'data_registrazione' => Carbon::today(),
                    'anno_competenza'    => $anno,
                    'mese_competenza'    => $mese,
                    'aliquota_ritenuta'  => self::ALIQUOTA_RITENUTA,
                    'importo_ritenuta'   => $ritenuta,
                    'importo_netto'      => $interessiNetti,
                    'descrizione'        => sprintf('Interessi lordi %02d/%d (tasso %.2f%%, saldo medio €%.2f)', $mese, $anno, $tasso * 100, $saldoMedio),
                ]);

                // Saldo dopo detrazione della ritenuta
                $saldoDopoRitenuta = round($saldoDopoLordi - $ritenuta, 2);

                PrestitoSocialeMovimento::create([
                    'libretto_id'        => $lib->id,
                    'tipo'               => PrestitoSocialeMovimento::TIPO_RITENUTA_FISCALE,
                    'importo'            => $ritenuta,
                    'segno'              => PrestitoSocialeMovimento::SEGNO_DARE,
                    'saldo_dopo'         => $saldoDopoRitenuta,
                    'data_valuta'        => $dataValuta,
                    'data_registrazione' => Carbon::today(),
                    'anno_competenza'    => $anno,
                    'mese_competenza'    => $mese,
                    'aliquota_ritenuta'  => self::ALIQUOTA_RITENUTA,
                    'importo_ritenuta'   => $ritenuta,
                    'descrizione'        => sprintf('Ritenuta fiscale 26%% su interessi %02d/%d', $mese, $anno),
                ]);

                $lib->update(['saldo_attuale' => $saldoDopoRitenuta]);

                $risultati->push([
                    'libretto_id'     => $lib->id,
                    'numero_libretto' => $lib->numero_libretto,
                    'member_id'       => $lib->member_id,
                    'saldo_medio'     => $saldoMedio,
                    'interessi_lordi' => $interessiLordi,
                    'ritenuta'        => $ritenuta,
                    'interessi_netti' => $interessiNetti,
                    'nuovo_saldo'     => $saldoDopoRitenuta,
                ]);
            }

            return $risultati;
        });
    }

    /**
     * Calcola il saldo medio ponderato per giorni di valuta nel mese.
     *
     * Algoritmo: parte dal saldo all'inizio del mese, segue ogni movimento
     * e pondera il saldo per i giorni durante i quali è rimasto invariato.
     */
    private function calcolaSaldoMedioMensile(
        PrestitoSocialeLibretto $lib,
        Carbon $inizio,
        Carbon $fine,
        int $giorni,
    ): float {
        // Saldo all'inizio del mese = saldo al giorno prima di $inizio
        $saldoPrec = $lib->saldoAggiornatoAl($inizio->copy()->subDay());

        // Movimenti del mese ordinati per data
        $movimenti = $lib->movimenti()
            ->whereBetween('data_valuta', [$inizio->toDateString(), $fine->toDateString()])
            ->orderBy('data_valuta')
            ->orderBy('id')
            ->get(['data_valuta', 'saldo_dopo']);

        $somma       = 0.0;
        $cursore     = $inizio->copy();
        $saldoCurr   = $saldoPrec;

        foreach ($movimenti as $mov) {
            $dataMov = Carbon::parse($mov->data_valuta)->startOfDay();
            if ($dataMov->gt($fine)) {
                break;
            }
            // Giorni durante i quali il saldo precedente al movimento è rimasto invariato
            // Nota: Carbon 3 restituisce float da diffInDays(); il cast a int garantisce
            // che si contino solo giorni interi (comportamento coerente con Carbon 2).
            $giorniConSaldo = (int) $cursore->diffInDays($dataMov);
            if ($giorniConSaldo < 0) {
                $giorniConSaldo = 0;
            }
            $somma += $saldoCurr * $giorniConSaldo;

            // Avanza il cursore e aggiorna saldo
            $cursore   = $dataMov->copy();
            $saldoCurr = (float) $mov->saldo_dopo;
        }

        // Giorni rimanenti fino a fine mese (inclusa)
        $giorniFinali = (int) $cursore->diffInDays($fine) + 1;
        $somma += $saldoCurr * $giorniFinali;

        if ($giorni <= 0) {
            return 0.0;
        }

        return round($somma / $giorni, 2);
    }

    // ── e) Chiudi libretto ────────────────────────────────────────────────────

    /**
     * Chiude un libretto. Richiede saldo = 0.
     *
     * @throws RuntimeException se il saldo non è zero o il libretto già chiuso
     */
    public function chiudiLibretto(PrestitoSocialeLibretto $lib, Carbon $data): void
    {
        if ($lib->isChiuso()) {
            throw new RuntimeException("Il libretto {$lib->numero_libretto} è già chiuso.");
        }

        if (abs((float) $lib->saldo_attuale) > 0.01) {
            throw new RuntimeException(sprintf(
                'Impossibile chiudere il libretto %s: saldo residuo €%.2f. Effettuare prima un prelievo totale.',
                $lib->numero_libretto,
                (float) $lib->saldo_attuale,
            ));
        }

        $lib->update([
            'status'        => 'chiuso',
            'data_chiusura' => $data,
        ]);
    }

    // ── f) Estratto conto ─────────────────────────────────────────────────────

    /**
     * Genera l'estratto conto di un libretto per un periodo specifico.
     *
     * @return array{
     *   libretto: PrestitoSocialeLibretto,
     *   dal: Carbon,
     *   al: Carbon,
     *   saldo_iniziale: float,
     *   saldo_finale: float,
     *   movimenti: Collection<PrestitoSocialeMovimento>,
     *   totale_depositi: float,
     *   totale_prelievi: float,
     *   totale_interessi_lordi: float,
     *   totale_ritenute: float,
     * }
     */
    public function getEstrattoConto(
        PrestitoSocialeLibretto $lib,
        Carbon $dal,
        Carbon $al,
    ): array {
        if ($dal->gt($al)) {
            throw new RuntimeException('Il periodo dell\'estratto conto non è valido (dal > al).');
        }

        $saldoIniziale = $lib->saldoAggiornatoAl($dal->copy()->subDay());
        $saldoFinale   = $lib->saldoAggiornatoAl($al);

        $movimenti = $lib->movimenti()
            ->whereBetween('data_valuta', [$dal->toDateString(), $al->toDateString()])
            ->get();

        $totDepositi  = (float) $movimenti->where('tipo', PrestitoSocialeMovimento::TIPO_DEPOSITO)->sum('importo');
        $totPrelievi  = (float) $movimenti->where('tipo', PrestitoSocialeMovimento::TIPO_PRELIEVO)->sum('importo');
        $totInteressi = (float) $movimenti->where('tipo', PrestitoSocialeMovimento::TIPO_INTERESSI)->sum('importo');
        $totRitenute  = (float) $movimenti->where('tipo', PrestitoSocialeMovimento::TIPO_RITENUTA_FISCALE)->sum('importo');

        return [
            'libretto'               => $lib,
            'dal'                    => $dal,
            'al'                     => $al,
            'saldo_iniziale'         => $saldoIniziale,
            'saldo_finale'           => $saldoFinale,
            'movimenti'              => $movimenti,
            'totale_depositi'        => $totDepositi,
            'totale_prelievi'        => $totPrelievi,
            'totale_interessi_lordi' => $totInteressi,
            'totale_ritenute'        => $totRitenute,
        ];
    }
}
