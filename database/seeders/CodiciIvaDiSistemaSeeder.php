<?php

namespace Database\Seeders;

use App\Models\CodiceIva;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Precarica i 9 codici IVA di sistema per un tenant.
 *
 * Idempotente: usa firstOrCreate su (tenant_id, codice).
 *
 * Uso tipico:
 *   (new CodiciIvaDiSistemaSeeder)->perTenant($tenant);
 *
 * Oppure, via TenantObserver::created() al momento della creazione
 * di un nuovo tenant di tipo cooperativa.
 */
class CodiciIvaDiSistemaSeeder extends Seeder
{
    /**
     * Codici IVA di sistema.
     *
     * Riferimenti Natura SDI (tabella FatturaPA 1.2):
     *   N1     = escluse ex art.15
     *   N2.1/2 = non soggette
     *   N3.x   = non imponibili
     *   N4     = esenti
     *   N6.x   = reverse charge
     *   N7     = vendite a distanza UE (non usato qui)
     */
    public const CODICI_SISTEMA = [
        ['codice' => '22',  'descrizione' => 'IVA 22% ordinaria',        'percentuale' => 22.00, 'tipo' => CodiceIva::TIPO_NORMALE,         'natura_sdi' => null],
        ['codice' => '10',  'descrizione' => 'IVA 10% ridotta',           'percentuale' => 10.00, 'tipo' => CodiceIva::TIPO_NORMALE,         'natura_sdi' => null],
        ['codice' => '4',   'descrizione' => 'IVA 4% super-ridotta',      'percentuale' =>  4.00, 'tipo' => CodiceIva::TIPO_NORMALE,         'natura_sdi' => null],
        ['codice' => '5',   'descrizione' => 'IVA 5% beni essenziali',    'percentuale' =>  5.00, 'tipo' => CodiceIva::TIPO_NORMALE,         'natura_sdi' => null],
        ['codice' => '0',   'descrizione' => 'Aliquota 0%',                'percentuale' =>  0.00, 'tipo' => CodiceIva::TIPO_NORMALE,         'natura_sdi' => null],
        ['codice' => 'ESE', 'descrizione' => 'Esente art.10 DPR 633/72',  'percentuale' =>  0.00, 'tipo' => CodiceIva::TIPO_ESENTE,          'natura_sdi' => 'N4'],
        ['codice' => 'FC',  'descrizione' => 'Fuori campo IVA',            'percentuale' =>  0.00, 'tipo' => CodiceIva::TIPO_FUORI_CAMPO,     'natura_sdi' => 'N2.2'],
        ['codice' => 'NI',  'descrizione' => 'Non imponibile art.8/8bis', 'percentuale' =>  0.00, 'tipo' => CodiceIva::TIPO_NON_IMPONIBILE,  'natura_sdi' => 'N3.1'],
        ['codice' => 'RC',  'descrizione' => 'Reverse charge interno',    'percentuale' => 22.00, 'tipo' => CodiceIva::TIPO_REVERSE_CHARGE,  'natura_sdi' => 'N6.1'],
        ['codice' => 'SP',  'descrizione' => 'Split payment PA',          'percentuale' => 22.00, 'tipo' => CodiceIva::TIPO_SPLIT_PAYMENT,   'natura_sdi' => null],
    ];

    /**
     * Esegui il seeder per un tenant specifico.
     */
    public function perTenant(Tenant $tenant): void
    {
        foreach (self::CODICI_SISTEMA as $codice) {
            CodiceIva::withoutGlobalScope('tenant')->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'codice'    => $codice['codice'],
                ],
                array_merge($codice, [
                    'tenant_id'                => $tenant->id,
                    'indetraibile_percentuale' => 0,
                    'attivo'                   => true,
                    'di_sistema'               => true,
                ]),
            );
        }
    }

    /**
     * Esegue il seeder per tutti i tenant di tipo cooperativa.
     * Invocabile via: php artisan db:seed --class=CodiciIvaDiSistemaSeeder
     */
    public function run(): void
    {
        Tenant::query()
            ->where('organization_type', 'cooperative')
            ->each(function (Tenant $tenant) {
                $this->perTenant($tenant);
            });
    }
}
