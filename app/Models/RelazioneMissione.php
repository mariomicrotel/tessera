<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Relazione di Missione — documento obbligatorio per ETS.
 *
 * Art. 13 D.Lgs. 117/2017 (Codice del Terzo Settore):
 *  "Gli enti del Terzo settore ... redigono il bilancio di esercizio
 *   formato dallo stato patrimoniale, dal rendiconto gestionale, con
 *   l'indicazione, dei proventi e degli oneri, dall'ente, e dalla
 *   relazione di missione che illustra le poste di bilancio, l'andamento
 *   economico e gestionale dell'ente e le modalità di perseguimento delle
 *   finalità statutarie."
 *
 * Struttura consigliata (Linee Guida Min. Lavoro 2021):
 *  1. Presentazione dell'ente e missione
 *  2. Attività istituzionali svolte
 *  3. Attività diverse e accessorie
 *  4. Raccolta fondi (se presente)
 *  5. Situazione patrimoniale e finanziaria
 *  6. Andamento economico e gestionale
 *  7. Fatti rilevanti avvenuti dopo la chiusura dell'esercizio
 *  8. Prospettive future
 */
class RelazioneMissione extends Model
{
    use BelongsToTenant;

    protected $table = 'relazioni_missione';

    // ── Stati ─────────────────────────────────────────────────────────────
    public const STATO_BOZZA     = 'bozza';
    public const STATO_DEFINITIVA = 'definitiva';
    public const STATO_APPROVATA  = 'approvata';

    public const STATI = [
        self::STATO_BOZZA      => 'Bozza',
        self::STATO_DEFINITIVA => 'Definitiva',
        self::STATO_APPROVATA  => 'Approvata',
    ];

    // ── Sezioni standard (indici per template) ────────────────────────────
    public const SEZIONI_DEFAULT = [
        [
            'id'     => 'presentazione',
            'titolo' => "1. Presentazione dell'ente e missione",
            'testo'  => '',
        ],
        [
            'id'     => 'attivita_istituzionali',
            'titolo' => '2. Attività istituzionali svolte',
            'testo'  => '',
        ],
        [
            'id'     => 'attivita_diverse',
            'titolo' => '3. Attività diverse e accessorie',
            'testo'  => '',
        ],
        [
            'id'     => 'raccolta_fondi',
            'titolo' => '4. Raccolta fondi e liberalità',
            'testo'  => '',
        ],
        [
            'id'     => 'situazione_patrimoniale',
            'titolo' => '5. Situazione patrimoniale e finanziaria',
            'testo'  => '',
        ],
        [
            'id'     => 'andamento_economico',
            'titolo' => '6. Andamento economico e gestionale',
            'testo'  => '',
        ],
        [
            'id'     => 'fatti_rilevanti',
            'titolo' => '7. Fatti rilevanti successivi alla chiusura',
            'testo'  => '',
        ],
        [
            'id'     => 'prospettive',
            'titolo' => '8. Prospettive future e obiettivi',
            'testo'  => '',
        ],
    ];

    protected $fillable = [
        'tenant_id',
        'anno',
        'stato',
        'organo_approvante',
        'data_approvazione',
        'luogo_approvazione',
        'sezioni',
        'variabili_snapshot',
        'note_interne',
    ];

    protected $casts = [
        'anno'                => 'integer',
        'data_approvazione'   => 'date',
        'sezioni'             => 'array',
        'variabili_snapshot'  => 'array',
    ];

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isBozza(): bool    { return $this->stato === self::STATO_BOZZA; }
    public function isDefinitiva(): bool { return $this->stato === self::STATO_DEFINITIVA; }
    public function isApprovata(): bool  { return $this->stato === self::STATO_APPROVATA; }

    /** Restituisce le sezioni già compilate (testo non vuoto). */
    public function sezioniCompilate(): int
    {
        return collect($this->sezioni ?? [])->filter(fn ($s) => ! empty(trim($s['testo'])))->count();
    }
}
