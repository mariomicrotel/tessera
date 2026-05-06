<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scadenza extends Model
{
    use BelongsToTenant;

    protected $table = 'scadenze';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'descrizione',
        'importo',
        'data_scadenza',
        'data_pagamento',
        'stato',
        'riferimento',
        'conto_id',
        'ricorrente',
        'origine_anno_precedente',
        'note',
    ];

    protected $appends = ['is_scaduta', 'giorni_alla_scadenza'];

    protected function casts(): array
    {
        return [
            'data_scadenza'            => 'date',
            'data_pagamento'           => 'date',
            'origine_anno_precedente'  => 'date',
            'importo'                  => 'decimal:2',
            'ricorrente'               => 'boolean',
        ];
    }

    // ─── Costanti di stato ───────────────────────────────────────────────────

    public const STATO_APERTA   = 'aperta';
    public const STATO_PAGATA   = 'pagata';
    public const STATO_SOSPESA  = 'sospesa';

    public const STATI = [
        self::STATO_APERTA  => 'Aperta',
        self::STATO_PAGATA  => 'Pagata',
        self::STATO_SOSPESA => 'Sospesa',
    ];

    // ─── Tipi ────────────────────────────────────────────────────────────────

    public const TIPI = [
        'fiscale'       => 'Fiscale (IVA/IRPEF/IRAP)',
        'contributi'    => 'Contributi previdenziali',
        'affitto'       => 'Canone affitto',
        'assicurazione' => 'Assicurazione',
        'noleggio'      => 'Noleggio',
        'abbonamento'   => 'Abbonamento',
        'altra'         => 'Altra scadenza',
    ];

    // ─── Relazioni ───────────────────────────────────────────────────────────

    public function conto(): BelongsTo
    {
        return $this->belongsTo(Conto::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAperte(Builder $q): Builder
    {
        return $q->where('stato', self::STATO_APERTA);
    }

    public function scopeScadute(Builder $q): Builder
    {
        return $q->where('stato', self::STATO_APERTA)
                 ->where('data_scadenza', '<', now()->toDateString());
    }

    public function scopeInScadenza(Builder $q, int $giorni = 30): Builder
    {
        return $q->where('stato', self::STATO_APERTA)
                 ->whereBetween('data_scadenza', [
                     now()->toDateString(),
                     now()->addDays($giorni)->toDateString(),
                 ]);
    }

    public function scopePerTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo);
    }

    public function scopePerPeriodo(Builder $q, ?string $dal, ?string $al): Builder
    {
        if ($dal) {
            $q->where('data_scadenza', '>=', $dal);
        }
        if ($al) {
            $q->where('data_scadenza', '<=', $al);
        }
        return $q;
    }

    // ─── Accessor ─────────────────────────────────────────────────────────────

    public function getIsScadutaAttribute(): bool
    {
        return $this->stato === self::STATO_APERTA
            && $this->data_scadenza->isPast();
    }

    public function getGiorniAllaScadenzaAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->data_scadenza->startOfDay(), false);
    }
}
