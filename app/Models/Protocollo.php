<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Protocollo extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'protocolli';

    // -------------------------------------------------------------------------
    // Costanti
    // -------------------------------------------------------------------------

    public const TIPO_ENTRATA = 'entrata';
    public const TIPO_USCITA  = 'uscita';

    // -------------------------------------------------------------------------
    // Configurazione Eloquent
    // -------------------------------------------------------------------------

    protected $fillable = [
        'anno',
        'numero',
        'tipo',
        'data_registrazione',
        'oggetto',
        'mittente',
        'destinatario',
        'note',
        'linked_type',
        'linked_id',
        'created_by',
    ];

    protected $appends = ['numero_formattato'];

    protected function casts(): array
    {
        return [
            'data_registrazione' => 'date',
            'anno'               => 'integer',
            'numero'             => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relazioni
    // -------------------------------------------------------------------------

    /**
     * Entità collegata in modo polimorfico (es. Receipt, FatturaAttiva, ecc.).
     */
    public function linked(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Allegati collegati a questo protocollo.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Utente che ha creato il record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Numero di protocollo nel formato "ANNO/NNN" (numero zero-padded a 3 cifre).
     * Accessibile come $protocollo->numero_formattato
     */
    public function getNumeroFormattatoAttribute(): string
    {
        return $this->anno . '/' . str_pad((string) $this->numero, 3, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
    // Metodi statici
    // -------------------------------------------------------------------------

    /**
     * Calcola il prossimo numero progressivo per il tenant e l'anno indicati.
     * Esclude il global scope tenant per poter passare un tenant_id esplicito.
     */
    public static function nextNumero(int|string $tenantId, int $anno): int
    {
        $max = static::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('anno', $anno)
            ->max('numero');

        return $max === null ? 1 : (int) $max + 1;
    }
}
