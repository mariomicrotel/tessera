<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rateo o Risconto contabile.
 *
 * Rappresenta una scrittura di rettifica infrannuale per competenza economica:
 *  - rateo_attivo:     ricavo maturato non ancora incassato      → DARE ratei attivi / AVERE ricavo
 *  - rateo_passivo:    costo maturato non ancora pagato          → DARE costo / AVERE ratei passivi
 *  - risconto_attivo:  costo pagato di competenza futura         → DARE risconti attivi / AVERE costo
 *  - risconto_passivo: ricavo incassato di competenza futura     → DARE ricavo / AVERE risconti passivi
 *
 * @property int         $id
 * @property string      $tenant_id
 * @property int         $anno_esercizio
 * @property string      $tipo
 * @property string      $descrizione
 * @property float       $importo_totale
 * @property float       $quota_esercizio
 * @property \Carbon\Carbon $data_inizio
 * @property \Carbon\Carbon $data_fine
 * @property int         $conto_economico_id
 * @property int         $conto_rettifica_id
 * @property string      $stato
 * @property int|null    $movimento_id
 * @property int|null    $storno_id
 * @property string|null $note
 */
class RateoRisconto extends Model
{
    use BelongsToTenant;

    protected $table = 'ratei_risconti';

    // ── Costanti tipo ─────────────────────────────────────────────────────
    public const TIPO_RATEO_ATTIVO      = 'rateo_attivo';
    public const TIPO_RATEO_PASSIVO     = 'rateo_passivo';
    public const TIPO_RISCONTO_ATTIVO   = 'risconto_attivo';
    public const TIPO_RISCONTO_PASSIVO  = 'risconto_passivo';

    public const TIPI = [
        self::TIPO_RATEO_ATTIVO,
        self::TIPO_RATEO_PASSIVO,
        self::TIPO_RISCONTO_ATTIVO,
        self::TIPO_RISCONTO_PASSIVO,
    ];

    // ── Costanti stato ────────────────────────────────────────────────────
    public const STATO_DA_REGISTRARE = 'da_registrare';
    public const STATO_REGISTRATO    = 'registrato';
    public const STATO_STORNATO      = 'stornato';

    protected $fillable = [
        'tenant_id',
        'anno_esercizio',
        'tipo',
        'descrizione',
        'importo_totale',
        'quota_esercizio',
        'data_inizio',
        'data_fine',
        'conto_economico_id',
        'conto_rettifica_id',
        'stato',
        'movimento_id',
        'storno_id',
        'note',
    ];

    protected $casts = [
        'anno_esercizio'  => 'integer',
        'importo_totale'  => 'float',
        'quota_esercizio' => 'float',
        'data_inizio'     => 'date',
        'data_fine'       => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function contoEconomico(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_economico_id');
    }

    public function contoRettifica(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_rettifica_id');
    }

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_id');
    }

    public function storno(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'storno_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isDaRegistrare(): bool
    {
        return $this->stato === self::STATO_DA_REGISTRARE;
    }

    public function isRegistrato(): bool
    {
        return $this->stato === self::STATO_REGISTRATO;
    }

    public function isStornato(): bool
    {
        return $this->stato === self::STATO_STORNATO;
    }

    public function isRateo(): bool
    {
        return in_array($this->tipo, [self::TIPO_RATEO_ATTIVO, self::TIPO_RATEO_PASSIVO], true);
    }

    public function isRisconto(): bool
    {
        return in_array($this->tipo, [self::TIPO_RISCONTO_ATTIVO, self::TIPO_RISCONTO_PASSIVO], true);
    }

    public function isAttivo(): bool
    {
        return in_array($this->tipo, [self::TIPO_RATEO_ATTIVO, self::TIPO_RISCONTO_ATTIVO], true);
    }

    /** Etichetta leggibile del tipo. */
    public function labelTipo(): string
    {
        return match ($this->tipo) {
            self::TIPO_RATEO_ATTIVO     => 'Rateo attivo',
            self::TIPO_RATEO_PASSIVO    => 'Rateo passivo',
            self::TIPO_RISCONTO_ATTIVO  => 'Risconto attivo',
            self::TIPO_RISCONTO_PASSIVO => 'Risconto passivo',
            default                      => $this->tipo,
        };
    }

    /**
     * Calcola la quota proporzionale di competenza dell'esercizio specificato
     * basandosi sui giorni.
     *
     * @param int $anno  Anno di cui calcolare la quota (default: anno_esercizio)
     * @return float     Importo da registrare
     */
    public function calcolaQuota(?int $anno = null): float
    {
        $anno ??= $this->anno_esercizio;

        $inizioAnno = \Carbon\Carbon::create($anno, 1, 1);
        $fineAnno   = \Carbon\Carbon::create($anno, 12, 31);

        $inizioComp = $this->data_inizio->copy()->max($inizioAnno);
        $fineComp   = $this->data_fine->copy()->min($fineAnno);

        if ($inizioComp->gt($fineComp)) {
            return 0.0;
        }

        $giorniTotali = $this->data_inizio->diffInDays($this->data_fine) + 1;
        $giorniAnno   = $inizioComp->diffInDays($fineComp) + 1;

        if ($giorniTotali <= 0) {
            return $this->importo_totale;
        }

        return round($this->importo_totale * $giorniAnno / $giorniTotali, 2);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePerAnno($query, int $anno)
    {
        return $query->where('anno_esercizio', $anno);
    }

    public function scopeDaRegistrare($query)
    {
        return $query->where('stato', self::STATO_DA_REGISTRARE);
    }

    public function scopeRegistrati($query)
    {
        return $query->where('stato', self::STATO_REGISTRATO);
    }
}
