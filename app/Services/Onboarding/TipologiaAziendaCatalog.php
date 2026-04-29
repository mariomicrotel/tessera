<?php

namespace App\Services\Onboarding;

use App\Models\Tenant;

/**
 * Catalogo centrale delle tipologie aziendali.
 *
 * Per ogni forma giuridica definisce:
 *  - moduli abilitati (sidebar + funzionalità)
 *  - template piano dei conti da installare
 *  - schema di bilancio applicabile
 *  - dimensioni bilancio disponibili
 *  - regimi contabili disponibili
 *  - regimi IVA disponibili
 *  - campi obbligatori aggiuntivi nel wizard
 *
 * Pattern: classe con soli metodi statici (value object / catalog).
 * Non ha stato, non dipende da DB — testabile puro.
 */
final class TipologiaAziendaCatalog
{
    // ─────────────────────────────────────────────────────────────────────────
    // Moduli (identificatori stringa usati da MenuBuilder e middleware)
    // ─────────────────────────────────────────────────────────────────────────

    const MOD_PRIMA_NOTA         = 'prima_nota';
    const MOD_FATTURE_ATTIVE     = 'fatture_attive';
    const MOD_FATTURE_PASSIVE    = 'fatture_passive';
    const MOD_INCASSI            = 'incassi';
    const MOD_IVA                = 'iva';             // liquidazione, registri IVA
    const MOD_CESPITI            = 'cespiti';
    const MOD_SCADENZE           = 'scadenze';
    const MOD_RATEI_RISCONTI     = 'ratei_risconti';
    const MOD_ESERCIZIO          = 'esercizio_contabile';
    const MOD_LIBRO_GIORNALE     = 'libro_giornale';
    const MOD_BILANCIO_CEE       = 'bilancio_cee';
    const MOD_CONTO_ECONOMICO    = 'conto_economico';
    const MOD_RENDICONTO_CASSA   = 'rendiconto_cassa'; // ETS: rendiconto entrate/uscite
    const MOD_SOCI               = 'soci';             // gestione soci / libro soci
    const MOD_QUOTE_SOCIALI      = 'quote_sociali';    // scadenzario quote soci
    const MOD_COOPERATIVE        = 'cooperative';      // ristorni, prestito sociale, capitale
    const MOD_ETS                = 'ets';              // erogazioni liberali, relazione missione
    const MOD_COMPENSI_TERZI     = 'compensi_terzi';  // ritenute d'acconto
    const MOD_F24                = 'f24';
    const MOD_CENTRI_COSTO       = 'centri_costo';
    const MOD_MAGAZZINO          = 'magazzino';
    const MOD_SCADENZARIO_SOCI   = 'scadenzario_soci';

    // ─────────────────────────────────────────────────────────────────────────
    // Template piano dei conti
    // ─────────────────────────────────────────────────────────────────────────

    const PDC_ETS                = 'ets';
    const PDC_COOPERATIVA        = 'cooperativa';
    const PDC_CEE_ORDINARIO      = 'cee_ordinario';
    const PDC_CEE_ABBREVIATO     = 'cee_abbreviato';
    const PDC_PROFESSIONISTA     = 'professionista';
    const PDC_FORFETTARIO        = 'forfettario';
    const PDC_ASSOCIAZIONE       = 'associazione';

    // ─────────────────────────────────────────────────────────────────────────
    // Schema bilancio
    // ─────────────────────────────────────────────────────────────────────────

    const SCHEMA_ETS_D           = 'ets_d';           // D.M. 5/3/2020 Moduli A/B/C/D
    const SCHEMA_COOPERATIVA     = 'cooperativa';     // CEE + sezioni cooperative
    const SCHEMA_CEE_ORDINARIO   = 'cee_ordinario';   // artt. 2424/2425 completo
    const SCHEMA_CEE_ABBREVIATO  = 'cee_abbreviato';  // art. 2435-bis
    const SCHEMA_CEE_MICRO       = 'cee_micro';       // art. 2435-ter
    const SCHEMA_RENDICONTO      = 'rendiconto';      // rendiconto entrate/uscite (non-profit)
    const SCHEMA_NESSUNO         = 'nessuno';         // forfettari, autonomi

