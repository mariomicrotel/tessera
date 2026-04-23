<?php

namespace App\Services;

/**
 * Schema hardcoded del Rendiconto per cassa specifico per Cooperative.
 *
 * Analogo a `RendicontoCassaSchema` (Modello D ETS) ma con voci adatte al
 * bilancio di esercizio semplificato delle cooperative: include capitale
 * sociale, ristorni ai soci, riserve indivisibili, interessi passivi sul
 * prestito sociale, rimborso capitale a soci uscenti, ecc.
 *
 * Riferimenti normativi:
 *  - Codice civile art. 2545-sexies (ristorni)
 *  - Codice civile art. 2545-ter (riserve indivisibili)
 *  - Legge 59/1992 art. 11 (riserva legale 30% utili min)
 *  - TUIR art. 12 DPR 601/73 (agevolazioni IRES cooperative)
 *
 * Label utente con codice (es. "A.01 – Quote e contributi soci").
 */
class RendicontoCassaSchemaCooperativa
{
    // ── Codici utilizzati per prima nota automatica ──────────────────────────

    /** Entrata: quote capitale sociale versate nell'anno */
    public const CODE_CAPITALE_SOCIALE = 'A01';

    /** Entrata: ricavi da attività mutualistica (prestazioni/servizi) */
    public const CODE_PROVENTI_MUTUALISTICI = 'A02';

    /** Entrata: ricavi da attività commerciale verso terzi */
    public const CODE_PROVENTI_COMMERCIALI = 'A03';

    /** Uscita: interessi passivi sul prestito sociale dei soci */
    public const CODE_INTERESSI_PRESTITO_SOCIALE = 'B06';

    /** Uscita: ristorni deliberati ai soci (al netto della ritenuta fiscale) */
    public const CODE_RISTORNI_SOCI = 'B07';

    /** Uscita: accantonamento a riserva legale (min 30% utili, L. 59/1992) */
    public const CODE_RISERVA_LEGALE = 'B08';

    /** Uscita: accantonamento a riserva indivisibile (art. 2545-ter c.c.) */
    public const CODE_RISERVA_INDIVISIBILE = 'B09';

    /** Uscita: rimborso capitale a soci uscenti/receduti */
    public const CODE_RIMBORSO_CAPITALE_SOCI = 'B11';

    // ── Alias di compatibilità con `RendicontoCassaSchema` ───────────────────
    // Consentono ai controller di usare le stesse costanti passando attraverso
    // `RendicontoCassaSchemaResolver::class()`.

    /** Entrata: quota associativa/capitale (→ A01 Quote e contributi soci) */
    public const CODE_QUOTA = 'A01';

    /** Entrata: donazione/contributo (→ A04 Contributi pubblici e privati) */
    public const CODE_DONAZIONE = 'A04';

    /** Uscita: rimborsi spese ai soci/volontari (→ B02 Servizi e prestazioni esterne) */
    public const CODE_RIMBORSI = 'B02';

    // ── Cache interne ────────────────────────────────────────────────────────

    protected static ?array $accounts = null;

    protected static ?array $codeToInfo = null;

    protected static ?array $selectableVoices = null;

    protected static ?array $validCodes = null;

    protected static ?array $macroAreasForSelect = null;

    protected static ?array $selectableVoicesUscita = null;

    protected static ?array $selectableVoicesEntrata = null;

    protected static ?array $macroAreasForSelectUscita = null;

    protected static ?array $macroAreasForSelectEntrata = null;

    // ── API pubblica ─────────────────────────────────────────────────────────

