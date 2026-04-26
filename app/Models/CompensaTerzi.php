<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compenso a terzo con ritenuta d'acconto.
 *
 * Modella il pagamento di compensi a collaboratori occasionali, professionisti
 * e agenti/mediatori soggetti a ritenuta d'acconto ex art. 25 DPR 600/1973.
 *
 * Il percipiente è identificato da codice_fiscale; se è anche un socio
 * (Member) si imposta member_id per collegarlo all'anagrafica.
 */
class CompensaTerzi extends Model
{
    use BelongsToTenant;

    protected $table = 'compensi_terzi';

    // ── Tipi di rapporto ──────────────────────────────────────────────────
    public const TIPO_OCCASIONALE    = 'occasionale';
    public const TIPO_PROFESSIONALE  = 'professionale';
    public const TIPO_PROVVIGIONI    = 'provvigioni';

    public const TIPI_RAPPORTO = [
        self::TIPO_OCCASIONALE   => 'Collaborazione occasionale',
        self::TIPO_PROFESSIONALE => 'Prestazione professionale',
        self::TIPO_PROVVIGIONI   => 'Provvigioni / Agenzia',
    ];

    // ── Codici causale (Modello 770) ──────────────────────────────────────
    public const CAUSALE_A = 'A'; // lavoro autonomo occasionale / professionale
    public const CAUSALE_Q = 'Q'; // provvigioni agente monomandatario
    public const CAUSALE_R = 'R'; // provvigioni agente plurimandatario
    public const CAUSALE_V = 'V'; // provvigioni procacciatori d'affari

    public const CAUSALI = [
        self::CAUSALE_A => 'A — Lavoro autonomo, provvigioni, compensi art. 29',
        self::CAUSALE_Q => 'Q — Provvigioni agente monomandatario',
        self::CAUSALE_R => 'R — Provvigioni agente plurimandatario',
        self::CAUSALE_V => 'V — Provvigioni procacciatori d\'affari',
    ];

    // ── Stato ritenuta ────────────────────────────────────────────────────
    public const STATO_DA_VERSARE = 'da_versare';
    public const STATO_VERSATA    = 'versata';

    public const ALIQUOTA_DEFAULT = 20.00; // % standard lavoro autonomo

    protected $fillable = [
        'tenant_id',
        'member_id',
        'nome_percipiente',
        'codice_fiscale',
        'partita_iva',
        'indirizzo',
        'tipo_rapporto',
        'codice_causale',
        'anno_competenza',
        'data_pagamento',
        'causale_prestazione',
        'compenso_lordo',
        'base_imponibile_ritenuta',
        'aliquota_ritenuta',
        'ritenuta',
        'compenso_netto',
        'contributo_inps_beneficiario',
        'contributo_inps_committente',
        'rimborsi_spese',
        'stato_ritenuta',
        'versamento_ritenuta_id',
        'conto_costo_id',
        'conto_ritenute_id',
        'movimento_id',
        'note',
    ];

    protected $casts = [
        'anno_competenza'              => 'integer',
        'data_pagamento'               => 'date',
        'compenso_lordo'               => 'float',
        'base_imponibile_ritenuta'     => 'float',
        'aliquota_ritenuta'            => 'float',
        'ritenuta'                     => 'float',
        'compenso_netto'               => 'float',
        'contributo_inps_beneficiario' => 'float',
        'contributo_inps_committente'  => 'float',
        'rimborsi_spese'               => 'float',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function versamento(): BelongsTo
    {
        return $this->belongsTo(VersamentoRitenuta::class, 'versamento_ritenuta_id');
    }

    public function contoCosto(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_costo_id');
    }

    public function contoRitenute(): BelongsTo
    {
        return $this->belongsTo(ContoContabile::class, 'conto_ritenute_id');
    }

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(MovimentoContabile::class, 'movimento_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isDaVersare(): bool
    {
        return $this->stato_ritenuta === self::STATO_DA_VERSARE;
    }

    public function isVersata(): bool
    {
        return $this->stato_ritenuta === self::STATO_VERSATA;
    }

    /** Importo totale incassato dal percipiente (netto + rimborsi). */
    public function totaleDaCorreispondere(): float
    {
        return round($this->compenso_netto + $this->rimborsi_spese, 2);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePerAnno($query, int $anno)
    {
        return $query->where('anno_competenza', $anno);
    }

    public function scopeDaVersare($query)
    {
        return $query->where('stato_ritenuta', self::STATO_DA_VERSARE);
    }

    public function scopePerPercipiente($query, string $codiceFiscale)
    {
        return $query->where('codice_fiscale', strtoupper($codiceFiscale));
    }
}
