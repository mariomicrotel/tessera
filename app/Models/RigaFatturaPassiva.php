<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di una fattura passiva.
 */
class RigaFatturaPassiva extends Model
{
    use BelongsToTenant;

    protected $table = 'righe_fattura_passiva';

    protected $fillable = [
        'tenant_id',
        'fattura_passiva_id',
        'codice_iva_id',
        'conto_id',
        'descrizione',
        'quantita',
        'prezzo_unitario',
        'imponibile',
        'iva',
        'totale',
        'indetraibile_percentuale',
        'iva_indetraibile',
    ];

    protected function casts(): array
    {
        return [
            'quantita'                 => 'decimal:4',
            'prezzo_unitario'          => 'decimal:4',
            'imponibile'               => 'decimal:2',
            'iva'                      => 'decimal:2',
            'totale'                   => 'decimal:2',
            'indetraibile_percentuale' => 'decimal:2',
            'iva_indetraibile'         => 'decimal:2',
        ];
    }

    public function fattura(): BelongsTo
    {
        return $this->belongsTo(FatturaPassiva::class, 'fattura_passiva_id');
    }

    public function codiceIva(): BelongsTo
    {
        return $this->belongsTo(CodiceIva::class);
    }

    public function conto(): BelongsTo
    {
        return $this->belongsTo(Conto::class);
    }

    /**
     * Calcola imponibile/iva/totale a partire da quantita, prezzo_unitario e codice_iva.
     */
    public function calcolaTotali(): void
    {
        $imp = (float) $this->quantita * (float) $this->prezzo_unitario;
        $aliquota = $this->codiceIva ? (float) $this->codiceIva->percentuale : 0;
        $iva = round($imp * $aliquota / 100, 2);
        $indPct = (float) $this->indetraibile_percentuale;

        $this->imponibile       = round($imp, 2);
        $this->iva              = $iva;
        $this->iva_indetraibile = round($iva * $indPct / 100, 2);
        $this->totale           = round($imp + $iva, 2);
    }
}
