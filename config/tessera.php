<?php

/*
|--------------------------------------------------------------------------
| Configurazione moduli Tessera (feature flags)
|--------------------------------------------------------------------------
|
| Tessera è una piattaforma SaaS di supporto consulenziale, amministrativo,
| documentale e di compliance per cooperative, associazioni ed Enti del
| Terzo Settore. NON è un software di contabilità ufficiale.
|
| I flag in `modules` controllano la visibilità di interi sotto-sistemi:
|  - true  → menu, route e schermate disponibili
|  - false → middleware "module" blocca le route con 404 e il menu nasconde le voci
|
| Tutti i flag relativi a contabilità ufficiale (partita doppia, registri
| IVA, dichiarativi, payroll, bilancio civilistico computato) sono OFF di
| default: la contabilità ufficiale resta nel gestionale del commercialista.
|
| Override per ambiente: ogni flag accetta una variabile d'ambiente
| TESSERA_MODULE_<NAME> (es. TESSERA_MODULE_VAT_REGISTERS=true) per
| riabilitare un modulo senza modificare il codice.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Moduli (feature flags)
    |--------------------------------------------------------------------------
    */
    'modules' => [

        // === MANTENUTI (cuore della piattaforma) ===
        'members'                  => env('TESSERA_MODULE_MEMBERS', true),
        'volunteers'               => env('TESSERA_MODULE_VOLUNTEERS', true),
        'governance'               => env('TESSERA_MODULE_GOVERNANCE', true),
        'documents'                => env('TESSERA_MODULE_DOCUMENTS', true),
        'assemblies'               => env('TESSERA_MODULE_ASSEMBLIES', true),
        'ets_compliance'           => env('TESSERA_MODULE_ETS_COMPLIANCE', true),
        'runts_filing'             => env('TESSERA_MODULE_RUNTS_FILING', true),
        'consultant_workspace'     => env('TESSERA_MODULE_CONSULTANT_WORKSPACE', true),
        'administrative_movements' => env('TESSERA_MODULE_ADMINISTRATIVE_MOVEMENTS', true),
        'financial_dossier'        => env('TESSERA_MODULE_FINANCIAL_DOSSIER', true),
        'donations_register'       => env('TESSERA_MODULE_DONATIONS_REGISTER', true),

        // === DISATTIVATI: contabilità ufficiale (default OFF) ===
        'full_accounting'          => env('TESSERA_MODULE_FULL_ACCOUNTING', false),
        'double_entry_accounting'  => env('TESSERA_MODULE_DOUBLE_ENTRY_ACCOUNTING', false),
        'chart_of_accounts'        => env('TESSERA_MODULE_CHART_OF_ACCOUNTS', false),
        'vat_registers'            => env('TESSERA_MODULE_VAT_REGISTERS', false),
        'invoicing'                => env('TESSERA_MODULE_INVOICING', false),
        'tax_returns'              => env('TESSERA_MODULE_TAX_RETURNS', false),
        'payroll'                  => env('TESSERA_MODULE_PAYROLL', false),
        'civil_balance_sheet'      => env('TESSERA_MODULE_CIVIL_BALANCE_SHEET', false),
        'assets_and_depreciation'  => env('TESSERA_MODULE_ASSETS_AND_DEPRECIATION', false),
        'accruals_deferrals'       => env('TESSERA_MODULE_ACCRUALS_DEFERRALS', false),
        'accounting_reports'       => env('TESSERA_MODULE_ACCOUNTING_REPORTS', false),
        'fiscal_year_closing'      => env('TESSERA_MODULE_FISCAL_YEAR_CLOSING', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Etichette UI rinominate
    |--------------------------------------------------------------------------
    |
    | Mapping utilizzato dai menu/breadcrumb per sostituire la terminologia
    | "contabile" con quella "amministrativa/consulenziale".
    |
    */
    'labels' => [
        'contabilita'        => 'Amministrazione semplificata',
        'prima_nota'         => 'Movimenti amministrativi',
        'bilancio'           => 'Fascicolo bilancio/rendiconto',
        'piano_dei_conti'    => 'Categorie amministrative',
        'registrazioni'      => 'Documenti economici',
        'dichiarativi'       => 'Documenti per il consulente',
    ],

];
