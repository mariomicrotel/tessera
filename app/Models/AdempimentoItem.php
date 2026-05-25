<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Istanza concreta di un adempimento per (tenant, anno, periodo).
 *
 * NO BelongsToTenant: cross-tenant per il consulente. Le query filtrano
 * sempre per tenant_id esplicito.
 */
class AdempimentoItem extends Model
{
    use SoftDeletes;

    public const STATO_DA_FARE         = 'da_fare';
    public const STATO_IN_LAVORAZIONE  = 'in_lavorazione';
    public const STATO_CONSEGNATO      = 'consegnato';
    public const STATO_COMPLETATO      = 'completato';
    public const STATO_NON_APPLICABILE = 'non_applicabile';

    public const STATI = [
        self::STATO_DA_FARE, self::STATO_IN_LAVORAZIONE,
        self::STATO_CONSEGNATO, self::STATO_COMPLETATO, self::STATO_NON_APPLICABILE,
    ];

    protected $table = 'adempimenti_items';

    protected $fillable = [
        'tenant_id', 'template_id',
        'anno', 'periodo', 'data_scadenza',
        'stato', 'assegnato_a_user_id',
        'data_completamento', 'data_invio_telematico', 'protocollo_invio',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_scadenza'         => 'date',
            'data_completamento'    => 'datetime',
            'data_invio_telematico' => 'date',
            'anno'                  => 'integer',
        ];
    }

    /* ── Relazioni ──────────────────────────────────────────────────────── */

    public function tenant(): BelongsTo      { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function template(): BelongsTo    { return $this->belongsTo(AdempimentoTemplate::class, 'template_id'); }
    public function assegnatoA(): BelongsTo  { return $this->belongsTo(User::class, 'assegnato_a_user_id'); }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopePerAnno($q, int $anno)
    {
        return $q->where('anno', $anno);
    }

    public function scopePerTenant($q, string $tenantId)
    {
        return $q->where('tenant_id', $tenantId);
    }

    public function scopeAperti($q)
    {
        return $q->whereIn('stato', [self::STATO_DA_FARE, self::STATO_IN_LAVORAZIONE]);
    }

    public function scopeInScadenza($q, int $days = 7)
    {
        return $q->whereIn('stato', [self::STATO_DA_FARE, self::STATO_IN_LAVORAZIONE])
            ->whereBetween('data_scadenza', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeScaduti($q)
    {
        return $q->whereIn('stato', [self::STATO_DA_FARE, self::STATO_IN_LAVORAZIONE])
            ->where('data_scadenza', '<', now()->toDateString());
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    public function isCompletato(): bool
    {
        return in_array($this->stato, [self::STATO_COMPLETATO, self::STATO_NON_APPLICABILE], true);
    }

    public function isScaduto(): bool
    {
        return ! $this->isCompletato() && $this->data_scadenza?->isPast();
    }

    public function giorniAllaScadenza(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->data_scadenza, false);
    }

    public function statoLabel(): string
    {
        return [
            self::STATO_DA_FARE         => 'Da fare',
            self::STATO_IN_LAVORAZIONE  => 'In lavorazione',
            self::STATO_CONSEGNATO      => 'Consegnato',
            self::STATO_COMPLETATO      => 'Completato',
            self::STATO_NON_APPLICABILE => 'Non applicabile',
        ][$this->stato] ?? $this->stato;
    }

    public function statoBadgeColor(): string
    {
        if ($this->isScaduto()) {
            return 'red';
        }
        return [
            self::STATO_DA_FARE         => 'gray',
            self::STATO_IN_LAVORAZIONE  => 'blue',
            self::STATO_CONSEGNATO      => 'cyan',
            self::STATO_COMPLETATO      => 'green',
            self::STATO_NON_APPLICABILE => 'gray',
        ][$this->stato] ?? 'gray';
    }
}
