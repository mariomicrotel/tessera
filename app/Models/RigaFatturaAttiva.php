<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di una fattura attiva.
 */
class RigaFatturaAttiva extends Model
{
    use BelongsToTenant;

    protected $table = 'righe_fattura_attiva';

    protected $fillable = [
        'tenant_id',
        'fattura_attiva_id',
        'codice_iva_id',
        'conto_id',
        'descrizione',
        'quantita',
        'prezzo_unitario',
        'sconto_percentuale',
        'imponibile',
        'iva',
        'totale',
    ];

    protected function casts(): array
    {
        return [
            'quantita'           => 'decimal:4',
            'prezzo_unitario'    => 'decimal:4',
            'sconto_percentuale' => 'decimal:2',
            'imponibile'         => 'decimal:2',
            'iva'                => 'decimal:2',
            'totale'             => 'decimal:2',
        ];
    }

    public function fattura(): BelongsTo
    {
        return $this->belongsTo(FatturaAttiva::class, 'fattura_attiva_id');
    }

    public function codiceIva(): BelongsTo
    {
        return $this->belongsTo(CodiceIva::class);
    }

    public function conto(): BelongsTo
    {
        return $this->belongsTo(Conto::class);
    }

    public function calcolaTotali(): void
    {
        $lordo  = (float) $this->quantita * (float) $this->prezzo_unitario;
        $sconto = $lordo * (float) $this->sconto_percentuale / 100;
        $imp    = $lordo - $sconto;

        $aliquota = $this->codiceIva ? (float) $this->codiceIva->percentuale : 0;
        $iva = round($imp * $aliquota / 100, 2);

        $this->imponibile = round($imp, 2);
        $this->iva        = $iva;
        $this->totale     = round($imp + $iva, 2);
    }
}
