<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conto del piano dei conti contabili (partita doppia).
 *
 * Gerarchia a 4 livelli:
 *   Classe → Mastro → Conto → Sottoconto
 *
 * Solo i sottoconti (livello 4, movimentabile=true) sono ammessi nelle
 * righe di MovimentoContabile. I livelli superiori aggregano per reporting.
 *
 * I conti `di_sistema=true` provengono dal template e non sono eliminabili;
 * possono essere disattivati (attivo=false) se non usati, solo se non hanno
 * scritture nell'esercizio corrente (enforcement via service).
 */
class ContoContabile extends Model
{
    use BelongsToTenant;

    // ── Natura del conto ──────────────────────────────────────────────────
    public const NATURA_ATTIVO            = 'attivo';
    public const NATURA_PASSIVO           = 'passivo';
    public const NATURA_PATRIMONIO_NETTO  = 'patrimonio_netto';
    public const NATURA_COSTO             = 'costo';
    public const NATURA_RICAVO            = 'ricavo';
    public const NATURA_CONTO_ORDINE      = 'conto_ordine';
    public const NATURA_TRANSITORIO       = 'transitorio';

    // ── Segno naturale ────────────────────────────────────────────────────
    public const SEGNO_DARE  = 'dare';
    public const SEGNO_AVERE = 'avere';

    // ── Tipo bilancio (classificazione CEE) ───────────────────────────────
    public const TIPO_SP_ATTIVO                    = 'sp_attivo';
    public const TIPO_SP_PASSIVO                   = 'sp_passivo';
    public const TIPO_PN                           = 'pn';
    public const TIPO_CE_VALORE_PRODUZIONE         = 'ce_valore_produzione';
    public const TIPO_CE_COSTI_PRODUZIONE          = 'ce_costi_produzione';
    public const TIPO_CE_PROVENTI_ONERI_FINANZIARI = 'ce_proventi_oneri_finanziari';
    public const TIPO_CE_RETTIFICHE_FINANZIARIE    = 'ce_rettifiche_finanziarie';
    public const TIPO_CE_IMPOSTE                   = 'ce_imposte';
    public const TIPO_CONTO_ORDINE                 = 'conto_ordine';
    public const TIPO_TRANSITORIO                  = 'transitorio';

    // ── Gestione (cooperative) ────────────────────────────────────────────
    public const GESTIONE_ISTITUZIONALE = 'istituzionale';
    public const GESTIONE_COMMERCIALE   = 'commerciale';

    protected $table = 'conti_contabili';

    protected $fillable = [
        'tenant_id',
        'codice',
        'descrizione',
        'parent_id',
        'livello',
        'natura',
        'segno_naturale',
        'classe_bilancio_ce',
        'tipo_bilancio',
        'movimentabile',
        'di_sistema',
        'attivo',
        'gestione_default',
        'codice_iva_default_id',
        'rendiconto_code_map',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'livello'        => 'integer',
            'movimentabile'  => 'boolean',
            'di_sistema'     => 'boolean',
            'attivo'         => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('codice');
    }

    public function codiceIvaDefault(): BelongsTo
    {
        return $this->belongsTo(CodiceIva::class, 'codice_iva_default_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeAttivi(Builder $q): Builder
    {
        return $q->where('attivo', true);
    }

    public function scopeMovimentabili(Builder $q): Builder
    {
        return $q->where('movimentabile', true);
    }

    public function scopeDiSistema(Builder $q): Builder
    {
        return $q->where('di_sistema', true);
    }

    public function scopeByNatura(Builder $q, string $natura): Builder
    {
        return $q->where('natura', $natura);
    }

    public function scopeByLivello(Builder $q, int $livello): Builder
    {
        return $q->where('livello', $livello);
    }

    public function scopeDiStatoPatrimoniale(Builder $q): Builder
    {
        return $q->whereIn('natura', [
            self::NATURA_ATTIVO,
            self::NATURA_PASSIVO,
            self::NATURA_PATRIMONIO_NETTO,
        ]);
    }

    public function scopeDiContoEconomico(Builder $q): Builder
    {
        return $q->whereIn('natura', [
            self::NATURA_COSTO,
            self::NATURA_RICAVO,
        ]);
    }

    /**
     * Ricerca per descrizione o codice (LIKE).
     */
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

    /**
     * Scope: conti discendenti (per prefisso codice).
     * Esempio: scopeDiscendenti('1.10') → tutti i conti sotto "Immobilizzazioni immateriali".
     */
    public function scopeDiscendenti(Builder $q, string $codicePadre): Builder
    {
        return $q->where('codice', 'like', $codicePadre.'.%');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function isMovimentabile(): bool
    {
        return (bool) $this->movimentabile;
    }

    public function isDiSistema(): bool
    {
        return (bool) $this->di_sistema;
    }

    public function isAttivo(): bool
    {
        return (bool) $this->attivo;
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * True se il segno naturale è Dare (attivi, costi).
     */
    public function isDare(): bool
    {
        return $this->segno_naturale === self::SEGNO_DARE;
    }

    /**
     * True se il segno naturale è Avere (passivi, PN, ricavi).
     */
    public function isAvere(): bool
    {
        return $this->segno_naturale === self::SEGNO_AVERE;
    }

    /**
     * Natura di Stato Patrimoniale.
     */
    public function isStatoPatrimoniale(): bool
    {
        return in_array($this->natura, [
            self::NATURA_ATTIVO,
            self::NATURA_PASSIVO,
            self::NATURA_PATRIMONIO_NETTO,
        ], true);
    }

    /**
     * Natura di Conto Economico.
     */
    public function isContoEconomico(): bool
    {
        return in_array($this->natura, [
            self::NATURA_COSTO,
            self::NATURA_RICAVO,
        ], true);
    }

    /**
     * Deriva il livello (1-4) dal codice decimale.
     * Esempio: "1.10.05.001" → 4, "1.10" → 2.
     */
    public static function calcolaLivelloDaCodice(string $codice): int
    {
        return substr_count($codice, '.') + 1;
    }

    /**
     * Deriva il codice padre da un codice gerarchico.
     * Esempio: "1.10.05.001" → "1.10.05", "1" → null.
     */
    public static function calcolaCodicePadre(string $codice): ?string
    {
        $pos = strrpos($codice, '.');
        return $pos === false ? null : substr($codice, 0, $pos);
    }
}
