<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Libretto di prestito sociale di un socio cooperatore.
 *
 * Ogni socio può avere uno o più libretti su cui depositare somme
 * che la cooperativa impiega nella propria attività. Il socio percepisce
 * un interesse annuo (soggetto a ritenuta fiscale del 26%).
 */
class PrestitoSocialeLibretto extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'prestito_sociale_libretti';

    protected $fillable = [
        'member_id',
        'numero_libretto',
        'saldo_attuale',
        'tasso_interesse_annuo',
        'data_apertura',
        'data_chiusura',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'saldo_attuale'         => 'decimal:2',
            'tasso_interesse_annuo' => 'decimal:4',
            'data_apertura'         => 'date',
            'data_chiusura'         => 'date',
            'status'                => 'string',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Movimenti ordinati per data valuta ascendente (dal più vecchio al più recente).
     */
    public function movimenti(): HasMany
    {
        return $this->hasMany(PrestitoSocialeMovimento::class, 'libretto_id')
            ->orderBy('data_valuta')
            ->orderBy('id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where('status', 'attivo');
    }

    public function scopeChiusi(Builder $query): Builder
    {
        return $query->where('status', 'chiuso');
    }

    // ── Helper / Accessor ─────────────────────────────────────────────────────

    public function isAttivo(): bool
    {
        return $this->status === 'attivo';
    }

    public function isChiuso(): bool
    {
        return $this->status === 'chiuso';
    }

    /**
     * Calcola il saldo del libretto a una data di valuta specifica
     * sommando tutti i movimenti fino a quella data (inclusa).
     *
     * Algoritmo: usa `saldo_dopo` dell'ultimo movimento ≤ $data
     * (che è già il saldo progressivo risultante).
     */
    public function saldoAggiornatoAl(Carbon $data): float
    {
        $ultimo = $this->movimenti()
            ->whereDate('data_valuta', '<=', $data->toDateString())
            ->orderByDesc('data_valuta')
            ->orderByDesc('id')
            ->first();

        return $ultimo ? (float) $ultimo->saldo_dopo : 0.0;
    }
}
