<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorizzazione gestita dal middleware role
    }

    public function rules(): array
    {
        $tenantId = app('current_tenant')->id;

        return [
            'name'                  => ['required', 'string', 'max:200'],
            'ragione_sociale'       => ['nullable', 'string', 'max:200'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'phone'                 => ['nullable', 'string', 'max:30'],
            'partita_iva'           => [
                'nullable', 'string', 'max:20',
                Rule::unique('suppliers', 'partita_iva')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'codice_fiscale'        => ['nullable', 'string', 'max:16'],
            'codice_sdi'            => ['nullable', 'string', 'max:7'],
            'pec'                   => ['nullable', 'email', 'max:150'],
            'indirizzo'             => ['nullable', 'string', 'max:200'],
            'cap'                   => ['nullable', 'string', 'max:5'],
            'citta'                 => ['nullable', 'string', 'max:100'],
            'provincia'             => ['nullable', 'string', 'max:2'],
            'nazione'               => ['nullable', 'string', 'size:2'],
            'iban'                  => ['nullable', 'string', 'max:34'],
            'condizioni_pagamento'  => ['required', Rule::in(array_keys(Supplier::CONDIZIONI_PAGAMENTO))],
            'categoria'             => ['required', Rule::in(array_keys(Supplier::CATEGORIE))],
            'note'                  => ['nullable', 'string'],
            'attivo'                => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                 => 'nome commerciale',
            'ragione_sociale'      => 'ragione sociale',
            'partita_iva'          => 'partita IVA',
            'codice_fiscale'       => 'codice fiscale',
            'codice_sdi'           => 'codice SDI',
            'condizioni_pagamento' => 'condizioni di pagamento',
        ];
    }
}
