<?php

namespace App\Models;

use App\Traits\AuditsChanges;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fattura passiva (di acquisto).
 *
 * Una volta agganciata a una liquidazione definitiva (liquidazione_iva_id != null
 * con status != bozza) diventa read-only: gli update sono bloccati a livello
 * application layer (Policy / FormRequest).
 */
class FatturaPassiva extends Model
{
    use BelongsToTenant, SoftDeletes, AuditsChanges;

    // ── Esigibilità IVA ───────────────────────────────────────────────────
    public const ESIGIBILITA_IMMEDIATA     = 'immediata';
    public const ESIGIBILITA_DIFFERITA     = 'differita';
    public const ESIGIBILITA_SPLIT_PAYMENT = 'split_payment';

    public const ESIGIBILITA_LABEL = [
        self::ESIGIBILITA_IMMEDIATA     => 'Immediata',
        self::ESIGIBILITA_DIFFERITA     => 'Differita (IVA per cassa)',
        self::ESIGIBILITA_SPLIT_PAYMENT => 'Split Payment (PA)',
    ];

    // ── Stato pagamento ───────────────────────────────────────────────────
    public const STATO_DA_PAGARE              = 'da_pagare';
    public const STATO_PAGATA                 = 'pagata';
    public const STATO_PARZIALMENTE_PAGATA    = 'parzialmente_pagata';
    public const STATO_ANNULLATA              = 'annullata';

    public const STATI_LABEL = [
        self::STATO_DA_PAGARE           => 'Da pagare',
        self::STATO_PAGATA              => 'Pagata',
        self::STATO_PARZIALMENTE_PAGATA => 'Parzialmente pagata',
        self::STATO_ANNULLATA           => 'Annullata',
    ];

    // ── Tipo documento SDI ────────────────────────────────────────────────
    public const TIPI_DOCUMENTO = [
        'TD01' => 'TD01 – Fattura',
        'TD02' => 'TD02 – Acconto/Anticipo su fattura',
        'TD03' => 'TD03 – Acconto/Anticipo su parcella',
        'TD04' => 'TD04 – Nota di credito',
        'TD05' => 'TD05 – Nota di debito',
        'TD06' => 'TD06 – Parcella',
        'TD16' => 'TD16 – Integrazione fattura (reverse charge interno)',
        'TD17' => 'TD17 – Integrazione/autofattura (acquisto servizi estero)',
        'TD18' => 'TD18 – Integrazione (acquisto beni intracomunitari)',
        'TD19' => 'TD19 – Integrazione/autofattura (acquisto beni art.17)',
        'TD20' => 'TD20 – Autofattura per regolarizzazione',
        'TD24' => 'TD24 – Fattura differita art.21 c.4 lett.a',
        'TD25' => 'TD25 – Fattura differita art.21 c.4 terzo periodo',
        'TD26' => 'TD26 – Cessione beni ammortizzabili',
        'TD27' => 'TD27 – Fattura pro-forma',
        'TD28' => 'TD28 – Acquisti da San Marino con IVA',
    ];

    protected $table = 'fatture_passive';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'numero_fattura',
        'data_fattura',
        'data_ricezione',
        'data_registrazione',
        'data_scadenza',
        'imponibile_totale',
        'iva_totale',
        'totale_documento',
        'esigibilita',
        'tipo_documento',
        'stato_pagamento',
        'liquidazione_iva_id',
        'xml_sdi_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_fattura'       => 'date',
            'data_ricezione'     => 'date',
            'data_registrazione' => 'date',
            'data_scadenza'      => 'date',
            'imponibile_totale'  => 'decimal:2',
            'iva_totale'         => 'decimal:2',
            'totale_documento'   => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function righe(): HasMany
    {
        return $this->hasMany(RigaFatturaPassiva::class);
    }

    public function liquidazione(): BelongsTo
    {
        return $this->belongsTo(LiquidazioneIva::class, 'liquidazione_iva_id');
    }

    public function scopeDaPagare($query)
    {
        return $query->where('stato_pagamento', self::STATO_DA_PAGARE);
    }

    public function scopeNelPeriodo($query, string $dataInizio, string $dataFine)
    {
        return $query->whereBetween('data_registrazione', [$dataInizio, $dataFine]);
    }

    /**
     * Ricalcola i totali sommando le righe.
     */
    public function ricalcolaTotali(): void
    {
        $this->imponibile_totale = $this->righe()->sum('imponibile');
        $this->iva_totale        = $this->righe()->sum('iva');
        $this->totale_documento  = $this->righe()->sum('totale');
    }

    /**
     * True se la fattura è agganciata a una liquidazione definitiva.
     */
    public function isReadOnly(): bool
    {
        if (! $this->liquidazione_iva_id) {
            return false;
        }

        return $this->liquidazione && $this->liquidazione->status !== LiquidazioneIva::STATUS_BOZZA;
    }
}
