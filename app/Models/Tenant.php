<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, SoftDeletes;

    // ─────────────────────────────────────────────────────────────────────────
    // Costanti — Forma Giuridica
    // ─────────────────────────────────────────────────────────────────────────

    // ETS
    const FG_ETS_ODV          = 'ets_odv';
    const FG_ETS_APS          = 'ets_aps';
    const FG_ETS_FONDAZIONE   = 'ets_fondazione';
    const FG_ETS_GENERICO     = 'ets_generico';
    // Cooperative
    const FG_COOP_LAVORO      = 'coop_lavoro';
    const FG_COOP_SOCIALE_A   = 'coop_sociale_a';
    const FG_COOP_SOCIALE_B   = 'coop_sociale_b';
    const FG_COOP_AGRICOLA    = 'coop_agricola';
    const FG_COOP_CONSORTILE  = 'coop_consortile';
    const FG_COOP_CONSUMO     = 'coop_consumo';
    const FG_COOP_ABITAZIONE  = 'coop_abitazione';
    const FG_COOP_COMUNITA    = 'coop_comunita';
    // Società di capitali
    const FG_SRL              = 'srl';
    const FG_SRLS             = 'srls';
    const FG_SPA              = 'spa';
    const FG_SAPA             = 'sapa';
    // Società di persone
    const FG_SAS              = 'sas';
    const FG_SNC              = 'snc';
    const FG_SS               = 'ss';
    // Autonomi
    const FG_DITTA_IND        = 'ditta_individuale';
    const FG_LIBERO_PROF      = 'libero_professionista';
    const FG_STUDIO_PROF      = 'studio_professionale';
    // Enti non commerciali non ETS
    const FG_ASS_NON_ETS      = 'associazione_non_ets';
    const FG_FOND_NON_ETS     = 'fondazione_non_ets';
    // Regimi agevolati
    const FG_FORFETTARIO      = 'forfettario';
    const FG_ALTRO            = 'altro';

    // ─────────────────────────────────────────────────────────────────────────
    // Costanti — Dimensione Bilancio
    // ─────────────────────────────────────────────────────────────────────────

    const DIM_MICRO           = 'micro';
    const DIM_ABBREVIATO      = 'abbreviato';
    const DIM_ORDINARIO_CEE   = 'ordinario_cee';
    const DIM_ETS_D           = 'ets_d';
    const DIM_COOPERATIVA     = 'cooperativa';
    const DIM_NON_APPLICABILE = 'non_applicabile';

    // ─────────────────────────────────────────────────────────────────────────
    // Costanti — Regime Contabile
    // ─────────────────────────────────────────────────────────────────────────

    const RC_ORDINARIO        = 'ordinario';
    const RC_SEMPLIFICATO     = 'semplificato';
    const RC_FORFETTARIO      = 'forfettario';
    const RC_NON_APPLICABILE  = 'non_applicabile';

    // ─────────────────────────────────────────────────────────────────────────
    // Costanti — Regime IVA
    // ─────────────────────────────────────────────────────────────────────────

    const IVA_ORDINARIO       = 'ordinario';
    const IVA_FORFETTARIO     = 'forfettario';
    const IVA_AGRICOLO        = 'agricolo';
    const IVA_MARGINE         = 'margine';
    const IVA_EDITORIA        = 'editoria';
    const IVA_ESENTE          = 'esente';
    const IVA_NON_APPLICABILE = 'non_applicabile';

    // ─────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'plan',
        'plan_expires_at',
        'is_active',
        'settings',
        // Legacy (backward-compat)
        'organization_type',
        'cooperative_type',
        'codice_fiscale',
        'partita_iva',
        'numero_iscrizione_albo_coop',
        'capitale_sottoscritto',
        'capitale_versato',
        // W1 — Onboarding
        'forma_giuridica',
        'dimensione_bilancio',
        'regime_contabile',
        'regime_iva',
        'attivita_ateco',
        'wizard_completato_at',
        'wizard_step_corrente',
        // Anagrafica estesa
        'pec',
        'rea_numero',
        'rea_citta',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'nazione',
        'telefono',
        'sito_web',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'settings'             => 'array',
        'plan_expires_at'      => 'datetime',
        'capitale_sottoscritto' => 'decimal:2',
        'capitale_versato'     => 'decimal:2',
        'wizard_completato_at' => 'datetime',
        'wizard_step_corrente' => 'integer',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Helper — Tipo organizzazione (legacy + nuovi)
    // ─────────────────────────────────────────────────────────────────────────

    /** True se il tenant è una qualsiasi forma di ETS. */
    public function isETS(): bool
    {
        if ($this->forma_giuridica !== null) {
            return str_starts_with($this->forma_giuridica, 'ets_');
        }
        return $this->organization_type === 'ets' || $this->organization_type === null;
    }

    /** True se il tenant è una qualsiasi forma di cooperativa. */
    public function isCooperativa(): bool
    {
        if ($this->forma_giuridica !== null) {
            return str_starts_with($this->forma_giuridica, 'coop_');
        }
        return $this->organization_type === 'cooperative';
    }

    /** True se il tenant è una società di capitali (SRL, SRLS, SPA, SAPA). */
    public function isSocietaCapitali(): bool
    {
        return in_array($this->forma_giuridica, [
            self::FG_SRL, self::FG_SRLS, self::FG_SPA, self::FG_SAPA,
        ]);
    }

    /** True se il tenant è una società di persone (SAS, SNC, SS). */
    public function isSocietaPersone(): bool
    {
        return in_array($this->forma_giuridica, [
            self::FG_SAS, self::FG_SNC, self::FG_SS,
        ]);
    }

    /** True se il tenant è un libero professionista o studio. */
    public function isProfessionista(): bool
    {
        return in_array($this->forma_giuridica, [
            self::FG_LIBERO_PROF, self::FG_STUDIO_PROF, self::FG_DITTA_IND,
        ]);
    }

    /** True se il tenant è in regime forfettario. */
    public function isForfettario(): bool
    {
        return $this->forma_giuridica === self::FG_FORFETTARIO
            || $this->regime_contabile === self::RC_FORFETTARIO;
    }

    /** True se il wizard di onboarding è stato completato. */
    public function wizardCompletato(): bool
    {
        return $this->wizard_completato_at !== null;
    }

    /** True se il tenant deve ancora completare il wizard. */
    public function wizardPendente(): bool
    {
        return $this->wizard_completato_at === null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Label leggibili
    // ─────────────────────────────────────────────────────────────────────────

    /** Etichetta leggibile della forma giuridica. */
    public function formaGiuridicaLabel(): string
    {
        return self::formaGiuridicaLabels()[$this->forma_giuridica] ?? $this->forma_giuridica ?? '—';
    }

    /** Etichetta leggibile della dimensione bilancio. */
    public function dimensioneBilancioLabel(): string
    {
        return match ($this->dimensione_bilancio) {
            self::DIM_MICRO           => 'Micro-impresa (art. 2435-ter c.c.)',
            self::DIM_ABBREVIATO      => 'Bilancio abbreviato (art. 2435-bis c.c.)',
            self::DIM_ORDINARIO_CEE   => 'Bilancio ordinario CEE',
            self::DIM_ETS_D           => 'Bilancio ETS (D.M. 5/3/2020)',
            self::DIM_COOPERATIVA     => 'Bilancio cooperativa',
            self::DIM_NON_APPLICABILE => 'Non applicabile',
            default                   => $this->dimensione_bilancio ?? '—',
        };
    }

    /** Etichetta leggibile del regime contabile. */
    public function regimeContabileLabel(): string
    {
        return match ($this->regime_contabile) {
            self::RC_ORDINARIO        => 'Contabilità ordinaria',
            self::RC_SEMPLIFICATO     => 'Contabilità semplificata (art. 18 DPR 600/73)',
            self::RC_FORFETTARIO      => 'Regime forfettario (L. 190/2014)',
            self::RC_NON_APPLICABILE  => 'Non applicabile',
            default                   => $this->regime_contabile ?? '—',
        };
    }

    /** Etichetta leggibile del regime IVA. */
    public function regimeIvaLabel(): string
    {
        return match ($this->regime_iva) {
            self::IVA_ORDINARIO       => 'Regime IVA ordinario',
            self::IVA_FORFETTARIO     => 'Escluso IVA (regime forfettario)',
            self::IVA_AGRICOLO        => 'Regime IVA agricolo (art. 34)',
            self::IVA_MARGINE         => 'Regime del margine (beni usati)',
            self::IVA_EDITORIA        => 'Editoria (L. 62/2001)',
            self::IVA_ESENTE          => 'Attività esente IVA (art. 10)',
            self::IVA_NON_APPLICABILE => 'Non soggetto IVA',
            default                   => $this->regime_iva ?? '—',
        };
    }

    /**
     * Mappa statica: forma_giuridica → etichetta leggibile.
     * Usata da Vue (passata via Inertia shared props) e da formaGiuridicaLabel().
     */
    public static function formaGiuridicaLabels(): array
    {
        return [
            // ETS
            self::FG_ETS_ODV          => 'Organizzazione di Volontariato (OdV)',
            self::FG_ETS_APS          => 'Associazione di Promozione Sociale (APS)',
            self::FG_ETS_FONDAZIONE   => 'Fondazione ETS',
            self::FG_ETS_GENERICO     => 'Ente del Terzo Settore (generico)',
            // Cooperative
            self::FG_COOP_LAVORO      => 'Cooperativa di Lavoro',
            self::FG_COOP_SOCIALE_A   => 'Cooperativa Sociale (Tipo A)',
            self::FG_COOP_SOCIALE_B   => 'Cooperativa Sociale (Tipo B)',
            self::FG_COOP_AGRICOLA    => 'Cooperativa Agricola',
            self::FG_COOP_CONSORTILE  => 'Cooperativa Consortile',
            self::FG_COOP_CONSUMO     => 'Cooperativa di Consumo',
            self::FG_COOP_ABITAZIONE  => 'Cooperativa di Abitazione',
            self::FG_COOP_COMUNITA    => 'Cooperativa di Comunità',
            // Società di capitali
            self::FG_SRL              => 'Società a Responsabilità Limitata (S.r.l.)',
            self::FG_SRLS             => 'S.r.l. Semplificata (S.r.l.s.)',
            self::FG_SPA              => 'Società per Azioni (S.p.A.)',
            self::FG_SAPA             => 'Società in Accomandita per Azioni (S.a.p.a.)',
            // Società di persone
            self::FG_SAS              => 'Società in Accomandita Semplice (S.a.s.)',
            self::FG_SNC              => 'Società in Nome Collettivo (S.n.c.)',
            self::FG_SS               => 'Società Semplice (S.s.)',
            // Autonomi
            self::FG_DITTA_IND        => 'Ditta Individuale',
            self::FG_LIBERO_PROF      => 'Libero Professionista',
            self::FG_STUDIO_PROF      => 'Studio Professionale',
            // Non ETS
            self::FG_ASS_NON_ETS      => 'Associazione (non ETS)',
            self::FG_FOND_NON_ETS     => 'Fondazione (non ETS)',
            // Agevolati
            self::FG_FORFETTARIO      => 'Regime Forfettario',
            self::FG_ALTRO            => 'Altro',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy helpers (mantengono backward-compat con codice esistente)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Etichetta leggibile del tipo cooperativa (legacy).
     */
    public function cooperativeTypeLabel(): string
    {
        if ($this->forma_giuridica !== null && str_starts_with($this->forma_giuridica, 'coop_')) {
            return self::formaGiuridicaLabels()[$this->forma_giuridica] ?? 'Cooperativa';
        }

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

    // ─────────────────────────────────────────────────────────────────────────
    // Piano
    // ─────────────────────────────────────────────────────────────────────────

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

    public function memberLimit(): int
    {
        return match ($this->plan) {
            'free'       => 25,
            'basic'      => 100,
            'pro'        => 500,
            'enterprise' => PHP_INT_MAX,
            default      => 25,
        };
    }

    public function staffLimit(): int
    {
        return match ($this->plan) {
            'free'       => 1,
            'basic'      => 3,
            'pro'        => 10,
            'enterprise' => PHP_INT_MAX,
            default      => 1,
        };
    }

    public function storageLimitMb(): int
    {
        return match ($this->plan) {
            'free'       => 100,
            'basic'      => 1024,
            'pro'        => 10240,
            'enterprise' => 51200,
            default      => 100,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relazioni
    // ─────────────────────────────────────────────────────────────────────────

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Route binding
    // ─────────────────────────────────────────────────────────────────────────

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
