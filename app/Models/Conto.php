<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conto extends Model
{
    use BelongsToTenant;

    protected $table = 'conti';

    protected $fillable = [
        'name',
        'code',
        'type',
        'iban',
        'ordine',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function movimenti(): HasMany
    {
        return $this->hasMany(PrimaNotaEntry::class, 'conto_id');
    }

    public function incassi(): HasMany
    {
        return $this->hasMany(Incasso::class, 'conto_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordine')->orderBy('name');
    }

    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }

    /**
     * Saldo totale del conto (somma di tutti i movimenti in prima nota).
     */
    public function saldo(): float
    {
        return round((float) $this->movimenti()->sum('amount'), 2);
    }

    /**
     * Saldo del conto per un anno specifico.
     */
    public function saldoAnno(int $anno): float
    {
        return round((float) $this->movimenti()->whereYear('date', $anno)->sum('amount'), 2);
    }
}
