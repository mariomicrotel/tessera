<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'plan',
        'plan_expires_at',
        'is_active',
        'settings',
        'organization_type',
        'cooperative_type',
        'codice_fiscale',
        'partita_iva',
        'numero_iscrizione_albo_coop',
        'capitale_sottoscritto',
        'capitale_versato',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'plan_expires_at' => 'datetime',
        'capitale_sottoscritto' => 'decimal:2',
        'capitale_versato' => 'decimal:2',
    ];

    /**
     * Verifica se il tenant è una cooperativa.
     */
    public function isCooperativa(): bool
    {
        return $this->organization_type === 'cooperative';
    }

    /**
     * Verifica se il tenant è un ETS (Ente del Terzo Settore).
     */
    public function isETS(): bool
    {
        return $this->organization_type === 'ets' || $this->organization_type === null;
    }

    /**
     * Restituisce l'etichetta leggibile del tipo di cooperativa.
     */
    public function cooperativeTypeLabel(): string
    {
        return match ($this->cooperative_type) {
            'lavoro'     => 'Cooperativa di Lavoro',
            'sociale_a'  => 'Cooperativa Sociale (Tipo A)',
            'sociale_b'  => 'Cooperativa Sociale (Tipo B)',
            'agricola'   => 'Cooperativa Agricola',
            'comunita'   => 'Cooperativa di Comunità',
            'consumo'    => 'Cooperativa di Consumo',
            'abitazione' => 'Cooperativa di Abitazione',
            'consortile' => 'Cooperativa Consortile',
            default      => 'Cooperativa',
        };
    }

    /**
     * Utenti appartenenti a questo tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Verifica se il piano è ancora attivo (non scaduto).
     */
    public function isPlanActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->plan === 'free') {
            return true;
        }

        return $this->plan_expires_at === null || $this->plan_expires_at->isFuture();
    }

    /**
     * Restituisce il limite soci per il piano corrente.
     */
    public function memberLimit(): int
    {
        return match ($this->plan) {
            'free' => 25,
            'basic' => 100,
            'pro' => 500,
            'enterprise' => PHP_INT_MAX,
            default => 25,
        };
    }

    /**
     * Restituisce il limite utenti staff per il piano corrente.
     */
    public function staffLimit(): int
    {
        return match ($this->plan) {
            'free' => 1,
            'basic' => 3,
            'pro' => 10,
            'enterprise' => PHP_INT_MAX,
            default => 1,
        };
    }

    /**
     * Restituisce il limite storage in MB.
     */
    public function storageLimitMb(): int
    {
        return match ($this->plan) {
            'free' => 100,
            'basic' => 1024,
            'pro' => 10240,
            'enterprise' => 51200,
            default => 100,
        };
    }

    /**
     * Resolve route binding tramite slug.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
