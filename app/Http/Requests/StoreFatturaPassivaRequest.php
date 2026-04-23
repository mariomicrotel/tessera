<?php

namespace App\Http\Requests;

use App\Models\FatturaPassiva;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFatturaPassivaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin', 'segreteria', 'contabile');
    }

    public function rules(): array
    {
        $tenant = app('current_tenant');

        return [
            // ── Testata ───────────────────────────────────────────────────
            'supplier_id'       => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenant->id)],
            'numero_fattura'    => ['required', 'string', 'max:50'],
            'data_fattura'      => ['required', 'date'],
            'data_ricezione'    => ['nullable', 'date'],
            'data_registrazione'=> ['required', 'date'],
            'data_scadenza'     => ['nullable', 'date', 'after_or_equal:data_fattura'],
            'esigibilita'       => ['required', Rule::in(array_keys(FatturaPassiva::ESIGIBILITA_LABEL))],
            'tipo_documento'    => ['required', Rule::in(array_keys(FatturaPassiva::TIPI_DOCUMENTO))],
            'note'              => ['nullable', 'string', 'max:2000'],

            // ── Righe ─────────────────────────────────────────────────────
            'righe'                             => ['required', 'array', 'min:1'],
            'righe.*.codice_iva_id'             => ['required', 'integer', Rule::exists('codici_iva', 'id')->where('tenant_id', $tenant->id)],
            'righe.*.conto_id'                  => ['nullable', 'integer', Rule::exists('conti', 'id')->where('tenant_id', $tenant->id)],
            'righe.*.descrizione'               => ['required', 'string', 'max:500'],
            'righe.*.quantita'                  => ['required', 'numeric', 'min:0.0001'],
            'righe.*.prezzo_unitario'           => ['required', 'numeric'],
            'righe.*.indetraibile_percentuale'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id'        => 'fornitore',
            'numero_fattura'     => 'numero fattura',
            'data_fattura'       => 'data fattura',
            'data_ricezione'     => 'data ricezione',
            'data_registrazione' => 'data registrazione',
            'data_scadenza'      => 'data scadenza',
            'esigibilita'        => 'esigibilità IVA',
            'tipo_documento'     => 'tipo documento',
            'righe'              => 'righe',
            'righe.*.codice_iva_id'           => 'codice IVA',
            'righe.*.descrizione'             => 'descrizione riga',
            'righe.*.quantita'                => 'quantità',
            'righe.*.prezzo_unitario'         => 'prezzo unitario',
            'righe.*.indetraibile_percentuale'=> '% IVA indetraibile',
        ];
    }

    /**
     * Restituisce solo i campi della testata (senza le righe).
     */
    public function testata(): array
    {
        return $this->only([
            'supplier_id',
            'numero_fattura',
            'data_fattura',
            'data_ricezione',
            'data_registrazione',
            'data_scadenza',
            'esigibilita',
            'tipo_documento',
            'note',
        ]);
    }
}
