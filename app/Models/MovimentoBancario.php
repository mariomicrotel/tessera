<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovimentoBancario extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'movimenti_bancari';

    public const TIPO_DARE  = 'dare';
    public const TIPO_AVERE = 'avere';

    protected $fillable = [
        'tenant_id',
        'estratto_conto_id',
        'data_valuta',
        'data_contabile',
        'descrizione',
        'importo',
        'tipo',
        'riferimento',
        'riconciliato',
    ];

    protected function casts(): array
    {
        return [
            'data_valuta'    => 'date',
            'data_contabile' => 'date',
            'importo'        => 'decimal:2',
            'riconciliato'   => 'boolean',
        ];
    }

    public function estrattoConto(): BelongsTo
    {
        return $this->belongsTo(EstrattoContoModel::class, 'estratto_conto_id');
    }

    public function riconciliazioni(): HasMany
    {
        return $this->hasMany(RiconciliazioneVoce::class, 'movimento_bancario_id');
    }

    public function scopeNonRiconciliati($query)
    {
        return $query->where('riconciliato', false);
    }
}
