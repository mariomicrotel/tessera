<?php

return [
    'base_url' => env('OPENAPI_COMPANY_BASE_URL', 'https://company.openapi.com'),
    'token' => env('OPENAPI_COMPANY_TOKEN', ''),
    'daily_limit' => (int) env('OPENAPI_COMPANY_DAILY_LIMIT', 100),
    'warning_threshold' => 80,
    'cache_ttl_days' => 30,
    'timeout' => 15,

    /*
    |--------------------------------------------------------------------------
    | Costo per chiamata (€) per ogni endpoint OpenAPI Company.
    |--------------------------------------------------------------------------
    | Valori indicativi (da aggiornare in base al listino effettivo del piano
    | sottoscritto). Possono essere sovrascritti da DB:
    | Settings::set('openapi_company_costs', json_encode([...]))
    */
    'costs_per_call' => [
        'IT-start'         => (float) env('OPENAPI_COST_IT_START', 0.10),
        'IT-advanced'      => (float) env('OPENAPI_COST_IT_ADVANCED', 0.30),
        'IT-pec'           => (float) env('OPENAPI_COST_IT_PEC', 0.05),
        'IT-sdicode'       => (float) env('OPENAPI_COST_IT_SDICODE', 0.05),
        'IT-search'        => (float) env('OPENAPI_COST_IT_SEARCH', 0.50),
        'IT-ubo'           => (float) env('OPENAPI_COST_IT_UBO', 0.80),
        'IT-stakeholders'  => (float) env('OPENAPI_COST_IT_STAKEHOLDERS', 0.50),
        'IT-shareholders'  => (float) env('OPENAPI_COST_IT_SHAREHOLDERS', 0.50),
        'IT-full'          => (float) env('OPENAPI_COST_IT_FULL', 1.50),
        'IT-closed'        => (float) env('OPENAPI_COST_IT_CLOSED', 0.05),
        'IT-legalforms'    => 0.00,
        '_default'         => (float) env('OPENAPI_COST_DEFAULT', 0.20),
    ],

    /*
    | Soglia in € di costo cumulato giornaliero oltre la quale mostrare warning
    */
    'daily_cost_warning' => (float) env('OPENAPI_DAILY_COST_WARNING', 10.0),
    'monthly_cost_warning' => (float) env('OPENAPI_MONTHLY_COST_WARNING', 200.0),
];
