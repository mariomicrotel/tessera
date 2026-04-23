<?php

namespace App\Services;

/**
 * Schema hardcoded Modello D – Rendiconto per cassa (GU 18-04-2020).
 * Fornisce voci utilizzabili in prima nota e struttura per report/PDF.
 * Label utente con codice ministeriale (es. A.1 – Nome voce).
 */
class RendicontoCassaSchema
{
    /** Codice voce per prima nota automatica: quote associative */
    public const CODE_QUOTA = 'INC_A_1';

    /** Codice voce per prima nota automatica: erogazioni liberali */
    public const CODE_DONAZIONE = 'INC_A_4';

    /** Codice voce per prima nota automatica: rimborsi spese */
    public const CODE_RIMBORSI = 'EXP_A_5';

    protected static ?array $accounts = null;

    protected static ?array $codeToInfo = null;

    protected static ?array $selectableVoices = null;

    protected static ?array $validCodes = null;

    protected static ?array $macroAreasForSelect = null;

    protected static ?array $selectableVoicesUscita = null;

    protected static ?array $selectableVoicesEntrata = null;

    protected static ?array $macroAreasForSelectUscita = null;

    /**
     * Restituisce la struttura completa (macro_areas con children).
     */
    public static function getAccounts(): array
    {
        if (self::$accounts === null) {
            self::$accounts = config('rendiconto_cassa.accounts', []);
        }

        return self::$accounts;
    }

    /**
     * Restituisce la macro area con nome uguale a $name (per lookup da payload PDF).
     * Restituisce: name, area, section, code, children, ecc.
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
            $prefix = $area !== null ? $area : (str_starts_with($macroCode, 'INV') ? 'INV' : 'NOT');
            $idx = 0;
            foreach ($macro['children'] ?? [] as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . $idx);
                    $children[] = [
                        'code' => $child['code'],
                        'name' => $child['name'],
                        'ministerial_code' => $ministerialCode,
                        'type' => $type,
                    ];
                }
            }
            $result[] = [
                'code' => $macroCode,
                'name' => $macroName,
                'area' => $area,
                'children' => $children,
            ];
        }
        self::$macroAreasForSelect = $result;

        return self::$macroAreasForSelect;
    }

    /**
     * Elenco piatto delle voci selezionabili (solo type income/expense).
     * Ogni elemento: code, name, ministerial_code, tipo (entrata|uscita), macro_name.
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
            $prefix = $area !== null ? $area : (str_starts_with($macro['code'] ?? '', 'INV') ? 'INV' : 'NOT');
            $children = $macro['children'] ?? [];
            $idx = 0;
            foreach ($children as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . $idx);
                    $list[] = [
                        'code' => $child['code'],
                        'name' => $child['name'],
                        'ministerial_code' => $ministerialCode,
                        'tipo' => $type === 'income' ? 'entrata' : 'uscita',
                        'macro_name' => $macroName,
                    ];
                }
            }
        }
        usort($list, fn ($a, $b) => strcmp($a['code'], $b['code']));
        self::$selectableVoices = $list;

        return self::$selectableVoices;
    }

    /**
     * Elenco piatto delle voci selezionabili di tipo uscita (per pagina Spese).
     * Stessa struttura di getSelectableVoices() ma filtrata a tipo === 'uscita'.
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
     * Codici validi solo per voci di uscita (per validazione Spese).
     */
    public static function getValidCodesUscita(): array
    {
        return array_map(fn ($v) => $v['code'], self::getSelectableVoicesUscita());
    }

    /**
     * Elenco piatto delle voci selezionabili di tipo entrata (per incasso generico).
     * Stessa struttura di getSelectableVoices() ma filtrata a tipo === 'entrata'.
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
     * Codici validi solo per voci di entrata (per validazione incasso tipo altro).
     */
    public static function getValidCodesEntrata(): array
    {
        return array_map(fn ($v) => $v['code'], self::getSelectableVoicesEntrata());
    }

    /**
     * Macro aree con solo children di tipo expense (per select a due livelli nella pagina Spese).
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
                    'code' => $macro['code'],
                    'name' => $macro['name'],
                    'area' => $macro['area'] ?? null,
                    'children' => $children,
                ];
            }
        }
        self::$macroAreasForSelectUscita = $result;

        return self::$macroAreasForSelectUscita;
    }

    /**
     * Dato un code, restituisce info: code, name, tipo (entrata|uscita), macro_name, ministerial_code.
     */
    public static function getInfoByCode(string $code): ?array
    {
        if (self::$codeToInfo === null) {
            self::buildCodeToInfo();
        }

        return self::$codeToInfo[$code] ?? null;
    }

    /**
     * Label per visualizzazione utente: "{ministerial_code} – {name}" (es. "A.1 – Materie prime...").
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
     * Elenco di tutti i codici validi (per validazione).
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

    protected static function buildCodeToInfo(): void
    {
        self::$codeToInfo = [];
        foreach (self::getAccounts() as $macro) {
            $macroName = $macro['name'] ?? '';
            $area = $macro['area'] ?? null;
            $prefix = $area !== null ? $area : (str_starts_with($macro['code'] ?? '', 'INV') ? 'INV' : 'NOT');
            $children = $macro['children'] ?? [];
            $idx = 0;
            foreach ($children as $child) {
                $type = $child['type'] ?? '';
                if ($type === 'income' || $type === 'expense') {
                    $idx++;
                    $ministerialCode = $child['ministerial_code'] ?? ($prefix . '.' . $idx);
                    $tipo = $type === 'income' ? 'entrata' : 'uscita';
                    self::$codeToInfo[$child['code']] = [
                        'code' => $child['code'],
                        'name' => $child['name'],
                        'tipo' => $tipo,
                        'macro_name' => $macroName,
                        'ministerial_code' => $ministerialCode,
                    ];
                }
            }
        }
    }
}
