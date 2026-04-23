<?php

/**
 * Organi e cariche sono definiti qui; il seeder sincronizza il DB.
 *
 * Per aggiungere un nuovo organo:
 * 1) Aggiungere un elemento all'array sotto con slug (univoco), nome e cariche.
 *    Ogni carica: nome, ordine, multiplo (opzionale, default false). Se multiplo=true la carica può avere più assegnatari (es. Consigliere).
 * 2) Eseguire: php artisan db:seed --class=OrganiHardcodedSeeder
 *
 * L'organo comparirà in Organi/Index, Elezioni (select/filtro) e nel select "Assegna carica" in Members/Show.
 */

return [
    'organi' => [
        [
            'slug' => 'consiglio_direttivo',
            'nome' => 'Consiglio direttivo',
            'durata_mesi' => 36,
            'richiedi_elezioni_fine_mandato' => true,
            'cariche' => [
                ['nome' => 'Presidente', 'ordine' => 1, 'multiplo' => false],
                ['nome' => 'Vicepresidente', 'ordine' => 2, 'multiplo' => false],
                ['nome' => 'Tesoriere', 'ordine' => 3, 'multiplo' => false],
                ['nome' => 'Segretario', 'ordine' => 4, 'multiplo' => false],
                ['nome' => 'Consigliere', 'ordine' => 5, 'multiplo' => true],
            ],
        ],
    ],
];
