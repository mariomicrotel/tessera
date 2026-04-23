<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimento su un libretto di prestito sociale.
 *
 * Tipi:
 *  - deposito         (segno avere) — aumenta il saldo
 *  - prelievo         (segno dare)  — diminuisce il saldo
 *  - interessi        (segno avere) — accredito periodico interessi maturati
 *  - ritenuta_fiscale (segno dare)  — trattenuta 26% su interessi
 *  - rettifica        (dare|avere)  — correzioni manuali
 */
class PrestitoSocialeMovimento extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TIPO_DEPOSITO          = 'deposito';
    public const TIPO_PRELIEVO          = 'prelievo';
    public const TIPO_INTERESSI         = 'interessi';
    public const TIPO_RITENUTA_FISCALE  = 'ritenuta_fiscale';
    public const TIPO_RETTIFICA         = 'rettifica';

    public const SEGNO_DARE  = 'dare';   // uscita per il socio / diminuisce saldo
    public const SEGNO_AVERE = 'avere';  // entrata per il socio / aumenta saldo

    protected $table = 'prestito_sociale_movimenti';

    protected $fillable = [
        'libretto_id',
        'tipo',
        'importo',
        'segno',
        'saldo_dopo',
        'data_valuta',
        'data_registrazione',
        'anno_competenza',
        'mese_competenza',
        'aliquota_ritenuta',
        'importo_ritenuta',
        'importo_netto',
        'descrizione',
        'incasso_id',
        'spesa_id',
    ];

    protected function casts(): array
    {
        return [
            'importo'            => 'decimal:2',
            'saldo_dopo'         => 'decimal:2',
            'aliquota_ritenuta'  => 'decimal:4',
            'importo_ritenuta'   => 'decimal:2',
            'importo_netto'      => 'decimal:2',
            'data_valuta'        => 'date',
            'data_registrazione' => 'date',
            'anno_competenza'    => 'integer',
            'mese_competenza'    => 'integer',
        ];
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function libretto(): BelongsTo
    {
        return $this->belongsTo(PrestitoSocialeLibretto::class, 'libretto_id');
    }

    public function incasso(): BelongsTo
    {
        return $this->belongsTo(Incasso::class, 'incasso_id');
    }

    public function spesa(): BelongsTo
    {
        return $this->belongsTo(Spesa::class, 'spesa_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeDepositi(Builder $q): Builder
    {
        return $q->where('tipo', self::TIPO_DEPOSITO);
    }

    public function scopePrelievi(Builder $q): Builder
    {
        return $q->where('tipo', self::TIPO_PRELIEVO);
    }

    public function scopeInteressi(Builder $q): Builder
    {
        return $q->where('tipo', self::TIPO_INTERESSI);
    }

    public function scopePerPeriodo(Builder $q, string $dal, string $al): Builder
    {
        return $q->whereBetween('data_valuta', [$dal, $al]);
    }
}
