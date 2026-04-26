<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centro di costo: permette la contabilità analitica per area/progetto/sede.
 * Le righe di movimento contabile possono essere assegnate a un centro di costo.
 */
class CentroCosto extends Model
{
    use BelongsToTenant;

    protected $table = 'centri_di_costo';

    protected $fillable = [
        'tenant_id',
        'codice',
        'descrizione',
        'note',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function righeMovimento(): HasMany
    {
        return $this->hasMany(RigaMovimentoContabile::class, 'centro_costo_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAttivi(Builder $query): Builder
    {
        return $query->where('attivo', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Calcola il totale dare/avere per un anno e l'eventuale gestione.
     */
    public function saldoAnno(int $anno, ?string $gestione = null): array
    {
        $query = RigaMovimentoContabile::join('movimenti_contabili as m', 'm.id', '=', 'righe_movimento_contabile.movimento_id')
            ->where('righe_movimento_contabile.centro_costo_id', $this->id)
            ->where('m.anno_esercizio', $anno)
            ->whereIn('m.stato', ['definitivo', 'confermato']);

        if ($gestione !== null) {
            $query->where('righe_movimento_contabile.gestione', $gestione);
        }

        $totale = $query->selectRaw(
            'SUM(importo_dare) as tot_dare, SUM(importo_avere) as tot_avere'
        )->first();

        return [
            'dare'  => (float) ($totale->tot_dare  ?? 0),
            'avere' => (float) ($totale->tot_avere ?? 0),
            'saldo' => (float) ($totale->tot_avere ?? 0) - (float) ($totale->tot_dare ?? 0),
        ];
    }
}