    /**
     * Restituisce la struttura completa (macro_areas con children).
     * Schema hardcoded: una macro area per sezione (A=entrate, B=uscite).
     */
    public static function getAccounts(): array
    {
        if (self::$accounts !== null) {
            return self::$accounts;
        }

        self::$accounts = [
            // ── SEZIONE A: ENTRATE ────────────────────────────────────────
            [
                'code'    => 'COOP_A',
                'type'    => 'macro_area',
                'section' => 'INCOME',
                'area'    => 'A',
                'name'    => 'Entrate',
                'children' => [
                    [
                        'code'             => 'A01',
                        'type'             => 'income',
                        'name'             => 'Quote e contributi soci',
                        'description'      => 'Capitale sociale versato dai soci nell\'anno',
                        'ministerial_code' => 'A.01',
                    ],
                    [
                        'code'             => 'A02',
                        'type'             => 'income',
                        'name'             => 'Proventi da prestazioni e servizi',
                        'description'      => 'Ricavi da attività mutualistica verso soci',
                        'ministerial_code' => 'A.02',
                    ],
                    [
                        'code'             => 'A03',
                        'type'             => 'income',
                        'name'             => 'Proventi da attività commerciale',
                        'description'      => 'Ricavi da vendite e servizi a terzi non soci',
                        'ministerial_code' => 'A.03',
                    ],
                    [
                        'code'             => 'A04',
                        'type'             => 'income',
                        'name'             => 'Contributi pubblici e privati',
                        'description'      => 'Contributi da enti pubblici, privati, 5x1000',
                        'ministerial_code' => 'A.04',
                    ],
                    [
                        'code'             => 'A05',
                        'type'             => 'income',
                        'name'             => 'Rimborsi e recuperi',
                        'description'      => 'Rimborsi spese, risarcimenti, recuperi crediti',
                        'ministerial_code' => 'A.05',
                    ],
                    [
                        'code'             => 'A06',
                        'type'             => 'income',
                        'name'             => 'Interessi attivi e proventi finanziari',
                        'description'      => 'Interessi su conti correnti, investimenti finanziari',
                        'ministerial_code' => 'A.06',
                    ],
                    [
                        'code'             => 'A07',
                        'type'             => 'income',
                        'name'             => 'Rivalutazioni attivi',
                        'description'      => 'Rivalutazione di immobili, partecipazioni, beni strumentali',
                        'ministerial_code' => 'A.07',
                    ],
                    [
                        'code'             => 'A08',
                        'type'             => 'income',
                        'name'             => 'Proventi straordinari',
                        'description'      => 'Plusvalenze, sopravvenienze attive, eventi non ricorrenti',
                        'ministerial_code' => 'A.08',
                    ],
                    [
                        'code'             => 'A09',
                        'type'             => 'income',
                        'name'             => 'Utilizzo riserve',
                        'description'      => 'Prelievo da riserve indivisibili o disponibili',
                        'ministerial_code' => 'A.09',
                    ],
                ],
            ],

            // ── SEZIONE B: USCITE ─────────────────────────────────────────
            [
                'code'    => 'COOP_B',
                'type'    => 'macro_area',
                'section' => 'EXPENSES',
                'area'    => 'B',
                'name'    => 'Uscite',
                'children' => [
                    [
                        'code'             => 'B01',
                        'type'             => 'expense',
                        'name'             => 'Acquisto beni e materiali',
                        'description'      => 'Materie prime, sussidiarie, di consumo e di merci',
                        'ministerial_code' => 'B.01',
                    ],
                    [
                        'code'             => 'B02',
                        'type'             => 'expense',
                        'name'             => 'Servizi e prestazioni esterne',
                        'description'      => 'Consulenze, utenze, manutenzioni, servizi vari',
                        'ministerial_code' => 'B.02',
                    ],
                    [
                        'code'             => 'B03',
                        'type'             => 'expense',
                        'name'             => 'Personale',
                        'description'      => 'Salari, stipendi, contributi previdenziali e assistenziali',
                        'ministerial_code' => 'B.03',
                    ],
                    [
                        'code'             => 'B04',
                        'type'             => 'expense',
                        'name'             => 'Affitti e locazioni',
                        'description'      => 'Godimento beni di terzi, canoni di locazione e leasing',
                        'ministerial_code' => 'B.04',
                    ],
                    [
                        'code'             => 'B05',
                        'type'             => 'expense',
                        'name'             => 'Ammortamenti',
                        'description'      => 'In contabilità per cassa: corrisponde agli acquisti di beni strumentali',
                        'ministerial_code' => 'B.05',
                    ],
                    [
                        'code'             => 'B06',
                        'type'             => 'expense',
                        'name'             => 'Oneri finanziari',
                        'description'      => 'Interessi passivi prestito sociale, interessi bancari, commissioni',
                        'ministerial_code' => 'B.06',
                    ],
                    [
                        'code'             => 'B07',
                        'type'             => 'expense',
                        'name'             => 'Ristorni ai soci',
                        'description'      => 'Ristorni deliberati dall\'assemblea (con ritenuta fiscale del 30%)',
                        'ministerial_code' => 'B.07',
                    ],
                    [
                        'code'             => 'B08',
                        'type'             => 'expense',
                        'name'             => 'Accantonamento riserva legale',
                        'description'      => 'Quota minima 30% degli utili (L. 59/1992 art. 11)',
                        'ministerial_code' => 'B.08',
                    ],
                    [
                        'code'             => 'B09',
                        'type'             => 'expense',
                        'name'             => 'Accantonamento riserva indivisibile',
                        'description'      => 'Quota minima 3% degli utili ai fondi mutualistici (art. 2545-ter c.c.)',
                        'ministerial_code' => 'B.09',
                    ],
                    [
                        'code'             => 'B10',
                        'type'             => 'expense',
                        'name'             => 'Tasse e imposte',
                        'description'      => 'IRAP, IRES agevolata (TUIR art. 12 DPR 601/73), imposte locali',
                        'ministerial_code' => 'B.10',
                    ],
                    [
                        'code'             => 'B11',
                        'type'             => 'expense',
                        'name'             => 'Rimborso capitale ai soci uscenti',
                        'description'      => 'Liquidazione quote a soci receduti, esclusi, deceduti',
                        'ministerial_code' => 'B.11',
                    ],
                    [
                        'code'             => 'B12',
                        'type'             => 'expense',
                        'name'             => 'Uscite straordinarie',
                        'description'      => 'Minusvalenze, sopravvenienze passive, eventi non ricorrenti',
                        'ministerial_code' => 'B.12',
                    ],
                ],
            ],
        ];

        return self::$accounts;
    }

