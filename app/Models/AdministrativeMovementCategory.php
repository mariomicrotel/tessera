<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria libera per i movimenti amministrativi semplificati.
 *
 * Ogni tenant può creare le proprie categorie (es. "Quote associative",
 * "Affitto sede", "Utenze", "Rimborsi spese") senza configurare
 * un piano dei conti.
 */
class AdministrativeMovementCategory extends Model
{
    use BelongsToTenant;

    protected $table = 'administrative_movement_categories';

    protected $fillable = [
        'tenant_id',
        'nome',
        'tipo',
        'colore',
        'icona',
        'ordine',
        'attiva',
    ];

    protected $casts = [
        'attiva' => 'boolean',
        'ordine' => 'integer',
    ];

    /* ── Relazioni ─────────────────────────────────────────────────────── */

    public function movimenti(): HasMany
    {
        return $this->hasMany(AdministrativeMovement::class, 'category_id');
    }

    /* ── Scope ─────────────────────────────────────────────────────────── */

    public function scopeAttive($query)
    {
        return $query->where('attiva', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordine')->orderBy('nome');
    }

    /**
     * Categorie compatibili con un tipo di movimento.
     */
    public function scopeCompatibili($query, string $tipo)
    {
        return $query->where(function ($q) use ($tipo) {
            $q->where('tipo', $tipo)->orWhere('tipo', 'qualsiasi');
        });
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            'entrata'  => 'Entrata',
            'uscita'   => 'Uscita',
            'qualsiasi' => 'Entrata/Uscita',
            default    => $this->tipo,
        };
    }
}