    // ─────────────────────────────────────────────────────────────────────────
    // Matrice principale: forma_giuridica → profilo completo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce il profilo completo per una forma giuridica.
     *
     * @return array{
     *   moduli: string[],
     *   piano_conti_template: string,
     *   schema_bilancio_default: string,
     *   dimensioni_disponibili: string[],
     *   regimi_contabili: string[],
     *   regimi_iva: string[],
     *   campi_obbligatori: string[],
     *   gruppo: string,
     *   descrizione_breve: string,
     * }
     */
    public static function profilo(string $formaGiuridica): array
    {
        return self::matrice()[$formaGiuridica] ?? self::profiloDefault();
    }

    /**
     * Restituisce l'array dei moduli abilitati per una configurazione.
     * Se dimensione/regime specifici non cambiano i moduli, restituisce
     * quelli base della forma giuridica; override possibili tramite
     * moduliOverride().
     */
    public static function moduliAbilitati(
        string $formaGiuridica,
        string $dimensione = Tenant::DIM_NON_APPLICABILE,
        string $regimeContabile = Tenant::RC_NON_APPLICABILE
    ): array {
        $moduli = self::profilo($formaGiuridica)['moduli'];

        // Override per dimensione: il forfettario non ha IVA, libro giornale, bilancio CEE
        if ($regimeContabile === Tenant::RC_FORFETTARIO
            || $formaGiuridica === Tenant::FG_FORFETTARIO) {
            $moduli = array_filter($moduli, fn ($m) => ! in_array($m, [
                self::MOD_IVA,
                self::MOD_LIBRO_GIORNALE,
                self::MOD_BILANCIO_CEE,
                self::MOD_RATEI_RISCONTI,
            ]));
        }

        // Override per dimensione micro: niente ratei/risconti obbligatori
        if ($dimensione === Tenant::DIM_MICRO) {
            $moduli = array_filter($moduli, fn ($m) => $m !== self::MOD_CENTRI_COSTO);
        }

        return array_values($moduli);
    }

    /** Restituisce il template piano dei conti per una forma giuridica. */
    public static function pianoContiTemplate(string $formaGiuridica): string
    {
        return self::profilo($formaGiuridica)['piano_conti_template'];
    }

    /** Restituisce lo schema bilancio default per una forma giuridica. */
    public static function schemaBilancioDefault(string $formaGiuridica): string
    {
        return self::profilo($formaGiuridica)['schema_bilancio_default'];
    }

    /** Restituisce lo schema bilancio effettivo data la dimensione. */
    public static function schemaBilancio(
        string $formaGiuridica,
        string $dimensione = Tenant::DIM_NON_APPLICABILE
    ): string {
        // ETS → sempre schema ETS-D indipendentemente dalla dimensione
        if (str_starts_with($formaGiuridica, 'ets_')) {
            return self::SCHEMA_ETS_D;
        }
        // Cooperative → sempre schema cooperativa
        if (str_starts_with($formaGiuridica, 'coop_')) {
            return self::SCHEMA_COOPERATIVA;
        }
        // Forfettario / autonomi senza bilancio
        if (in_array($formaGiuridica, [
            Tenant::FG_FORFETTARIO, Tenant::FG_SS,
        ])) {
            return self::SCHEMA_NESSUNO;
        }
        // Professionisti → rendiconto entrate/uscite
        if (in_array($formaGiuridica, [
            Tenant::FG_LIBERO_PROF, Tenant::FG_DITTA_IND, Tenant::FG_STUDIO_PROF,
        ])) {
            return match ($dimensione) {
                Tenant::DIM_ORDINARIO_CEE => self::SCHEMA_CEE_ORDINARIO,
                default                   => self::SCHEMA_RENDICONTO,
            };
        }
        // Società → mappa dimensione → schema CEE
        return match ($dimensione) {
            Tenant::DIM_MICRO        => self::SCHEMA_CEE_MICRO,
            Tenant::DIM_ABBREVIATO   => self::SCHEMA_CEE_ABBREVIATO,
            Tenant::DIM_ORDINARIO_CEE => self::SCHEMA_CEE_ORDINARIO,
            default                  => self::SCHEMA_CEE_ORDINARIO,
        };
    }

