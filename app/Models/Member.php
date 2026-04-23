<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anagrafica socio, volontario o collaboratore. user_id opzionale per area self-service.
 */
class Member extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sostituito_da_member_id',
        'member_type_id',
        'numero_tessera',
        'nome',
        'cognome',
        'email',
        'codice_fiscale',
        'data_nascita',
        'data_iscrizione',
        'stato',
        'indirizzo',
        'telefono',
        'note',
        'domanda_presentata_at',
        'ammissione_decisa_at',
        'ammissione_esito',
        'rigetto_motivo',
        'rigetto_comunicato_at',
        'ricorso_presentato_at',
        'assemblea_esame_data',
        'data_cessazione',
        'cessazione_causa',
        'dimissioni_presentate_at',
        'motivo_esclusione',
        'deceduto_at',
        'tipo_persona',
        'ragione_sociale',
        'partita_iva',
        'referente_nome',
        'referente_cognome',
        'socio_lavoratore',
        'socio_sovventore',
        'socio_onorario',
        'data_ammissione_cda',
        'numero_quote_capitale',
    ];

    /** Salva il codice fiscale sempre in maiuscolo. */
    protected function setCodiceFiscaleAttribute($value): void
    {
        $this->attributes['codice_fiscale'] = ($value !== null && trim((string) $value) !== '')
            ? strtoupper(trim((string) $value))
            : null;
    }

    protected function casts(): array
    {
        return [
            'numero_tessera' => 'integer',
            'data_nascita' => 'date',
            'data_iscrizione' => 'date',
            'domanda_presentata_at' => 'date',
            'ammissione_decisa_at' => 'date',
            'rigetto_comunicato_at' => 'date',
            'ricorso_presentato_at' => 'date',
            'assemblea_esame_data' => 'date',
            'data_cessazione' => 'date',
            'dimissioni_presentate_at' => 'date',
            'deceduto_at' => 'date',
            'data_ammissione_cda' => 'date',
            'numero_quote_capitale' => 'integer',
            'socio_lavoratore' => 'boolean',
            'socio_sovventore' => 'boolean',
            'socio_onorario' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memberType(): BelongsTo
    {
        return $this->belongsTo(MemberType::class, 'member_type_id');
    }


    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'member_id');
    }

    public function incassi(): HasMany
    {
        return $this->hasMany(Incasso::class, 'member_id');
    }

    public function expenseRefunds(): HasMany
    {
        return $this->hasMany(ExpenseRefund::class, 'member_id');
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'member_id');
    }

    public function incarichi(): HasMany
    {
        return $this->hasMany(Incarico::class, 'member_id');
    }

    public function cooperativeShares(): HasMany
    {
        return $this->hasMany(CooperativeShare::class, 'member_id');
    }

    /** Nome completo */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->nome} {$this->cognome}");
    }

    /** Verifica se è una persona fisica */
    public function isPersonaFisica(): bool
    {
        return $this->tipo_persona === 'fisica' || $this->tipo_persona === null;
    }

    /** Verifica se è una persona giuridica */
    public function isPersonaGiuridica(): bool
    {
        return $this->tipo_persona === 'giuridica';
    }

    /** Nome visualizzabile (cognome + nome per fisica, ragione_sociale per giuridica) */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isPersonaGiuridica()) {
            return $this->ragione_sociale ?? 'Azienda senza nome';
        }
        return $this->getFullNameAttribute();
    }

    /** Nome completo per UI: "Cognome Nome" (fisica) o "Ragione Sociale (Ref.)" (giuridica) */
    public function nomeCompleto(): string
    {
        if ($this->isPersonaGiuridica()) {
            $label = $this->ragione_sociale ?? 'Azienda';
            if ($this->referente_nome || $this->referente_cognome) {
                $ref = trim("{$this->referente_nome} {$this->referente_cognome}");
                return "{$label} ({$ref})";
            }
            return $label;
        }
        return $this->getFullNameAttribute();
    }

    /** Scope: filtra solo soci lavoratori */
    public function scopeLavoratori(Builder $query): Builder
    {
        return $query->where('socio_lavoratore', true);
    }

    /** Verifica se il socio è in regola con la quota sociale (basato sui pagamenti). */
    public function isInRegolaConQuota(): bool
    {
        $currentYear = now()->year;
        return $this->incassi()
            ->whereYear('paid_at', $currentYear)
            ->where('type', 'quota')
            ->exists();
    }

    /** Scope: soci in regola con la quota (basato sui pagamenti dell'anno corrente). */
    public function scopeInRegolaConQuota(Builder $query): Builder
    {
        $currentYear = now()->year;

        return $query->whereHas('incassi', function (Builder $q) use ($currentYear) {
            $q->whereYear('paid_at', $currentYear)
                ->where('type', 'quota');
        });
    }

    /** Scope: soci non in regola con la quota (nessun pagamento quota nell'anno corrente). */
    public function scopeNonInRegolaConQuota(Builder $query): Builder
    {
        $currentYear = now()->year;

        return $query->whereDoesntHave('incassi', function (Builder $q) use ($currentYear) {
            $q->whereYear('paid_at', $currentYear)
                ->where('type', 'quota');
        });
    }

    /** Scope: esclude stati di cessazione (per dashboard/libro soci). */
    public function scopeNonCessati(Builder $query): Builder
    {
        return $query->whereNotIn('stato', ['decesso', 'dimesso', 'escluso', 'moroso', 'rigettato', 'cessato']);
    }

    // --- Parte B: ammissione ---
    public function isAspirante(): bool
    {
        return $this->stato === 'aspirante';
    }

    public function isRigettato(): bool
    {
        return $this->stato === 'rigettato' || $this->ammissione_esito === 'rigettata';
    }

    public function isInRicorso(): bool
    {
        return $this->stato === 'in_ricorso' || ($this->ammissione_esito === 'rigettata' && $this->ricorso_presentato_at !== null);
    }

    public function scadenzaComunicazioneRigetto(): ?\Carbon\Carbon
    {
        if (! $this->ammissione_decisa_at) {
            return null;
        }
        return $this->ammissione_decisa_at->copy()->addDays(60);
    }

    public function scadenzaRicorso(): ?\Carbon\Carbon
    {
        if (! $this->rigetto_comunicato_at) {
            return null;
        }
        return $this->rigetto_comunicato_at->copy()->addDays(60);
    }

    // --- Parte C: perdita qualità socio ---
    public function hasPersoQualita(): bool
    {
        return in_array($this->stato, ['decesso', 'dimesso', 'escluso', 'moroso'], true);
    }

    public function isDimesso(): bool
    {
        return $this->stato === 'dimesso';
    }

    public function isEscluso(): bool
    {
        return $this->stato === 'escluso';
    }

    public function isDeceduto(): bool
    {
        return $this->stato === 'decesso';
    }

    public function isMoroso(): bool
    {
        return $this->stato === 'moroso';
    }
}
