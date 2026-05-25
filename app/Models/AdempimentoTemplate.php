<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Template di adempimento fiscale/amministrativo.
 *
 * NO BelongsToTenant: i template sono globali, condivisi tra tenant.
 *
 * Periodicità + scadenza_kind compongono la regola di calcolo della data
 * di scadenza per ogni "occorrenza" (es. T1, T2, ...) nell'anno richiesto.
 */
class AdempimentoTemplate extends Model
{
    protected $table = 'adempimenti_templates';

    public const APPL_ETS         = 'ets';
    public const APPL_COOPERATIVA = 'cooperativa';
    public const APPL_ENTRAMBI    = 'entrambi';

    public const PERIODICITA_MENSILE     = 'mensile';
    public const PERIODICITA_TRIMESTRALE = 'trimestrale';
    public const PERIODICITA_SEMESTRALE  = 'semestrale';
    public const PERIODICITA_ANNUALE     = 'annuale';
    public const PERIODICITA_BIENNALE    = 'biennale';
    public const PERIODICITA_UNA_TANTUM  = 'una_tantum';

    public const KIND_FIXED_YEARLY            = 'fixed_yearly';
    public const KIND_FIXED_MONTHLY           = 'fixed_monthly';
    public const KIND_RELATIVE_TO_PERIOD_END  = 'relative_to_period_end';

    protected $fillable = [
        'codice', 'nome', 'descrizione', 'riferimento_normativo',
        'applicabile_a', 'categoria', 'periodicita',
        'scadenza_kind', 'scadenza_giorno', 'scadenza_mese',
        'scadenza_mesi_dopo_periodo', 'scadenza_anno_offset',
        'documenti_richiesti', 'priorita', 'attivo',
    ];

    protected function casts(): array
    {
        return [
            'documenti_richiesti'         => 'array',
            'attivo'                      => 'boolean',
            'scadenza_giorno'             => 'integer',
            'scadenza_mese'               => 'integer',
            'scadenza_mesi_dopo_periodo'  => 'integer',
            'scadenza_anno_offset'        => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(AdempimentoItem::class, 'template_id');
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeAttivi($q)
    {
        return $q->where('attivo', true);
    }

    public function scopeApplicabiliA($q, string $orgType)
    {
        // $orgType: 'ets' o 'cooperative'
        $appl = $orgType === 'cooperative' ? self::APPL_COOPERATIVA : self::APPL_ETS;
        return $q->whereIn('applicabile_a', [$appl, self::APPL_ENTRAMBI]);
    }

    /* ── Periodi nell'anno ──────────────────────────────────────────────── */

    /**
     * Restituisce l'array di "periodi" per cui generare item nell'anno.
     * Output esempi:
     *  - mensile:     ['01','02','03','04','05','06','07','08','09','10','11','12']
     *  - trimestrale: ['T1','T2','T3','T4']
     *  - semestrale:  ['S1','S2']
     *  - annuale:     [null]
     *  - una_tantum:  []  (nessuna ricorrenza automatica)
     *  - biennale:    [null] solo negli anni pari di una baseline (logica esterna)
     */
    public function periodiNellAnno(): array
    {
        return match ($this->periodicita) {
            self::PERIODICITA_MENSILE     => array_map(fn ($m) => sprintf('%02d', $m), range(1, 12)),
            self::PERIODICITA_TRIMESTRALE => ['T1', 'T2', 'T3', 'T4'],
            self::PERIODICITA_SEMESTRALE  => ['S1', 'S2'],
            self::PERIODICITA_ANNUALE,
            self::PERIODICITA_BIENNALE    => [null],
            self::PERIODICITA_UNA_TANTUM  => [],
            default                       => [],
        };
    }

    /**
     * Calcola la data di scadenza per (anno, periodo).
     *
     * @throws InvalidArgumentException  se la configurazione del template è inconsistente
     */
    public function calcolaScadenza(int $anno, ?string $periodo): Carbon
    {
        return match ($this->scadenza_kind) {
            self::KIND_FIXED_YEARLY            => $this->scadenzaFixedYearly($anno),
            self::KIND_FIXED_MONTHLY           => $this->scadenzaFixedMonthly($anno, $periodo),
            self::KIND_RELATIVE_TO_PERIOD_END  => $this->scadenzaRelativeToPeriodEnd($anno, $periodo),
            default => throw new InvalidArgumentException("scadenza_kind sconosciuta: {$this->scadenza_kind}"),
        };
    }

    private function scadenzaFixedYearly(int $anno): Carbon
    {
        if (! $this->scadenza_mese || ! $this->scadenza_giorno) {
            throw new InvalidArgumentException("Template {$this->codice}: fixed_yearly richiede scadenza_mese e scadenza_giorno");
        }
        $targetYear = $anno + (int) $this->scadenza_anno_offset;
        return Carbon::create($targetYear, $this->scadenza_mese, $this->scadenza_giorno);
    }

    private function scadenzaFixedMonthly(int $anno, ?string $periodo): Carbon
    {
        if (! $this->scadenza_giorno) {
            throw new InvalidArgumentException("Template {$this->codice}: fixed_monthly richiede scadenza_giorno");
        }
        // Periodo è il mese di competenza ('01'..'12'); scadenza al giorno X del mese successivo
        $meseCompetenza = $periodo ? (int) $periodo : 1;
        $scad = Carbon::create($anno, $meseCompetenza, 1)->addMonth();
        // Clampa il giorno se eccede (es. 31 in febbraio)
        $day = min($this->scadenza_giorno, $scad->daysInMonth);
        return $scad->setDay($day);
    }

    private function scadenzaRelativeToPeriodEnd(int $anno, ?string $periodo): Carbon
    {
        $months = (int) ($this->scadenza_mesi_dopo_periodo ?? 0);
        $end = $this->finePeriodo($anno, $periodo);
        $scad = $end->copy()->addMonths($months);

        // Se è specificato un giorno fisso, usalo (es. giorno 16 anziché ultimo del mese)
        if ($this->scadenza_giorno) {
            $day = min($this->scadenza_giorno, $scad->daysInMonth);
            $scad->setDay($day);
        } else {
            $scad->endOfMonth()->startOfDay();
        }

        return $scad;
    }

    /**
     * Carbon di fine periodo. Per trimestre T1 → 31 marzo, ecc.
     */
    private function finePeriodo(int $anno, ?string $periodo): Carbon
    {
        if ($periodo === null) {
            return Carbon::create($anno, 12, 31);
        }
        if (preg_match('/^T([1-4])$/', $periodo, $m)) {
            $trim = (int) $m[1];
            $meseFine = $trim * 3;
            return Carbon::create($anno, $meseFine, 1)->endOfMonth()->startOfDay();
        }
        if (preg_match('/^S([12])$/', $periodo, $m)) {
            $meseFine = ((int) $m[1]) * 6;
            return Carbon::create($anno, $meseFine, 1)->endOfMonth()->startOfDay();
        }
        if (preg_match('/^(0[1-9]|1[0-2])$/', $periodo)) {
            return Carbon::create($anno, (int) $periodo, 1)->endOfMonth()->startOfDay();
        }
        throw new InvalidArgumentException("Periodo sconosciuto: {$periodo}");
    }
}