    /** Restituisce le dimensioni bilancio disponibili per una forma giuridica. */
    public static function dimensioniDisponibili(string $formaGiuridica): array
    {
        return self::profilo($formaGiuridica)['dimensioni_disponibili'];
    }

    /** Restituisce i regimi contabili disponibili per una forma giuridica. */
    public static function regimiContabiliDisponibili(string $formaGiuridica): array
    {
        return self::profilo($formaGiuridica)['regimi_contabili'];
    }

    /** Restituisce i regimi IVA disponibili per una forma giuridica. */
    public static function regimiIvaDisponibili(string $formaGiuridica): array
    {
        return self::profilo($formaGiuridica)['regimi_iva'];
    }

    /** Restituisce i campi obbligatori aggiuntivi del wizard per questa forma. */
    public static function campiObbligatori(string $formaGiuridica): array
    {
        return self::profilo($formaGiuridica)['campi_obbligatori'];
    }

    /** Restituisce il gruppo di appartenenza (usato per raggruppare nel wizard Step1). */
    public static function gruppo(string $formaGiuridica): string
    {
        return self::profilo($formaGiuridica)['gruppo'];
    }

    /**
     * Restituisce tutte le forme giuridiche raggruppate per categoria.
     * Usato da Step1 del wizard per costruire il selettore a card.
     */
    public static function tutteRaggruppate(): array
    {
        $gruppi = [];
        foreach (self::matrice() as $fg => $profilo) {
            $gruppi[$profilo['gruppo']][] = [
                'value'       => $fg,
                'label'       => Tenant::formaGiuridicaLabels()[$fg] ?? $fg,
                'descrizione' => $profilo['descrizione_breve'],
            ];
        }
        return $gruppi;
    }

