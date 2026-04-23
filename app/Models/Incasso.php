<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Incasso (quota associativa o donazione). Unica entità per tutti gli incassi.
 */
class Incasso extends Model
{
    use BelongsToTenant;

    public const TYPE_QUOTA     = 'quota';
    public const TYPE_DONAZIONE = 'donazione';
    public const TYPE_ALTRO     = 'altro';
    /** Versamento quote di capitale sociale (solo cooperative) */
    public const TYPE_CAPITALE  = 'capitale';
    /** Deposito su libretto di prestito sociale (solo cooperative) */
    public const TYPE_PRESTITO_SOCIALE = 'prestito_sociale';

    protected $table = 'incassi';

    protected $fillable = [
        'member_id',
        'donor_name',
        'subscription_id',
        'amount',
        'paid_at',
        'conto_id',
        'description',
        'receipt_text_override',
        'receipt_issued_at',
        'genera_prima_nota',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'receipt_issued_at' => 'datetime',
            'amount' => 'decimal:2',
            'genera_prima_nota' => 'boolean',
            'type' => 'string',
        ];
    }

    public function scopeQuota($query)
    {
        return $query->where('type', self::TYPE_QUOTA);
    }

    public function scopeDonazione($query)
    {
        return $query->where('type', self::TYPE_DONAZIONE);
    }

    public function scopeCapitale($query)
    {
        return $query->where('type', self::TYPE_CAPITALE);
    }

    public function scopePrestitoSociale($query)
    {
        return $query->where('type', self::TYPE_PRESTITO_SOCIALE);
    }

    public function primaNotaEntry(): MorphOne
    {
        return $this->morphOne(PrimaNotaEntry::class, 'entryable');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function conto(): BelongsTo
    {
        return $this->belongsTo(Conto::class);
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Receipt::class, 'receivable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('created_at');
    }
}
