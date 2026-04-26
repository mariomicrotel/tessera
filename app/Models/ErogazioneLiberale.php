<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erogazione Liberale — D.Lgs. 117/2017 art. 83 (Codice del Terzo Settore).
 *
 * Traccia le donazioni ricevute con i dati fiscali necessari per la
 * comunicazione annuale all'Agenzia delle Entrate e il riconoscimento
 * della detrazione in capo al donante.
 *
 * Aliquote detraibilità:
 *  - 26% per persone fisiche (art. 83 c.1 CTS)
 *  - 30% per enti (art. 83 c.2 CTS)
 *
 * Condizione di detraibilità: pagamento con strumento tracciabile.
 * Il contante NON è detraibile (art. 83 c.1 CTS).
 */
class ErogazioneLiberale extends Model
{
    use BelongsToTenant;

    protected $table = 'erogazioni_liberali';

    // ── Costanti ──────────────────────────────────────────────────────────

    public const TIPO_PERSONA_FISICA = 'persona_fisica';
    public const TIPO_ENTE           = 'ente';

    public const TIPI_DONANTE = [
        self::TIPO_PERSONA_FISICA => 'Persona fisica',
        self::TIPO_ENTE           => 'Ente / Società',
    ];

    public const MODALITA = [
        'bonifico'          => 'Bonifico bancario/postale',
        'assegno_circolare' => 'Assegno circolare',
        'carta_credito'     => 'Carta di credito',
        'carta_debito'      => 'Carta di debito / Bancomat',
        'altro_tracciabile' => 'Altro strumento tracciabile',
        'contante'          => 'Contante (non detraibile)',
    ];

    /** Modalità che conferiscono detraibilità ex art. 83 CTS */
    public const MODALITA_TRACCIABILI = [
        'bonifico',
        'assegno_circolare',
        'carta_credito',
        'carta_debito',
        'altro_tracciabile',
    ];

    public const ALIQUOTA_PF   = 26;
    public const ALIQUOTA_ENTE = 30;

    // ── Fillable ──────────────────────────────────────────────────────────

    protected $fillable = [
        'tenant_id',
        'anno',
        'donante_tipo',
        'donante_cf',
        'donante_piva',
        'donante_cognome',
        'donante_nome',
        'donante_ragione_sociale',
        'donante_indirizzo',
        'donante_cap',
        'donante_comune',
        'donante_provincia',
        'importo',
        'data_erogazione',
        'modalita_pagamento',
        'is_detraibile',
        'aliquota_detrazione',
        'incasso_id',
        'note',
    ];

    protected $casts = [
        'importo'           => 'decimal:2',
        'data_erogazione'   => 'date',
        'is_detraibile'     => 'boolean',
        'aliquota_detrazione' => 'integer',
        'anno'              => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function incasso(): BelongsTo
    {
        return $this->belongsTo(Incasso::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** Nome visualizzabile del donante (persona fisica o ragione sociale). */
    public function nomeCompleto(): string
    {
        if ($this->donante_tipo === self::TIPO_PERSONA_FISICA) {
            return trim("{$this->donante_cognome} {$this->donante_nome}") ?: $this->donante_cf;
        }

        return $this->donante_ragione_sociale ?? $this->donante_cf;
    }

    /** Importo della detrazione spettante al donante. */
    public function importoDetrazione(): float
    {
        if (! $this->is_detraibile || ! $this->aliquota_detrazione) {
            return 0.0;
        }

        return round((float) $this->importo * $this->aliquota_detrazione / 100, 2);
    }

    /** Codice modalità pagamento per il file AdE (formato CSV/XML). */
    public function codiceModalitaAde(): string
    {
        return match ($this->modalita_pagamento) {
            'bonifico'          => 'BO',
            'assegno_circolare' => 'AC',
            'carta_credito'     => 'CC',
            'carta_debito'      => 'CD',
            'altro_tracciabile' => 'AT',
            default             => 'CN', // Contante
        };
    }

    /** Aliquota detrazione calcolata in base al tipo donante e modalità. */
    public static function calcolaAliquota(string $tipo, string $modalita): ?int
    {
        if (! in_array($modalita, self::MODALITA_TRACCIABILI)) {
            return null; // Contante: non detraibile
        }

        return $tipo === self::TIPO_PERSONA_FISICA ? self::ALIQUOTA_PF : self::ALIQUOTA_ENTE;
    }
}
