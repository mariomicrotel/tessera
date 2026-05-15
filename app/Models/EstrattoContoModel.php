<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstrattoContoModel extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table = 'estratti_conto';

    protected $fillable = [
        'tenant_id',
        'nome_file',
        'banca',
        'iban',
        'periodo_dal',
        'periodo_al',
        'saldo_iniziale',
        'saldo_finale',
        'formato',
    ];

    protected function casts(): array
    {
        return [
            'periodo_dal'    => 'date',
            'periodo_al'     => 'date',
            'saldo_iniziale' => 'decimal:2',
            'saldo_finale'   => 'decimal:2',
        ];
    }

    public function movimenti(): HasMany
    {
        return $this->hasMany(MovimentoBancario::class, 'estratto_conto_id');
    }
}
