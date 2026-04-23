<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Member::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codice_fiscale') && trim((string) $this->codice_fiscale) === '') {
            $this->merge(['codice_fiscale' => null]);
        }
    }

    public function rules(): array
    {
        /** @var Tenant|null $tenant */
        $tenant        = app()->bound('current_tenant') ? app('current_tenant') : null;
        $isCooperativa = $tenant?->isCooperativa() ?? false;
        $isCoopLavoro  = $isCooperativa && $tenant?->cooperative_type === 'lavoro';
        $isGiuridica   = $this->input('tipo_persona') === 'giuridica';

        $rules = [
            'member_type_id'     => 'required|exists:member_types,id',
            'numero_tessera'     => 'nullable|integer|min:1|unique:members,numero_tessera',
            'nome'               => $isGiuridica ? 'nullable|string|max:255' : 'required|string|max:255',
            'cognome'            => $isGiuridica ? 'nullable|string|max:255' : 'required|string|max:255',
            'email'              => 'nullable|email|max:255',
            'codice_fiscale'     => 'nullable|string|max:64',
            'data_nascita'       => 'nullable|date',
            'data_iscrizione'    => 'nullable|date',
            'stato'              => 'nullable|in:attivo,sospeso,cessato,aspirante',
            'presentare_domanda' => 'nullable|boolean',
            'indirizzo'          => 'nullable|string|max:255',
            'telefono'           => 'nullable|string|max:50',
            'note'               => 'nullable|string',
        ];

        if ($isCooperativa) {
            $rules['tipo_persona']       = 'required|in:fisica,giuridica';
            $rules['ragione_sociale']    = 'required_if:tipo_persona,giuridica|nullable|string|max:200';
            $rules['partita_iva']        = 'nullable|digits:11';
            $rules['referente_nome']     = 'nullable|string|max:100';
            $rules['referente_cognome']  = 'nullable|string|max:100';
            $rules['socio_sovventore']   = 'nullable|boolean';
            $rules['socio_onorario']     = 'nullable|boolean';
            $rules['data_ammissione_cda'] = 'nullable|date';

            if ($isCoopLavoro) {
                $rules['socio_lavoratore'] = 'nullable|boolean';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'ragione_sociale.required_if' => 'La ragione sociale è obbligatoria per persone giuridiche.',
            'partita_iva.digits'           => 'La partita IVA deve essere di 11 cifre.',
        ];
    }
}
