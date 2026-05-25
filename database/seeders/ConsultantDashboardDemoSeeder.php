<?php

namespace Database\Seeders;

use App\Models\EstrattoContoModel;
use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\Incasso;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\MovimentoBancario;
use App\Models\Spesa;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder demo per il cruscotto consulente (Fase 2).
 *
 * Popola il tenant "default" (o il primo tenant attivo) con:
 *  - 12 mesi di fatture attive (trend crescente)
 *  - 12 mesi di fatture passive (fornitori)
 *  - Incassi (quote + donazioni) distribuiti nel corso dell'anno
 *  - Spese varie mensili
 *  - Movimenti bancari (80% riconciliati)
 *  - Soci attivi + alcuni nuovi iscritti
 *
 * Idempotente: controlla se i dati demo esistono già.
 * Esegui: php artisan db:seed --class=ConsultantDashboardDemoSeeder
 */
class ConsultantDashboardDemoSeeder extends Seeder
{
    private Tenant $tenant;
    private int $contoId;          // conto cassa/banca per incassi e spese
    private string $estrattoId;    // estratto conto demo per movimenti bancari

    public function run(): void
    {
        $this->tenant = Tenant::where('slug', 'default')->first()
            ?? Tenant::where('is_active', true)->first();

        if (! $this->tenant) {
            $this->command->error('Nessun tenant trovato. Esegui prima l\'installer.');
            return;
        }

        app()->instance('current_tenant', $this->tenant);

        $this->command->info("🌱 Seeding dati demo cruscotto per: {$this->tenant->name}");

        // Recupera un conto disponibile (obbligatorio per incassi)
        $conto = \App\Models\Conto::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('attivo', true)
            ->first();

        if (! $conto) {
            $this->command->error('Nessun conto attivo trovato. Esegui prima il seeder di installazione.');
            return;
        }
        $this->contoId = $conto->id;

        // Recupera o crea estratto conto demo per movimenti bancari
        $year = now()->year;
        $estratto = EstrattoContoModel::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('banca', '[DEMO] Banca Demo SPA')
            ->first();
        if (! $estratto) {
            $estratto = EstrattoContoModel::create([
                'tenant_id'     => $this->tenant->id,
                'nome_file'     => 'estratto-demo-' . $year . '.csv',
                'banca'         => '[DEMO] Banca Demo SPA',
                'iban'          => 'IT60X0542811101000000123456',
                'periodo_dal'   => Carbon::create($year, 1, 1)->toDateString(),
                'periodo_al'    => Carbon::create($year, 12, 31)->toDateString(),
                'saldo_iniziale' => 12500.00,
                'saldo_finale'  => 0.00, // aggiornato dopo
                'formato'       => 'csv',
            ]);
        }
        $this->estrattoId = $estratto->id;

        // Controlla i dati demo già presenti per sezione
        $hasFattureAttive  = FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->where('note', 'LIKE', '%[DEMO]%')->exists();
        $hasIncassi        = Incasso::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->where('description', 'LIKE', '%[DEMO]%')->exists();
        $hasMovBancari     = MovimentoBancario::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->where('descrizione', 'LIKE', '%[DEMO]%')->exists();

        if ($hasFattureAttive && $hasIncassi && $hasMovBancari) {
            $this->command->warn('  Dati demo gia\' completi. Per rigenerare cancella le righe con nota [DEMO].');
            return;
        }

        if (! $hasFattureAttive) {
            $this->seedFattureAttive();
            $this->seedFatturePassive();
        } else {
            $this->command->warn('  Fatture gia\' presenti, skippo.');
        }

        if (! $hasIncassi) {
            $this->seedIncassi();
            $this->seedSpese();
        } else {
            $this->command->warn('  Incassi/spese gia\' presenti, skippo.');
        }

        if (! $hasMovBancari) {
            $this->seedMovimentiBancari();
        } else {
            $this->command->warn('  Movimenti bancari gia\' presenti, skippo.');
        }

        $this->seedSoci();

        $this->command->info("  Done! Apri il cruscotto consulente per {$this->tenant->name}");
    }

    /* ── Fatture attive ──────────────────────────────────────────────────── */

