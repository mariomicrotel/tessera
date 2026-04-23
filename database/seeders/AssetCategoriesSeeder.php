<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\ContoContabile;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeder delle categorie fiscali di cespiti (coefficienti ministeriali DM 31/12/1988).
 *
 * Le categorie `di_sistema = true` sono condivise tra tutti i tenant (tenant_id = null).
 * I conti contabili di default vengono cercati per codice nel piano conti del primo tenant
 * cooperativa trovato (o del tenant specificato).
 *
 * Idempotente: usa firstOrCreate, sicuro eseguirlo più volte.
 *
 * Categorie incluse:
 *   ATTREZZ  — Attrezzature industriali                 15%
 *   MOBILI   — Mobili e arredi                          12%
 *   MACCHUFF — Macchine d'ufficio elettroniche          20%
 *   AUTO     — Autovetture (art. 164 TUIR — ded. 20%)  25%
 *   AUTOCARRO— Autocarri (ded. 100%)                   20%
 *   SW       — Software / Licenze informatiche          33%
 *   FABBR    — Fabbricati strumentali                    3%
 *   IMPIANTI — Impianti e macchinari generici          15%
 *   BENIIMM  — Beni immateriali (marchi, brevetti)     20%
 */
class AssetCategoriesSeeder extends Seeder
{
    /**
     * Struttura: [codice, descrizione, coefficiente%, deducibilità%, primo_anno_ridotto,
     *             codice_conto_bene, codice_conto_fondo, codice_conto_amm]
     */
    private const CATEGORIE = [
        // Beni materiali — strumentali
        [
            'codice'             => 'ATTREZZ',
            'descrizione'        => 'Attrezzature industriali e commerciali',
            'coefficiente'       => 15.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.15',   // Attrezzature
            'conto_fondo'        => '1.25.15',   // F.do amm.to attrezzature
            'conto_amm'          => '7.25.15',   // Amm.to attrezzature
        ],
        [
            'codice'             => 'MOBILI',
            'descrizione'        => 'Mobili e arredi',
            'coefficiente'       => 12.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.20',   // Mobili e arredi
            'conto_fondo'        => '1.25.20',   // F.do amm.to mobili
            'conto_amm'          => '7.25.20',   // Amm.to mobili
        ],
        [
            'codice'             => 'MACCHUFF',
            'descrizione'        => 'Macchine d\'ufficio elettroniche ed elettromeccaniche',
            'coefficiente'       => 20.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.25',   // Macchine ufficio
            'conto_fondo'        => '1.25.25',   // F.do amm.to macchine ufficio
            'conto_amm'          => '7.25.25',   // Amm.to macchine ufficio
        ],
        [
            'codice'             => 'AUTO',
            'descrizione'        => 'Autovetture (art. 164 co.1 lett.b TUIR — ded. 20%)',
            'coefficiente'       => 25.00,
            'deducibilita'       => 20.00,        // Limite fiscale autovetture
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.30',   // Automezzi
            'conto_fondo'        => '1.25.30',   // F.do amm.to automezzi
            'conto_amm'          => '7.25.30',   // Amm.to automezzi
        ],
        [
            'codice'             => 'AUTOCARRO',
            'descrizione'        => 'Autocarri e veicoli commerciali (ded. 100%)',
            'coefficiente'       => 20.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.30',   // Automezzi
            'conto_fondo'        => '1.25.30',   // F.do amm.to automezzi
            'conto_amm'          => '7.25.30',   // Amm.to automezzi
        ],
        [
            'codice'             => 'IMPIANTI',
            'descrizione'        => 'Impianti e macchinari',
            'coefficiente'       => 15.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.10',   // Impianti e macchinari
            'conto_fondo'        => '1.25.10',   // F.do amm.to impianti
            'conto_amm'          => '7.25.10',   // Amm.to impianti
        ],
        [
            'codice'             => 'FABBR',
            'descrizione'        => 'Fabbricati strumentali (solo quota edificio)',
            'coefficiente'       => 3.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => true,
            'conto_bene'         => '1.20.05',   // Fabbricati
            'conto_fondo'        => '1.25.05',   // F.do amm.to fabbricati
            'conto_amm'          => '7.25.05',   // Amm.to fabbricati
        ],

        // Beni immateriali
        [
            'codice'             => 'SW',
            'descrizione'        => 'Software e licenze informatiche',
            'coefficiente'       => 33.33,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => false,        // Per i beni immateriali il DM non prevede il 50%
            'conto_bene'         => null,         // Conto immateriali: da configurare per tenant
            'conto_fondo'        => null,
            'conto_amm'          => null,
        ],
        [
            'codice'             => 'BENIIMM',
            'descrizione'        => 'Beni immateriali (marchi, brevetti, concessioni)',
            'coefficiente'       => 20.00,
            'deducibilita'       => 100.00,
            'primo_anno_ridotto' => false,
            'conto_bene'         => null,
            'conto_fondo'        => null,
            'conto_amm'          => null,
        ],
    ];

    /**
     * Esegue il seeder.
     * Se viene passato un tenant, cerca i conti contabili nel suo piano dei conti.
     */
    public function run(?Tenant $tenant = null): void
    {
        // Usa il primo tenant cooperativa disponibile per risolvere i codici conto
        $tenant ??= Tenant::where('organization_type', 'cooperative')->first();

        foreach (self::CATEGORIE as $cat) {
            $contoBenoId        = $this->trovaContoId($cat['conto_bene'], $tenant);
            $contoFondoId       = $this->trovaContoId($cat['conto_fondo'], $tenant);
            $contoAmmId         = $this->trovaContoId($cat['conto_amm'], $tenant);

            AssetCategory::firstOrCreate(
                [
                    'tenant_id' => null,          // categorie di sistema: tenant_id null
                    'codice'    => $cat['codice'],
                ],
                [
                    'descrizione'                    => $cat['descrizione'],
                    'coefficiente_ministeriale'      => $cat['coefficiente'],
                    'percentuale_deducibilita_default' => $cat['deducibilita'],
                    'primo_anno_ridotto_default'     => $cat['primo_anno_ridotto'],
                    'conto_bene_default_id'          => $contoBenoId,
                    'conto_fondo_default_id'         => $contoFondoId,
                    'conto_ammortamento_default_id'  => $contoAmmId,
                    'di_sistema'                     => true,
                    'attivo'                         => true,
                ]
            );
        }

        $this->command?->info('✅ AssetCategories: ' . count(self::CATEGORIE) . ' categorie fiscali configurate.');
    }

    /**
     * Cerca un conto contabile per codice nel tenant specificato.
     * Restituisce null se il codice è null o il conto non viene trovato.
     */
    private function trovaContoId(?string $codice, ?Tenant $tenant): ?int
    {
        if ($codice === null || $tenant === null) {
            return null;
        }

        // Cerca sia codice esatto che codice con sottoconto
        $conto = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where(function ($q) use ($codice) {
                $q->where('codice', $codice)
                  ->orWhere('codice', 'like', $codice . '.%');
            })
            ->orderByRaw('LENGTH(codice) ASC')  // preferisce il codice più corto (il mastro)
            ->first();

        return $conto?->id;
    }
}
