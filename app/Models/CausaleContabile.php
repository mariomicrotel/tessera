<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Causale contabile (modello di scrittura in partita doppia).
 *
 * Classifica il tipo di movimento e può suggerire automaticamente
 * il conto di contropartita più comune.
 *
 * Le causali `di_sistema = true` sono predefinite dal template cooperativa
 * e non eliminabili, ma disattivabili se non usate.
 */
class CausaleContabile extends Model
{
    use BelongsToTenant;

    // ── Tipi di causale ───────────────────────────────────────────────────
    public const TIPO_GENERICO          = 'generico';
    public const TIPO_FATTURA_ACQUISTO  = 'fattura_acquisto';
    public const TIPO_FATTURA_VENDITA   = 'fattura_vendita';
    public const TIPO_NOTA_CREDITO      = 'nota_credito';
    public const TIPO_INCASSO           = 'incasso';
    public const TIPO_PAGAMENTO         = 'pagamento';
    public const TIPO_GIROCONTO         = 'giroconto';
    public const TIPO_AMMORTAMENTO      = 'ammortamento';
    public const TIPO_STIPENDI          = 'stipendi';
    public const TIPO_APERTURA          = 'apertura';
    public const TIPO_CHIUSURA          = 'chiusura';

    public const TIPI = [
        self::TIPO_GENERICO,
        self::TIPO_FATTURA_ACQUISTO,
        self::TIPO_FATTURA_VENDITA,
        self::TIPO_NOTA_CREDITO,
        self::TIPO_INCASSO,
        self::TIPO_PAGAMENTO,
        self::TIPO_GIROCONTO,
        self::TIPO_AMMORTAMENTO,
        self::TIPO_STIPENDI,
        self::TIPO_APERTURA,
        self::TIPO_CHIUSURA,
    ];

    protected $table = 'causali_contabili';

    protected $fillable = [
        'tenant_id',
        'codice',
        'descrizione',
        'tipo',
        'conto_contropartita_default_id',
        'di_sistema',
        'attivo',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'di_sistema' => 'boolean',
            'attivo'     => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function contoContropartitaDefault(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_contropartita_default_id');
    }

    public function movimenti(): HasMany
    {
        return $this->hasMany(MovimentoContabile::class, 'causale_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeAttive(Builder $q): Builder
    {
        return $q->where('attivo', true);
    }

    public function scopeDiSistema(Builder $q): Builder
    {
        return $q->where('di_sistema', true);
    }

    public function scopeByTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $q) use ($term) {
            $q->where('codice', 'like', "%{$term}%")
              ->orWhere('descrizione', 'like', "%{$term}%");
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isAttiva(): bool
    {
        return (bool) $this->attivo;
    }

    public function isDiSistema(): bool
    {
        return (bool) $this->di_sistema;
    }

    /**
     * True se la causale è usata in almeno un movimento (non eliminabile).
     */
    public function isUsata(): bool
    {
        return $this->movimenti()->exists();
    }
}
