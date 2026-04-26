<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versamento di ritenute d'acconto tramite Modello F24.
 *
 * Aggrega i compensi del mese e registra il versamento allo Stato
 * (entro il 16 del mese successivo ai pagamenti).
 */
class VersamentoRitenuta extends Model
{
    use BelongsToTenant;

    protected $table = 'versamenti_ritenute';

    // Codici tributo standard
    public const CODICE_TRIBUTO_LAV_AUTONOMO = '1040'; // lavoro autonomo/occasionale
    public const CODICE_TRIBUTO_PROVVIGIONI  = '1038'; // provvigioni agenti

    protected $fillable = [
        'tenant_id',
        'mese_riferimento',
        'anno_riferimento',
        'data_versamento',
        'codice_tributo',
        'importo_totale',
        'codice_ufficio',
        'codice_atto',
        'f24_pdf_path',
        'note',
    ];

    protected $casts = [
        'mese_riferimento' => 'integer',
        'anno_riferimento' => 'integer',
        'data_versamento'  => 'date',
        'importo_totale'   => 'float',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function compensi(): HasMany
    {
        return $this->hasMany(CompensaTerzi::class, 'versamento_ritenuta_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Scadenza legale: 16 del mese successivo. */
    public function scadenzaVersamento(): string
    {
        $mese = $this->mese_riferimento % 12 + 1;
        $anno = $this->mese_riferimento === 12
            ? $this->anno_riferimento + 1
            : $this->anno_riferimento;

        return \Carbon\Carbon::create($anno, $mese, 16)->toDateString();
    }

    public function nomeMese(): string
    {
        return \Carbon\Carbon::create($this->anno_riferimento, $this->mese_riferimento, 1)
            ->locale('it')
            ->isoFormat('MMMM YYYY');
    }
}
