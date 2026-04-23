<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin Email
    |--------------------------------------------------------------------------
    |
    | L'utente con questa email ha accesso alla dashboard di amministrazione
    | della piattaforma SaaS e può gestire tutti i tenant.
    |
    */
    'super_admin_email' => env('SAAS_SUPER_ADMIN_EMAIL', 'admin@infotelsistemi.it'),

    /*
    |--------------------------------------------------------------------------
    | Piani disponibili
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price_monthly' => 0,
            'members_limit' => 25,
            'staff_limit' => 1,
            'storage_mb' => 100,
            'public_site' => false,
            'custom_domain' => false,
        ],
        'basic' => [
            'name' => 'Basic',
            'price_monthly' => 19,
            'members_limit' => 100,
            'staff_limit' => 3,
            'storage_mb' => 1024,
            'public_site' => true,
            'custom_domain' => false,
        ],
        'pro' => [
            'name' => 'Pro',
            'price_monthly' => 49,
            'members_limit' => 500,
            'staff_limit' => 10,
            'storage_mb' => 10240,
            'public_site' => true,
            'custom_domain' => true,
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price_monthly' => null, // su richiesta
            'members_limit' => null, // illimitati
            'staff_limit' => null,   // illimitati
            'storage_mb' => 51200,
            'public_site' => true,
            'custom_domain' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial
    |--------------------------------------------------------------------------
    */
    'trial_days' => 14,

    /*
    |--------------------------------------------------------------------------
    | Tenant Route Prefix
    |--------------------------------------------------------------------------
    */
    'tenant_route_prefix' => 'app',
    'public_route_prefix' => 'org',
];
