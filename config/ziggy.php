<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route groups per contesto (guest vs autenticato)
    |--------------------------------------------------------------------------
    | Usato in app.blade.php con @routes(auth()->check() ? null : 'guest').
    | Per gli utenti non autenticati vengono esposte solo le route elencate
    | nel gruppo 'guest', riducendo l'information disclosure nel view-source.
    */
    'groups' => [
        'guest' => [
            'home',
            'login',
            'login.store',
            'logout',
            'register',
            'password.request',
            'password.reset',
            'password.email',
            'password.update',
            'password.confirm',
            'password.confirmation',
            'password.confirm.store',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'two-factor.login',
            'two-factor.login.store',
            'sanctum.csrf-cookie',
            'public.logo.show',
            'public.statuto.download',
            'public.rendiconto.download',
            'public.section-background.show',
            'donations.redirect',
        ],
    ],
];
