<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sezioni disponibili per il sito pubblico
    |--------------------------------------------------------------------------
    | Id e label usati in backoffice (Impostazioni > Sito pubblico) e per
    | decidere quali blocchi mostrare nella single-page pubblica.
    */
    'sections' => [
        'hero' => 'Hero (intestazione)',
        'chi_siamo' => 'Chi siamo',
        'eventi' => 'Eventi',
        'rendiconti' => 'Rendiconti anni precedenti',
        'statuto' => 'Statuto',
        'modulo_iscrizione' => 'Modulo iscrizione',
        'footer' => 'Footer',
    ],

    'allowed_ids' => ['hero', 'chi_siamo', 'eventi', 'rendiconti', 'statuto', 'modulo_iscrizione', 'footer'],
];