    private function seedFattureAttive(): void
    {
        $this->command->info('  → Fatture attive...');

        // Base mensile crescente (trend realistico ETS)
        $mensili = [
            1 => 3200, 2 => 3500, 3 => 4100, 4 => 4800, 5 => 5200, 6 => 6100,
            7 => 4900, 8 => 3800, 9 => 5500, 10 => 6200, 11 => 7100, 12 => 7800,
        ];

        $progressivo = 1;
        $year = now()->year;

        foreach ($mensili as $mese => $baseImporto) {
            // 2-4 fatture per mese
            $count = rand(2, 4);
            for ($i = 1; $i <= $count; $i++) {
                $importo = round($baseImporto / $count * (0.8 + rand(0, 40) / 100), 2);
                $iva     = round($importo * 0.22, 2);
                $totale  = $importo + $iva;
                $data    = Carbon::create($year, $mese, rand(1, 25));

                // Ultime 2 fatture dell'anno ancora da incassare
                $isUltimo2Mesi = $mese >= (now()->month - 1);
                $stato_pag = $isUltimo2Mesi
                    ? (rand(0, 1) ? 'da_incassare' : 'parzialmente_incassata')
                    : (rand(0, 10) > 1 ? 'incassata' : 'da_incassare');

                FatturaAttiva::create([
                    'tenant_id'          => $this->tenant->id,
                    'numero_fattura'      => sprintf('%d/%04d', $progressivo, $year),
                    'sezionale'           => 'VEN',
                    'anno'                => $year,
                    'progressivo'         => $progressivo,
                    'data_fattura'        => $data->toDateString(),
                    'data_scadenza'       => $data->addDays(30)->toDateString(),
                    'imponibile_totale'   => $importo,
                    'iva_totale'          => $iva,
                    'totale_documento'    => $totale,
                    'stato'               => rand(0, 10) > 1 ? 'accettata' : 'emessa',
                    'stato_pagamento'     => $stato_pag,
                    'tipo_documento'      => 'TD01',
                    'esigibilita'         => 'immediata',
                    'note'                => '[DEMO] Servizi istituzionali — cliente demo ' . $i,
                ]);
                $progressivo++;
            }
        }

        // 3 fatture in scadenza nei prossimi 30gg (per testare il badge avvisi)
        for ($i = 1; $i <= 2; $i++) {
            $importo = round(rand(800, 2500), 2);
            $iva     = round($importo * 0.22, 2);
            FatturaAttiva::create([
                'tenant_id'        => $this->tenant->id,
                'numero_fattura'   => sprintf('%d/%04d', $progressivo, $year),
                'sezionale'        => 'VEN',
                'anno'             => $year,
                'progressivo'      => $progressivo,
                'data_fattura'     => now()->subDays(rand(5, 15))->toDateString(),
                'data_scadenza'    => now()->addDays(rand(5, 25))->toDateString(),
                'imponibile_totale' => $importo,
                'iva_totale'       => $iva,
                'totale_documento' => $importo + $iva,
                'stato'            => 'accettata',
                'stato_pagamento'  => 'da_incassare',
                'tipo_documento'   => 'TD01',
                'esigibilita'      => 'immediata',
                'note'             => '[DEMO] Fattura scadenza imminente',
            ]);
            $progressivo++;
        }

        $count = FatturaAttiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} fatture attive create");
    }

    /* ── Fatture passive ─────────────────────────────────────────────────── */

    private function seedFatturePassive(): void
    {
        $this->command->info('  → Fatture passive...');

        $fornitori = [
            'Utenze Elettriche SpA', 'Affitto Locali Srl', 'Software House Srl',
            'Consulente Fiscale', 'Forniture Ufficio', 'Assicurazione AIG',
        ];

        $year = now()->year;
        $progressivo = 1;

        // Una fattura per fornitore ogni 2 mesi
        for ($mese = 1; $mese <= 12; $mese++) {
            $numFornitori = rand(2, 4);
            $selezionati = array_rand($fornitori, $numFornitori);
            if (! is_array($selezionati)) {
                $selezionati = [$selezionati];
            }

            foreach ($selezionati as $fi) {
                $importo = round(rand(200, 1800), 2);
                $iva     = round($importo * 0.22, 2);
                $data    = Carbon::create($year, $mese, rand(1, 20));

                // Alcune in scadenza nei prossimi 30gg
                $scadenza = ($mese >= now()->month - 1 && rand(0, 1))
                    ? now()->addDays(rand(5, 28))->toDateString()
                    : $data->addDays(30)->toDateString();

                $isPassata = Carbon::parse($scadenza)->isPast();
                $stato_pag = $isPassata
                    ? (rand(0, 10) > 2 ? 'pagata' : 'da_pagare')
                    : 'da_pagare';

                FatturaPassiva::create([
                    'tenant_id'          => $this->tenant->id,
                    'numero_fattura'      => "FP-{$year}-" . str_pad($progressivo, 4, '0', STR_PAD_LEFT),
                    'data_fattura'        => $data->toDateString(),
                    'data_ricezione'      => $data->addDays(2)->toDateString(),
                    'data_registrazione'  => $data->addDays(3)->toDateString(),
                    'data_scadenza'       => $scadenza,
                    'imponibile_totale'   => $importo,
                    'iva_totale'          => $iva,
                    'totale_documento'    => $importo + $iva,
                    'tipo_documento'      => 'TD01',
                    'esigibilita'         => 'immediata',
                    'stato_pagamento'     => $stato_pag,
                    'note'                => '[DEMO] ' . $fornitori[$fi],
                ]);
                $progressivo++;
            }
        }

        $count = FatturaPassiva::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} fatture passive create");
    }

    /* ── Incassi ──────────────────────────────────────────────────────────── */

    private function seedIncassi(): void
    {
        $this->command->info('  → Incassi...');

        $year = now()->year;
        $tipi = [
            ['type' => 'quota',     'base' => 50,   'weight' => 60],
            ['type' => 'donazione', 'base' => 200,  'weight' => 25],
            ['type' => 'altro',     'base' => 350,  'weight' => 15],
        ];

        for ($mese = 1; $mese <= 12; $mese++) {
            // 5-15 incassi per mese
            $numIncassi = rand(5, 15);
            for ($i = 0; $i < $numIncassi; $i++) {
                // Scegli tipo in base al peso
                $roll = rand(1, 100);
                $cumulativo = 0;
                $tipo = $tipi[0];
                foreach ($tipi as $t) {
                    $cumulativo += $t['weight'];
                    if ($roll <= $cumulativo) {
                        $tipo = $t;
                        break;
                    }
                }

                $importo = round($tipo['base'] * (0.7 + rand(0, 60) / 100), 2);

                Incasso::create([
                    'tenant_id'   => $this->tenant->id,
                    'amount'      => $importo,
                    'paid_at'     => Carbon::create($year, $mese, rand(1, 27)),
                    'type'        => $tipo['type'],
                    'conto_id'    => $this->contoId,
                    'description' => '[DEMO] ' . ucfirst($tipo['type']) . ' mese ' . $mese,
                ]);
            }
        }

        $count = Incasso::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} incassi creati");
    }

    /* ── Spese ──────────────────────────────────────────────────────────────  */

    private function seedSpese(): void
    {
        $this->command->info('  → Spese...');

        $year = now()->year;
        $voci = [
            ['desc' => 'Materiali di consumo',    'base' => 120],
            ['desc' => 'Spese postali e bancarie', 'base' => 30],
            ['desc' => 'Rimborsi spese volontari', 'base' => 180],
            ['desc' => 'Manutenzione sede',        'base' => 250],
            ['desc' => 'Formazione personale',     'base' => 400],
            ['desc' => 'Attrezzatura informatica', 'base' => 600],
        ];

        for ($mese = 1; $mese <= 12; $mese++) {
            // 3-5 spese per mese
            $numSpese = rand(3, 5);
            $selezionate = array_rand($voci, min($numSpese, count($voci)));
            if (! is_array($selezionate)) {
                $selezionate = [$selezionate];
            }

            foreach ($selezionate as $vi) {
                $voce = $voci[$vi];
                $importo = round($voce['base'] * (0.8 + rand(0, 40) / 100), 2);

                Spesa::create([
                    'tenant_id'   => $this->tenant->id,
                    'date'        => Carbon::create($year, $mese, rand(1, 27)),
                    'amount'      => $importo,
                    'description' => '[DEMO] ' . $voce['desc'],
                    'conto_id'    => $this->contoId,
                ]);
            }
        }

        $count = Spesa::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} spese create");
    }

    /* ── Movimenti bancari ──────────────────────────────────────────────── */

    private function seedMovimentiBancari(): void
    {
        $this->command->info('  → Movimenti bancari...');

        $year = now()->year;

        for ($mese = 1; $mese <= 12; $mese++) {
            // 8-20 movimenti per mese
            $numMov = rand(8, 20);
            for ($i = 0; $i < $numMov; $i++) {
                $isEntrata = rand(0, 10) > 4;
                $importo   = round(rand(50, 3000) + rand(0, 99) / 100, 2);
                $data      = Carbon::create($year, $mese, rand(1, 27));

                // 80% riconciliati, 20% no
                $riconciliato = rand(0, 10) > 1;

                MovimentoBancario::create([
                    'tenant_id'        => $this->tenant->id,
                    'estratto_conto_id' => $this->estrattoId,
                    'data_valuta'      => $data->toDateString(),
                    'data_contabile'   => $data->addDays(rand(0, 2))->toDateString(),
                    'descrizione'      => '[DEMO] ' . ($isEntrata ? 'Accredito' : 'Addebito') . ' ' . $data->format('Y-m-d'),
                    'importo'          => $importo,
                    'tipo'             => $isEntrata ? 'avere' : 'dare',
                    'riconciliato'     => $riconciliato,
                    'riferimento'      => Str::upper(Str::random(10)),
                ]);
            }
        }

        $count = MovimentoBancario::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} movimenti bancari creati");
    }

    /* ── Soci ──────────────────────────────────────────────────────────── */

    private function seedSoci(): void
    {
        $this->command->info('  → Soci...');

        $existing = Member::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();

        if ($existing >= 30) {
            $this->command->info("     Soci già presenti ({$existing}), skippo.");
            return;
        }

        // Tipo socio
        $tipoSocio = MemberType::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'name' => 'ordinario'],
            ['display_name' => 'Socio Ordinario'],
        );

        $nomi = [
            'Maria Rossi', 'Giuseppe Bianchi', 'Anna Ferrari', 'Marco Esposito',
            'Lucia Romano', 'Davide Ricci', 'Sofia Colombo', 'Luca Marino',
            'Elena Greco', 'Stefano Bruno', 'Giulia Gallo', 'Andrea Conti',
            'Federica Mancini', 'Roberto Costa', 'Valentina Fontana',
            'Paolo Barbieri', 'Chiara Rizzo', 'Matteo Serra', 'Laura Gentile',
            'Simone Lombardi', 'Francesca Moretti', 'Nicola De Luca',
            'Alessia Marini', 'Emanuele Pellegrini', 'Sara Caruso',
            'Fabio Villa', 'Monica Leone', 'Daniele Ferretti',
            'Elisa Montanari', 'Cristian Vitale',
        ];

        $year = now()->year;
        $month = now()->month;

        foreach ($nomi as $idx => $nome) {
            $parti = explode(' ', $nome);
            $isNuovo = $idx >= (count($nomi) - 5); // ultimi 5 = nuovi iscritti quest'anno

            // BelongsToTenant aggiunge automaticamente tenant_id (sia al where che alla create)
            Member::firstOrCreate(
                ['email' => Str::lower(str_replace(' ', '.', $nome)) . '@demo.it'],
                [
                    'nome'            => $parti[0],
                    'cognome'         => $parti[1] ?? '',
                    'member_type_id'  => $tipoSocio->id,
                    'stato'           => 'attivo',
                    'data_iscrizione' => $isNuovo
                        ? Carbon::create($year, rand(1, $month), rand(1, 20))->toDateString()
                        : Carbon::create($year - rand(1, 5), rand(1, 12), rand(1, 20))->toDateString(),
                    'codice_fiscale'  => strtoupper(Str::random(16)),
                ],
            );
        }

        $count = Member::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)->count();
        $this->command->info("     {$count} soci totali (5 nuovi iscritti quest'anno)");
    }
}
