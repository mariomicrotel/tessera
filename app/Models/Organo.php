<?php

namespace App\Models;

use App\Models\Settings;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organo extends Model
{
    use BelongsToTenant;

    protected $table = 'organi';

    protected $fillable = [
        'slug',
        'nome',
        'durata_mesi',
        'richiedi_elezioni_fine_mandato',
        'mandato_da',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'richiedi_elezioni_fine_mandato' => 'boolean',
            'mandato_da' => 'date',
        ];
    }

    public function caricheSociali(): HasMany
    {
        return $this->hasMany(CaricaSociale::class, 'organo_id')->orderBy('ordine');
    }

    public function elezioni(): HasMany
    {
        return $this->hasMany(Elezione::class, 'organo_id');
    }

    /**
     * Data inizio mandato: ultima elezione chiusa e non invalidata, oppure data costituzione associazione.
     */
    public function dataInizioMandato(): ?Carbon
    {
        $ultima = $this->elezioni()
            ->where('stato', Elezione::STATO_CHIUSA)
            ->whereNull('invalidata_at')
            ->orderByDesc('data_elezione')
            ->first();
        if ($ultima && $ultima->data_elezione) {
            return $ultima->data_elezione->copy();
        }
        $costituzione = Settings::get('data_costituzione_associazione', '');
        if ($costituzione === '') {
            return null;
        }
        try {
            return Carbon::parse($costituzione)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Scadenza mandato = data inizio + durata_mesi (se durata_mesi > 0).
     */
    public function mandatoScadenza(): ?Carbon
    {
        $durata = (int) $this->durata_mesi;
        if ($durata <= 0) {
            return null;
        }
        $inizio = $this->dataInizioMandato();
        if ($inizio === null) {
            return null;
        }

        return $inizio->copy()->addMonths($durata);
    }
}