    /**
     * Verifica se un modulo specifico è abilitato per una configurazione.
     */
    public static function moduloAbilitato(
        string $formaGiuridica,
        string $modulo,
        string $dimensione = Tenant::DIM_NON_APPLICABILE,
        string $regimeContabile = Tenant::RC_NON_APPLICABILE
    ): bool {
        return in_array(
            $modulo,
            self::moduliAbilitati($formaGiuridica, $dimensione, $regimeContabile)
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Matrice interna
    // ─────────────────────────────────────────────────────────────────────────

    private static function matrice(): array
    {
        // Moduli comuni a quasi tutti gli enti con contabilità ordinaria
        $moduliBaseContabilita = [
            self::MOD_PRIMA_NOTA,
            self::MOD_FATTURE_ATTIVE,
            self::MOD_FATTURE_PASSIVE,
            self::MOD_INCASSI,
            self::MOD_IVA,
            self::MOD_CESPITI,
            self::MOD_SCADENZE,
            self::MOD_RATEI_RISCONTI,
            self::MOD_ESERCIZIO,
            self::MOD_LIBRO_GIORNALE,
            self::MOD_COMPENSI_TERZI,
            self::MOD_F24,
        ];

        // Moduli bilancio CEE (per società)
        $moduliBilancioCee = [
            self::MOD_BILANCIO_CEE,
            self::MOD_CONTO_ECONOMICO,
        ];

        return [

            // ── ETS ──────────────────────────────────────────────────────

            Tenant::FG_ETS_ODV => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_ETS,
                ]),
                'piano_conti_template'    => self::PDC_ETS,
                'schema_bilancio_default' => self::SCHEMA_ETS_D,
                'dimensioni_disponibili'  => [Tenant::DIM_ETS_D],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'ETS — Enti del Terzo Settore',
                'descrizione_breve'       => 'OdV — gestione volontariato, libro soci, erogazioni liberali',
            ],

            Tenant::FG_ETS_APS => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_ETS,
                ]),
                'piano_conti_template'    => self::PDC_ETS,
                'schema_bilancio_default' => self::SCHEMA_ETS_D,
                'dimensioni_disponibili'  => [Tenant::DIM_ETS_D],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'ETS — Enti del Terzo Settore',
                'descrizione_breve'       => 'APS — promozione sociale, soci, erogazioni liberali',
            ],

            Tenant::FG_ETS_FONDAZIONE => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_ETS,
                    self::MOD_BILANCIO_CEE,
                ]),
                'piano_conti_template'    => self::PDC_ETS,
                'schema_bilancio_default' => self::SCHEMA_ETS_D,
                'dimensioni_disponibili'  => [Tenant::DIM_ETS_D],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
                'gruppo'                  => 'ETS — Enti del Terzo Settore',
                'descrizione_breve'       => 'Fondazione ETS — gestione patrimoni e erogazioni',
            ],

            Tenant::FG_ETS_GENERICO => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_ETS,
                ]),
                'piano_conti_template'    => self::PDC_ETS,
                'schema_bilancio_default' => self::SCHEMA_ETS_D,
                'dimensioni_disponibili'  => [Tenant::DIM_ETS_D],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'ETS — Enti del Terzo Settore',
                'descrizione_breve'       => 'Altro ente ETS (ex ONLUS, ODV in transizione, ecc.)',
            ],

            // ── Associazione non ETS ──────────────────────────────────────

            Tenant::FG_ASS_NON_ETS => [
                'moduli' => [
                    self::MOD_PRIMA_NOTA,
                    self::MOD_INCASSI,
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_SCADENZE,
                    self::MOD_F24,
                ],
                'piano_conti_template'    => self::PDC_ASSOCIAZIONE,
                'schema_bilancio_default' => self::SCHEMA_RENDICONTO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE],
                'regimi_contabili'        => [Tenant::RC_NON_APPLICABILE, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_NON_APPLICABILE, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'Associazioni e fondazioni',
                'descrizione_breve'       => 'Associazione non iscritta al RUNTS',
            ],

            Tenant::FG_FOND_NON_ETS => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_RENDICONTO_CASSA,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_BILANCIO_CEE,
                ]),
                'piano_conti_template'    => self::PDC_ASSOCIAZIONE,
                'schema_bilancio_default' => self::SCHEMA_RENDICONTO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
                'gruppo'                  => 'Associazioni e fondazioni',
                'descrizione_breve'       => 'Fondazione non ETS',
            ],

            // ── Cooperative ───────────────────────────────────────────────

            Tenant::FG_COOP_LAVORO => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_CENTRI_COSTO,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO, Tenant::DIM_MICRO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa di lavoro — ristorni, prestito sociale, capitale',
            ],

            Tenant::FG_COOP_SOCIALE_A => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_ETS,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO, Tenant::DIM_MICRO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa sociale tipo A — servizi socio-sanitari',
            ],

            Tenant::FG_COOP_SOCIALE_B => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_ETS,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO, Tenant::DIM_MICRO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa sociale tipo B — inserimento lavorativo',
            ],

            Tenant::FG_COOP_AGRICOLA => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO, Tenant::DIM_MICRO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_AGRICOLO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa agricola — regime IVA agricolo disponibile',
            ],

            Tenant::FG_COOP_CONSORTILE => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_COOPERATIVE,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa consortile — aggregazione tra cooperative',
            ],

            Tenant::FG_COOP_CONSUMO => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa di consumo / distribuzione',
            ],

            Tenant::FG_COOP_ABITAZIONE => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa di abitazione',
            ],

            Tenant::FG_COOP_COMUNITA => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_SOCI,
                    self::MOD_QUOTE_SOCIALI,
                    self::MOD_SCADENZARIO_SOCI,
                    self::MOD_COOPERATIVE,
                    self::MOD_ETS,
                ]),
                'piano_conti_template'    => self::PDC_COOPERATIVA,
                'schema_bilancio_default' => self::SCHEMA_COOPERATIVA,
                'dimensioni_disponibili'  => [Tenant::DIM_COOPERATIVA, Tenant::DIM_ABBREVIATO, Tenant::DIM_MICRO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_ESENTE],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'numero_iscrizione_albo_coop'],
                'gruppo'                  => 'Cooperative',
                'descrizione_breve'       => 'Cooperativa di comunità — servizi territoriali locali',
            ],

            // ── Società di capitali ───────────────────────────────────────

            Tenant::FG_SRL => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_CENTRI_COSTO,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ORDINARIO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ORDINARIO,
                'dimensioni_disponibili'  => [Tenant::DIM_MICRO, Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_MARGINE, Tenant::IVA_EDITORIA],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di capitali',
                'descrizione_breve'       => 'S.r.l. — bilancio CEE (micro/abbreviato/ordinario)',
            ],

            Tenant::FG_SRLS => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_CENTRI_COSTO,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
                'schema_bilancio_default' => self::SCHEMA_CEE_MICRO,
                'dimensioni_disponibili'  => [Tenant::DIM_MICRO, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di capitali',
                'descrizione_breve'       => 'S.r.l. semplificata — tipicamente micro o abbreviata',
            ],

            Tenant::FG_SPA => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_CENTRI_COSTO,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ORDINARIO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ORDINARIO,
                'dimensioni_disponibili'  => [Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di capitali',
                'descrizione_breve'       => 'Società per Azioni — bilancio CEE ordinario',
            ],

            Tenant::FG_SAPA => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_CENTRI_COSTO,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ORDINARIO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ORDINARIO,
                'dimensioni_disponibili'  => [Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di capitali',
                'descrizione_breve'       => 'Società in accomandita per azioni',
            ],

            // ── Società di persone ────────────────────────────────────────

            Tenant::FG_SAS => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_BILANCIO_CEE,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ABBREVIATO,
                'dimensioni_disponibili'  => [Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di persone',
                'descrizione_breve'       => 'S.a.s. — contabilità ordinaria o semplificata',
            ],

            Tenant::FG_SNC => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_BILANCIO_CEE,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ABBREVIATO,
                'dimensioni_disponibili'  => [Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'rea_numero', 'rea_citta'],
                'gruppo'                  => 'Società di persone',
                'descrizione_breve'       => 'S.n.c. — contabilità ordinaria o semplificata',
            ],

            Tenant::FG_SS => [
                'moduli' => [
                    self::MOD_PRIMA_NOTA,
                    self::MOD_INCASSI,
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_SCADENZE,
                    self::MOD_CESPITI,
                ],
                'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
                'schema_bilancio_default' => self::SCHEMA_NESSUNO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE],
                'regimi_contabili'        => [Tenant::RC_NON_APPLICABILE],
                'regimi_iva'              => [Tenant::IVA_NON_APPLICABILE],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'Società di persone',
                'descrizione_breve'       => 'Società semplice — no IVA, no bilancio CEE',
            ],

            // ── Autonomi / Professionisti ─────────────────────────────────

            Tenant::FG_DITTA_IND => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_MAGAZZINO,
                ]),
                'piano_conti_template'    => self::PDC_PROFESSIONISTA,
                'schema_bilancio_default' => self::SCHEMA_RENDICONTO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO, Tenant::RC_FORFETTARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_FORFETTARIO, Tenant::IVA_AGRICOLO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
                'gruppo'                  => 'Autonomi e professionisti',
                'descrizione_breve'       => 'Ditta individuale — ordinario, semplificato o forfettario',
            ],

            Tenant::FG_LIBERO_PROF => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_COMPENSI_TERZI,
                ]),
                'piano_conti_template'    => self::PDC_PROFESSIONISTA,
                'schema_bilancio_default' => self::SCHEMA_RENDICONTO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_FORFETTARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO, Tenant::IVA_FORFETTARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
                'gruppo'                  => 'Autonomi e professionisti',
                'descrizione_breve'       => 'Libero professionista — cassa professionale, parcelle',
            ],

            Tenant::FG_STUDIO_PROF => [
                'moduli' => array_merge($moduliBaseContabilita, $moduliBilancioCee, [
                    self::MOD_CONTO_ECONOMICO,
                    self::MOD_COMPENSI_TERZI,
                    self::MOD_CENTRI_COSTO,
                ]),
                'piano_conti_template'    => self::PDC_PROFESSIONISTA,
                'schema_bilancio_default' => self::SCHEMA_RENDICONTO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE, Tenant::DIM_ABBREVIATO],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO],
                'regimi_iva'              => [Tenant::IVA_ORDINARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
                'gruppo'                  => 'Autonomi e professionisti',
                'descrizione_breve'       => 'Studio professionale (commercialisti, avvocati, ingegneri)',
            ],

            // ── Forfettario ───────────────────────────────────────────────

            Tenant::FG_FORFETTARIO => [
                'moduli' => [
                    self::MOD_PRIMA_NOTA,
                    self::MOD_FATTURE_ATTIVE,
                    self::MOD_FATTURE_PASSIVE,
                    self::MOD_INCASSI,
                    self::MOD_CESPITI,
                    self::MOD_SCADENZE,
                    self::MOD_F24,
                ],
                'piano_conti_template'    => self::PDC_FORFETTARIO,
                'schema_bilancio_default' => self::SCHEMA_NESSUNO,
                'dimensioni_disponibili'  => [Tenant::DIM_NON_APPLICABILE],
                'regimi_contabili'        => [Tenant::RC_FORFETTARIO],
                'regimi_iva'              => [Tenant::IVA_FORFETTARIO],
                'campi_obbligatori'       => ['codice_fiscale', 'partita_iva', 'attivita_ateco'],
                'gruppo'                  => 'Autonomi e professionisti',
                'descrizione_breve'       => 'Regime forfettario — no IVA, no bilancio, sostitutiva 15%/5%',
            ],

            // ── Altro ─────────────────────────────────────────────────────

            Tenant::FG_ALTRO => [
                'moduli' => array_merge($moduliBaseContabilita, [
                    self::MOD_CONTO_ECONOMICO,
                ]),
                'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
                'schema_bilancio_default' => self::SCHEMA_CEE_ABBREVIATO,
                'dimensioni_disponibili'  => [
                    Tenant::DIM_MICRO, Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE,
                    Tenant::DIM_NON_APPLICABILE,
                ],
                'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO, Tenant::RC_FORFETTARIO],
                'regimi_iva'              => [
                    Tenant::IVA_ORDINARIO, Tenant::IVA_FORFETTARIO, Tenant::IVA_ESENTE,
                    Tenant::IVA_NON_APPLICABILE,
                ],
                'campi_obbligatori'       => ['codice_fiscale'],
                'gruppo'                  => 'Altro',
                'descrizione_breve'       => 'Forma giuridica non elencata — configurazione manuale',
            ],
        ];
    }

    /** Profilo di fallback per forme giuridiche non definite. */
    private static function profiloDefault(): array
    {
        return [
            'moduli' => [
                self::MOD_PRIMA_NOTA,
                self::MOD_FATTURE_ATTIVE,
                self::MOD_FATTURE_PASSIVE,
                self::MOD_INCASSI,
                self::MOD_IVA,
                self::MOD_SCADENZE,
                self::MOD_F24,
            ],
            'piano_conti_template'    => self::PDC_CEE_ABBREVIATO,
            'schema_bilancio_default' => self::SCHEMA_CEE_ABBREVIATO,
            'dimensioni_disponibili'  => [Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE],
            'regimi_contabili'        => [Tenant::RC_ORDINARIO, Tenant::RC_SEMPLIFICATO],
            'regimi_iva'              => [Tenant::IVA_ORDINARIO],
            'campi_obbligatori'       => ['codice_fiscale', 'partita_iva'],
            'gruppo'                  => 'Altro',
            'descrizione_breve'       => '',
        ];
    }
}
