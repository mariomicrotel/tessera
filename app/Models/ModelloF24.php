<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modello F24 — delega di pagamento unificata.
 *
 * Aggrega i debiti fiscali di un periodo (IVA, ritenute, INPS, IRAP, …)
 * in un unico documento pronto per il pagamento tramite banca/home banking.
 *
 * Il modello può essere:
 *  - generato automaticamente da una LiquidazioneIva o da un VersamentoRitenuta
 *  - compilato manualmente riga per riga
 */
class ModelloF24 extends Model
{
    use BelongsToTenant;

    protected $table = 'modelli_f24';

    // ── Stati ─────────────────────────────────────────────────────────────
    public const STATO_BOZZA     = 'bozza';
    public const STATO_COMPILATO = 'compilato';
    public const STATO_VERSATO   = 'versato';

    public const STATI = [
        self::STATO_BOZZA     => 'Bozza',
        self::STATO_COMPILATO => 'Compilato',
        self::STATO_VERSATO   => 'Versato',
    ];

    // ── Sezioni ───────────────────────────────────────────────────────────
    public const SEZIONE_ERARIO     = 'erario';
    public const SEZIONE_INPS       = 'inps';
    public const SEZIONE_REGIONI    = 'regioni';
    public const SEZIONE_ALTRI_ENTI = 'altri_enti';
    public const SEZIONE_ACCISE     = 'accise';

    public const SEZIONI = [
        self::SEZIONE_ERARIO     => 'Erario',
        self::SEZIONE_INPS       => 'INPS',
        self::SEZIONE_REGIONI    => 'Regioni',
        self::SEZIONE_ALTRI_ENTI => 'Altri enti',
        self::SEZIONE_ACCISE     => 'Accise',
    ];

    // ── Codici tributo più comuni ─────────────────────────────────────────
    // IVA mensile (gen-dic)
    public const CODICI_IVA_MENSILE = [
        1 => '6001', 2 => '6002', 3  => '6003',
        4 => '6004', 5 => '6005', 6  => '6006',
        7 => '6007', 8 => '6008', 9  => '6009',
        10=> '6010', 11=> '6011', 12 => '6012',
    ];

    // IVA trimestrale
    public const CODICI_IVA_TRIMESTRALE = [
        1 => '6031', 2 => '6032', 3 => '6033', 4 => '6099',
    ];

    // Ritenute
    public const CODICE_RITENUTA_LAV_AUTONOMO = '1040';
    public const CODICE_RITENUTA_PROVVIGIONI  = '1038';

    // IRAP
    public const CODICE_IRAP_SALDO          = '3800';
    public const CODICE_IRAP_PRIMO_ACCONTO  = '3812';
    public const CODICE_IRAP_SECONDO_ACCONTO= '3813';

    // IRPEF acconti/saldo
    public const CODICE_IRPEF_SALDO          = '4001';
    public const CODICE_IRPEF_PRIMO_ACCONTO  = '4033';
    public const CODICE_IRPEF_SECONDO_ACCONTO= '4034';

    protected $fillable = [
        'tenant_id',
        'anno',
        'mese',
        'data_compilazione',
        'data_versamento',
        'stato',
        'totale_debiti',
        'totale_crediti',
        'saldo',
        'liquidazione_iva_id',
        'versamento_ritenuta_id',
        'note',
    ];

    protected $casts = [
        'anno'              => 'integer',
        'mese'              => 'integer',
        'data_compilazione' => 'date',
        'data_versamento'   => 'date',
        'totale_debiti'     => 'float',
        'totale_crediti'    => 'float',
        'saldo'             => 'float',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function righe(): HasMany
    {
        return $this->hasMany(RigaF24::class)->orderBy('sezione')->orderBy('ordinamento');
    }

    public function liquidazioneIva(): BelongsTo
    {
        return $this->belongsTo(LiquidazioneIva::class);
    }

    public function versamentoRitenuta(): BelongsTo
    {
        return $this->belongsTo(VersamentoRitenuta::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isBozza(): bool
    {
        return $this->stato === self::STATO_BOZZA;
    }

    public function isVersionato(): bool
    {
        return $this->stato === self::STATO_VERSATO;
    }

    public function righePerSezione(string $sezione)
    {
        return $this->righe->where('sezione', $sezione)->values();
    }

    /** Label periodo, es. "Gennaio 2026" oppure "Anno 2026". */
    public function periodoLabel(): string
    {
        if ($this->mese) {
            $mesi = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                     'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
            return ($mesi[$this->mese] ?? $this->mese).' '.$this->anno;
        }
        return 'Anno '.$this->anno;
    }

    /** Ricalcola totali da righe. */
    public function ricalcolaTotali(): void
    {
        $debiti  = $this->righe()->sum('importo_debito');
        $crediti = $this->righe()->sum('importo_credito');
        $this->update([
            'totale_debiti'  => round((float)$debiti,  2),
            'totale_crediti' => round((float)$crediti, 2),
            'saldo'          => round((float)$debiti - (float)$crediti, 2),
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopePerAnno($query, int $anno)
    {
        return $query->where('anno', $anno);
    }

    public function scopeDaVersare($query)
    {
        return $query->whereIn('stato', [self::STATO_BOZZA, self::STATO_COMPILATO])
                     ->where('saldo', '>', 0);
    }
}
