<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Verbale extends Model
{
    use BelongsToTenant;

    public const TIPO_CONSIGLIO = 'consiglio_direttivo';
    public const TIPO_ASSEMBLEA = 'assemblea';

    public const STATO_BOZZA = 'bozza';
    public const STATO_CONFERMATO = 'confermato';

    protected $table = 'verbali';

    protected $fillable = ['tipo', 'data', 'anno', 'titolo', 'contenuto', 'numero', 'stato'];

    protected $appends = ['tipo_label', 'in_bozza'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_CONSIGLIO => 'Consiglio direttivo',
            self::TIPO_ASSEMBLEA => 'Assemblea',
            default => $this->tipo ?? '—',
        };
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isBozza(): bool
    {
        return $this->stato === self::STATO_BOZZA;
    }

    public function getInBozzaAttribute(): bool
    {
        return $this->isBozza();
    }

    /** Prossimo numero progressivo per il dato tipo e anno (numerazione separata tra CD e Assemblea). */
    public static function prossimoNumeroPer(string $tipo, int $anno): int
    {
        $max = (int) static::query()
            ->where('tipo', $tipo)
            ->where('anno', $anno)
            ->max('numero');

        return $max + 1;
    }
}