    /**
     * Restituisce la macro area con nome uguale a $name (per lookup da payload PDF).
     */
    public static function getMacroByName(string $name): ?array
    {
        foreach (self::getAccounts() as $macro) {
            if (($macro['name'] ?? '') === $name) {
                return $macro;
            }
        }

        return null;
    }

    /**
     * Struttura per doppio menu: macro aree con children (code, name, ministerial_code, type).
     */
    public static function getMacroAreasForSelect(): array
    {
        if (self::$macroAreasForSelect !== null) {
            return self::$macroAreasForSelect;
        }

        $result = [];
        foreach (self::getAccounts() as $macro) {
            $area = $macro['area'] ?? null;
            $macroCode = $macro['code'] ?? '';
            $macroName = $macro['name'] ?? '';
            $children = [];
            $prefix = $area !== null ? $area : 'COOP';
            $idx = 0;
            foreach ($macro['children'] ?? [] as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT));
                    $children[] = [
                        'code'             => $child['code'],
                        'name'             => $child['name'],
                        'ministerial_code' => $ministerialCode,
                        'type'             => $type,
                        'description'      => $child['description'] ?? '',
                    ];
                }
            }
            $result[] = [
                'code'     => $macroCode,
                'name'     => $macroName,
                'area'     => $area,
                'children' => $children,
            ];
        }
        self::$macroAreasForSelect = $result;

        return self::$macroAreasForSelect;
    }

    /**
     * Elenco piatto delle voci selezionabili (solo type income/expense).
     * Ogni elemento: code, name, ministerial_code, tipo (entrata|uscita), macro_name, description.
     */
    public static function getSelectableVoices(): array
    {
        if (self::$selectableVoices !== null) {
            return self::$selectableVoices;
        }

        $list = [];
        foreach (self::getAccounts() as $macro) {
            $macroName = $macro['name'] ?? '';
            $area = $macro['area'] ?? null;
            $prefix = $area !== null ? $area : 'COOP';
            $children = $macro['children'] ?? [];
            $idx = 0;
            foreach ($children as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT));
                    $list[] = [
                        'code'             => $child['code'],
                        'name'             => $child['name'],
                        'ministerial_code' => $ministerialCode,
                        'tipo'             => $type === 'income' ? 'entrata' : 'uscita',
                        'macro_name'       => $macroName,
                        'description'      => $child['description'] ?? '',
                    ];
                }
            }
        }
        usort($list, fn ($a, $b) => strcmp($a['code'], $b['code']));
        self::$selectableVoices = $list;

        return self::$selectableVoices;
    }

    /**
     * Elenco piatto delle voci selezionabili di tipo uscita (per pagina Spese cooperativa).
     */
    public static function getSelectableVoicesUscita(): array
    {
        if (self::$selectableVoicesUscita !== null) {
            return self::$selectableVoicesUscita;
        }

        self::$selectableVoicesUscita = array_values(array_filter(
            self::getSelectableVoices(),
            fn ($v) => ($v['tipo'] ?? '') === 'uscita'
        ));

        return self::$selectableVoicesUscita;
    }

    /**
     * Codici validi solo per voci di uscita (per validazione Spese cooperativa).
     */
    public static function getValidCodesUscita(): array
    {
        return array_map(fn ($v) => $v['code'], self::getSelectableVoicesUscita());
    }

    /**
     * Elenco piatto delle voci selezionabili di tipo entrata.
     */
    public static function getSelectableVoicesEntrata(): array
    {
        if (self::$selectableVoicesEntrata !== null) {
            return self::$selectableVoicesEntrata;
        }

        self::$selectableVoicesEntrata = array_values(array_filter(
            self::getSelectableVoices(),
            fn ($v) => ($v['tipo'] ?? '') === 'entrata'
        ));

        return self::$selectableVoicesEntrata;
    }

    /**
     * Codici validi solo per voci di entrata (per validazione incasso tipo altro cooperativa).
     */
    public static function getValidCodesEntrata(): array
    {
        return array_map(fn ($v) => $v['code'], self::getSelectableVoicesEntrata());
    }

    /**
     * Macro aree con solo children di tipo expense.
     */
    public static function getMacroAreasForSelectUscita(): array
    {
        if (self::$macroAreasForSelectUscita !== null) {
            return self::$macroAreasForSelectUscita;
        }

        $result = [];
        foreach (self::getMacroAreasForSelect() as $macro) {
            $children = array_values(array_filter(
                $macro['children'] ?? [],
                fn ($c) => ($c['type'] ?? '') === 'expense'
            ));
            if (count($children) > 0) {
                $result[] = [
                    'code'     => $macro['code'],
                    'name'     => $macro['name'],
                    'area'     => $macro['area'] ?? null,
                    'children' => $children,
                ];
            }
        }
        self::$macroAreasForSelectUscita = $result;

        return self::$macroAreasForSelectUscita;
    }

    /**
     * Macro aree con solo children di tipo income.
     */
    public static function getMacroAreasForSelectEntrata(): array
    {
        if (self::$macroAreasForSelectEntrata !== null) {
            return self::$macroAreasForSelectEntrata;
        }

        $result = [];
        foreach (self::getMacroAreasForSelect() as $macro) {
            $children = array_values(array_filter(
                $macro['children'] ?? [],
                fn ($c) => ($c['type'] ?? '') === 'income'
            ));
            if (count($children) > 0) {
                $result[] = [
                    'code'     => $macro['code'],
                    'name'     => $macro['name'],
                    'area'     => $macro['area'] ?? null,
                    'children' => $children,
                ];
            }
        }
        self::$macroAreasForSelectEntrata = $result;

        return self::$macroAreasForSelectEntrata;
    }

    /**
     * Dato un code, restituisce info: code, name, tipo, macro_name, ministerial_code, description.
     */
    public static function getInfoByCode(string $code): ?array
    {
        if (self::$codeToInfo === null) {
            self::buildCodeToInfo();
        }

        return self::$codeToInfo[$code] ?? null;
    }

    /**
     * Label per visualizzazione utente: "{ministerial_code} – {name}".
     */
    public static function getLabelForCode(string $code): string
    {
        $info = self::getInfoByCode($code);
        if (! $info) {
            return $code;
        }
        $ministerialCode = $info['ministerial_code'] ?? '';
        $name = $info['name'] ?? '';

        return $ministerialCode !== '' ? $ministerialCode . ' – ' . $name : $name;
    }

    /**
     * Descrizione estesa (help testuale) per un code.
     */
    public static function getDescriptionForCode(string $code): string
    {
        $info = self::getInfoByCode($code);

        return $info['description'] ?? '';
    }

    /**
     * Elenco di tutti i codici validi.
     */
    public static function getValidCodes(): array
    {
        if (self::$validCodes !== null) {
            return self::$validCodes;
        }

        self::$validCodes = array_map(fn ($v) => $v['code'], self::getSelectableVoices());

        return self::$validCodes;
    }

    public static function isValidCode(string $code): bool
    {
        return in_array($code, self::getValidCodes(), true);
    }

    // ── Helpers interni ──────────────────────────────────────────────────────

    protected static function buildCodeToInfo(): void
    {
        self::$codeToInfo = [];
        foreach (self::getAccounts() as $macro) {
            $macroName = $macro['name'] ?? '';
            $area = $macro['area'] ?? null;
            $prefix = $area !== null ? $area : 'COOP';
            $children = $macro['children'] ?? [];
            $idx = 0;
            foreach ($children as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . str_pad((string) $idx, 2, '0', STR_PAD_LEFT));
                    $tipo = $type === 'income' ? 'entrata' : 'uscita';
                    self::$codeToInfo[$child['code']] = [
                        'code'             => $child['code'],
                        'name'             => $child['name'],
                        'tipo'             => $tipo,
                        'macro_name'       => $macroName,
                        'ministerial_code' => $ministerialCode,
                        'description'      => $child['description'] ?? '',
                    ];
                }
            }
        }
    }
}
