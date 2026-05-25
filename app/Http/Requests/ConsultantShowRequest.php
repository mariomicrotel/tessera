<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida i parametri di periodo per il cruscotto consulente.
 *
 * Parametri query opzionali:
 *  - period  : preset  (this_year | last_year | last_12m | this_month)  default: this_year
 *  - from    : YYYY-MM-DD  (usato solo se period = custom)
 *  - to      : YYYY-MM-DD  (usato solo se period = custom)
 *  - refresh : boolean  — forza ricalcolo anche se in cache
 */
class ConsultantShowRequest extends FormRequest
{
    public const PERIOD_THIS_YEAR  = 'this_year';
    public const PERIOD_LAST_YEAR  = 'last_year';
    public const PERIOD_LAST_12M   = 'last_12m';
    public const PERIOD_THIS_MONTH = 'this_month';
    public const PERIOD_CUSTOM     = 'custom';

    public const VALID_PERIODS = [
        self::PERIOD_THIS_YEAR,
        self::PERIOD_LAST_YEAR,
        self::PERIOD_LAST_12M,
        self::PERIOD_THIS_MONTH,
        self::PERIOD_CUSTOM,
    ];

    public function authorize(): bool
    {
        return true; // autorizzazione gestita dal middleware role:consultant nel gruppo route
    }

    public function rules(): array
    {
        return [
            'period'  => ['nullable', 'string', 'in:' . implode(',', self::VALID_PERIODS)],
            'from'    => ['nullable', 'date_format:Y-m-d', 'required_if:period,' . self::PERIOD_CUSTOM],
            'to'      => ['nullable', 'date_format:Y-m-d', 'required_if:period,' . self::PERIOD_CUSTOM, 'after_or_equal:from'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Restituisce il preset selezionato (default: this_year).
     */
    public function selectedPeriod(): string
    {
        return $this->input('period', self::PERIOD_THIS_YEAR);
    }

    /**
     * Trasforma il preset nelle date Carbon from/to corrispondenti.
     *
     * @return array{from: \Illuminate\Support\Carbon, to: \Illuminate\Support\Carbon}
     */
    public function resolvedDates(): array
    {
        $now = now();

        return match ($this->selectedPeriod()) {
            self::PERIOD_LAST_YEAR  => [
                'from' => $now->copy()->subYear()->startOfYear(),
                'to'   => $now->copy()->subYear()->endOfYear(),
            ],
            self::PERIOD_LAST_12M   => [
                'from' => $now->copy()->subYear()->startOfDay(),
                'to'   => $now->copy()->endOfDay(),
            ],
            self::PERIOD_THIS_MONTH => [
                'from' => $now->copy()->startOfMonth(),
                'to'   => $now->copy()->endOfMonth(),
            ],
            self::PERIOD_CUSTOM     => [
                'from' => \Illuminate\Support\Carbon::parse($this->input('from'))->startOfDay(),
                'to'   => \Illuminate\Support\Carbon::parse($this->input('to'))->endOfDay(),
            ],
            default /* this_year */ => [
                'from' => $now->copy()->startOfYear(),
                'to'   => $now->copy()->endOfYear(),
            ],
        };
    }

    /**
     * Forza ricalcolo senza cache.
     */
    public function forceRefresh(): bool
    {
        return (bool) $this->input('refresh', false);
    }
}
