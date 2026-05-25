<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Consegna del consulente all'ente: documento che il commercialista carica
 * proattivamente per il tenant (es. F24 da pagare, bilancio firmato).
 *
 * Cross-tenant: NO BelongsToTenant — analogo a ConsultantRequest.
 * Le query devono sempre filtrare per consultant_user_id o tenant_id.
 *
 * Lifecycle: bozza → consegnato → letto → (accettato | contestato)
 */
class ConsultantDelivery extends Model
{
    use HasUuids, SoftDeletes;

    public const STATO_BOZZA       = 'bozza';
    public const STATO_CONSEGNATO  = 'consegnato';
    public const STATO_LETTO       = 'letto';
    public const STATO_ACCETTATO   = 'accettato';
    public const STATO_CONTESTATO  = 'contestato';

    public const STATI = [
        self::STATO_BOZZA, self::STATO_CONSEGNATO, self::STATO_LETTO,
        self::STATO_ACCETTATO, self::STATO_CONTESTATO,
    ];

    public const TIPO_DOC_FISCALE    = 'documento_fiscale';
    public const TIPO_F24            = 'f24';
    public const TIPO_BILANCIO       = 'bilancio';
    public const TIPO_COMUNICAZIONE  = 'comunicazione';
    public const TIPO_ALTRO          = 'altro';

    public const TIPI = [
        self::TIPO_DOC_FISCALE, self::TIPO_F24, self::TIPO_BILANCIO,
        self::TIPO_COMUNICAZIONE, self::TIPO_ALTRO,
    ];

    protected $table = 'consultant_deliveries';

    protected $fillable = [
        'tenant_id',
        'consultant_user_id',
        'titolo',
        'descrizione',
        'tipo',
        'stato',
        'data_consegna',
        'data_lettura',
        'data_feedback',
        'feedback_note',
    ];

    protected function casts(): array
    {
        return [
            'data_consegna'  => 'datetime',
            'data_lettura'   => 'datetime',
            'data_feedback'  => 'datetime',
        ];
    }

    /* ── Relazioni ──────────────────────────────────────────────────────── */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_user_id');
    }

    public function documenti(): HasMany
    {
        return $this->hasMany(ConsultantDeliveryDocument::class, 'delivery_id');
    }

    /* ── Stati ──────────────────────────────────────────────────────────── */

    public function isBozza(): bool      { return $this->stato === self::STATO_BOZZA; }
    public function isConsegnato(): bool { return $this->stato === self::STATO_CONSEGNATO; }
    public function isLetto(): bool      { return $this->stato === self::STATO_LETTO; }
    public function isAccettato(): bool  { return $this->stato === self::STATO_ACCETTATO; }
    public function isContestato(): bool { return $this->stato === self::STATO_CONTESTATO; }

    /** È visibile al tenant? (non più bozza) */
    public function isVisibleToTenant(): bool
    {
        return $this->stato !== self::STATO_BOZZA;
    }

    /* ── Helpers UI ─────────────────────────────────────────────────────── */

    public function statoLabel(): string
    {
        return [
            self::STATO_BOZZA       => 'Bozza',
            self::STATO_CONSEGNATO  => 'Consegnato',
            self::STATO_LETTO       => 'Letto',
            self::STATO_ACCETTATO   => 'Accettato',
            self::STATO_CONTESTATO  => 'Contestato',
        ][$this->stato] ?? $this->stato;
    }

    public function statoBadgeColor(): string
    {
        return [
            self::STATO_BOZZA       => 'gray',
            self::STATO_CONSEGNATO  => 'blue',
            self::STATO_LETTO       => 'cyan',
            self::STATO_ACCETTATO   => 'green',
            self::STATO_CONTESTATO  => 'red',
        ][$this->stato] ?? 'gray';
    }

    public function tipoLabel(): string
    {
        return [
            self::TIPO_DOC_FISCALE    => 'Documento fiscale',
            self::TIPO_F24            => 'F24',
            self::TIPO_BILANCIO       => 'Bilancio',
            self::TIPO_COMUNICAZIONE  => 'Comunicazione',
            self::TIPO_ALTRO          => 'Altro',
        ][$this->tipo] ?? $this->tipo;
    }

    /* ── Scopes ─────────────────────────────────────────────────────────── */

    public function scopeVisibiliAlTenant($query)
    {
        return $query->where('stato', '!=', self::STATO_BOZZA);
    }

    public function scopeNonLette($query)
    {
        return $query->where('stato', self::STATO_CONSEGNATO);
    }

    /* ── Transitions ────────────────────────────────────────────────────── */

    public function consegna(): void
    {
        $this->stato         = self::STATO_CONSEGNATO;
        $this->data_consegna = now();
        $this->save();
    }

    public function markAsRead(): void
    {
        if ($this->stato === self::STATO_CONSEGNATO) {
            $this->stato       = self::STATO_LETTO;
            $this->data_lettura = now();
            $this->save();
        }
    }

    public function accetta(?string $note = null): void
    {
        $this->stato         = self::STATO_ACCETTATO;
        $this->data_feedback = now();
        if ($note !== null) {
            $this->feedback_note = $note;
        }
        $this->save();
    }

    public function contesta(string $note): void
    {
        $this->stato         = self::STATO_CONTESTATO;
        $this->data_feedback = now();
        $this->feedback_note = $note;
        $this->save();
    }
}
