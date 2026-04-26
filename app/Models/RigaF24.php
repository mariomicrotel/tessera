<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga del Modello F24.
 *
 * Una riga corrisponde a un codice tributo in una specifica sezione
 * (Erario, INPS, Regioni, etc.) con importo a debito e/o a credito.
 */
class RigaF24 extends Model
{
    protected $table = 'righe_f24';

    protected $fillable = [
        'modello_f24_id',
        'sezione',
        'codice_tributo',
        'descrizione',
        'rateazione',
        'anno_riferimento',
        'regione_codice',
        'ente_codice',
        'importo_debito',
        'importo_credito',
        'ordinamento',
    ];

    protected $casts = [
        'anno_riferimento' => 'integer',
        'importo_debito'   => 'float',
        'importo_credito'  => 'float',
        'ordinamento'      => 'integer',
    ];

    public function modello(): BelongsTo
    {
        return $this->belongsTo(ModelloF24::class, 'modello_f24_id');
    }

    /** Saldo riga: positivo = debito netto, negativo = credito netto. */
    public function saldoRiga(): float
    {
        return round($this->importo_debito - $this->importo_credito, 2);
    }
}
