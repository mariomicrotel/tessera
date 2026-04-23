<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Anagrafica fornitore (ciclo passivo).
 *
 * Ogni fornitore appartiene a un tenant. I campi chiave per l'e-fattura
 * italiana sono: partita_iva, codice_fiscale, codice_sdi, pec.
 */
class Supplier extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    // ── Condizioni di pagamento ───────────────────────────────────────────
    public const PAGAMENTO_IMMEDIATO = 'immediato';
    public const PAGAMENTO_30GG      = '30gg';
    public const PAGAMENTO_60GG      = '60gg';
    public const PAGAMENTO_90GG      = '90gg';

    public const CONDIZIONI_PAGAMENTO = [
        self::PAGAMENTO_IMMEDIATO => 'Pagamento immediato',
        self::PAGAMENTO_30GG      => '30 giorni',
        self::PAGAMENTO_60GG      => '60 giorni',
        self::PAGAMENTO_90GG      => '90 giorni',
    ];

    // ── Categorie merceologiche ───────────────────────────────────────────
    public const CATEGORIA_BENI          = 'beni';
    public const CATEGORIA_SERVIZI       = 'servizi';
    public const CATEGORIA_PROFESSIONISTA = 'professionista';
    public const CATEGORIA_ALTRO         = 'altro';

    public const CATEGORIE = [
        self::CATEGORIA_BENI           => 'Beni / merci',
        self::CATEGORIA_SERVIZI        => 'Servizi',
        self::CATEGORIA_PROFESSIONISTA => 'Professionista',
        self::CATEGORIA_ALTRO          => 'Altro',
    ];

    protected $table = 'suppliers';

    protected $fillable = [
        'tenant_id',
        'name',
        'ragione_sociale',
        'email',
        'phone',
        'partita_iva',
        'codice_fiscale',
        'codice_sdi',
        'pec',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'nazione',
        'iban',
        'condizioni_pagamento',
        'categoria',
        'note',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function fatturePassive(): HasMany
    {
        return $this->hasMany(FatturaPassiva::class, 'supplier_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeAttivi(Builder $q): Builder
    {
        return $q->where('attivo', true);
    }

    public function scopeByCategoria(Builder $q, string $cat): Builder
    {
        return $q->where('categoria', $cat);
    }

    /**
     * Ricerca per nome commerciale, ragione sociale, P.IVA, città.
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('ragione_sociale', 'like', "%{$term}%")
              ->orWhere('partita_iva', 'like', "%{$term}%")
              ->orWhere('citta', 'like', "%{$term}%");
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Attributi / Accessors
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Nome da mostrare in UI: ragione_sociale se presente, altrimenti name.
     */
    public function getNomeCompletoAttribute(): string
    {
        return $this->ragione_sociale ?: $this->name;
    }

    /**
     * Indirizzo completo formattato su una riga.
     * Es: "Via Roma 1, 20100 Milano MI (IT)"
     */
    public function getIndirizzoCompletoAttribute(): string
    {
        $parti = array_filter([
            $this->indirizzo,
            $this->cap && $this->citta
                ? "{$this->cap} {$this->citta}" . ($this->provincia ? " {$this->provincia}" : '')
                : $this->citta,
            $this->nazione && $this->nazione !== 'IT' ? "({$this->nazione})" : null,
        ]);

        return implode(', ', $parti);
    }

    public function isAttivo(): bool
    {
        return (bool) $this->attivo;
    }
}
