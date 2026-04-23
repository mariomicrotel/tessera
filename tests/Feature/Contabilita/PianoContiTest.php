<?php

use App\Models\ContoContabile;
use App\Models\Tenant;
use Database\Seeders\PianoContiCooperativaSeeder;

// ─────────────────────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->tenant = Tenant::create([
        'name'              => 'Coop PdC Test',
        'slug'              => 'coop-pdc-test',
        'organization_type' => 'cooperative',
        'plan'              => 'free',
        'is_active'         => true,
    ]);

    app()->instance('current_tenant', $this->tenant);
});

afterEach(function () {
    app()->forgetInstance('current_tenant');
});

// ─────────────────────────────────────────────────────────────────────────────
// ContoContabile — helper statici
// ─────────────────────────────────────────────────────────────────────────────

describe('ContoContabile helpers', function () {

    it('calcola il livello dal codice decimale', function () {
        expect(ContoContabile::calcolaLivelloDaCodice('1'))->toBe(1);
        expect(ContoContabile::calcolaLivelloDaCodice('1.10'))->toBe(2);
        expect(ContoContabile::calcolaLivelloDaCodice('1.10.05'))->toBe(3);
        expect(ContoContabile::calcolaLivelloDaCodice('1.10.05.001'))->toBe(4);
    });

    it('calcola il codice padre da un codice gerarchico', function () {
        expect(ContoContabile::calcolaCodicePadre('1.10.05.001'))->toBe('1.10.05');
        expect(ContoContabile::calcolaCodicePadre('1.10.05'))->toBe('1.10');
        expect(ContoContabile::calcolaCodicePadre('1.10'))->toBe('1');
        expect(ContoContabile::calcolaCodicePadre('1'))->toBeNull();
    });

    it('isDare e isAvere riflettono il segno naturale', function () {
        $conto = new ContoContabile(['segno_naturale' => 'dare']);
        expect($conto->isDare())->toBeTrue();
        expect($conto->isAvere())->toBeFalse();

        $conto = new ContoContabile(['segno_naturale' => 'avere']);
        expect($conto->isDare())->toBeFalse();
        expect($conto->isAvere())->toBeTrue();
    });

    it('isStatoPatrimoniale true solo per attivo/passivo/PN', function () {
        expect((new ContoContabile(['natura' => 'attivo']))->isStatoPatrimoniale())->toBeTrue();
        expect((new ContoContabile(['natura' => 'passivo']))->isStatoPatrimoniale())->toBeTrue();
        expect((new ContoContabile(['natura' => 'patrimonio_netto']))->isStatoPatrimoniale())->toBeTrue();
        expect((new ContoContabile(['natura' => 'costo']))->isStatoPatrimoniale())->toBeFalse();
        expect((new ContoContabile(['natura' => 'ricavo']))->isStatoPatrimoniale())->toBeFalse();
    });

    it('isContoEconomico true solo per costo/ricavo', function () {
        expect((new ContoContabile(['natura' => 'costo']))->isContoEconomico())->toBeTrue();
        expect((new ContoContabile(['natura' => 'ricavo']))->isContoEconomico())->toBeTrue();
        expect((new ContoContabile(['natura' => 'attivo']))->isContoEconomico())->toBeFalse();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Seeder PianoContiCooperativa
// ─────────────────────────────────────────────────────────────────────────────

describe('PianoContiCooperativaSeeder', function () {

    it('precarica un numero consistente di conti per il tenant', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $count = ContoContabile::count();
        expect($count)->toBeGreaterThan(130);
        expect($count)->toBe(count(PianoContiCooperativaSeeder::PIANO_CONTI));
    });

    it('è idempotente: esecuzioni multiple non duplicano', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
        $countDopoPrimo = ContoContabile::count();

        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
        $countDopoSecondo = ContoContabile::count();

        expect($countDopoPrimo)->toBe($countDopoSecondo);
    });

    it('tutti i conti creati sono di_sistema e attivi', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $nonDiSistema = ContoContabile::where('di_sistema', false)->count();
        $nonAttivi    = ContoContabile::where('attivo', false)->count();

        expect($nonDiSistema)->toBe(0);
        expect($nonAttivi)->toBe(0);
    });

    it('gerarchia: ogni conto non-classe ha un parent corretto', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $figliSenzaPadre = ContoContabile::where('livello', '>', 1)
            ->whereNull('parent_id')
            ->count();

        expect($figliSenzaPadre)->toBe(0);
    });

    it('solo i sottoconti livello 4 sono movimentabili', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $movimentabiliNonSottoconti = ContoContabile::movimentabili()
            ->where('livello', '!=', 4)
            ->count();
        $sottocontiNonMovimentabili = ContoContabile::where('livello', 4)
            ->where('movimentabile', false)
            ->count();

        expect($movimentabiliNonSottoconti)->toBe(0);
        expect($sottocontiNonMovimentabili)->toBe(0);
    });

    it('segno naturale corretto: attivo/costo = dare, passivo/PN/ricavo = avere', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        // Escludo i fondi ammortamento (sono attivi ma segno avere — poste rettificative)
        $attiviNonDare = ContoContabile::where('natura', 'attivo')
            ->where('segno_naturale', '!=', 'dare')
            ->where('codice', 'not like', '1.25%')
            ->count();
        expect($attiviNonDare)->toBe(0);

        $costiNonDare = ContoContabile::where('natura', 'costo')
            ->where('segno_naturale', '!=', 'dare')->count();
        expect($costiNonDare)->toBe(0);

        $passiviNonAvere = ContoContabile::where('natura', 'passivo')
            ->where('segno_naturale', '!=', 'avere')->count();
        expect($passiviNonAvere)->toBe(0);

        $pnNonAvere = ContoContabile::where('natura', 'patrimonio_netto')
            ->where('segno_naturale', '!=', 'avere')->count();
        expect($pnNonAvere)->toBe(0);

        $ricaviNonAvere = ContoContabile::where('natura', 'ricavo')
            ->where('segno_naturale', '!=', 'avere')->count();
        expect($ricaviNonAvere)->toBe(0);
    });

    it('fondi ammortamento sono attivi ma segno avere (poste rettificative)', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $fondi = ContoContabile::where('codice', 'like', '1.25%')->get();
        expect($fondi)->not->toBeEmpty();

        foreach ($fondi as $conto) {
            expect($conto->natura)->toBe('attivo');
            expect($conto->segno_naturale)->toBe('avere');
        }
    });

    it('contiene i conti fondamentali del ciclo attivo/passivo', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $codiciFondamentali = [
            '1.50.05.001',  // Clienti Italia
            '1.60.05.001',  // Banca c/c
            '1.60.15.001',  // Cassa contanti
            '2.40.05.001',  // Fornitori Italia
            '2.45.05.001',  // Erario c/IVA
            '3.10.05.001',  // Capitale sociale soci cooperatori
            '4.10.05.001',  // Ricavi vendite merci
            '5.10.05.001',  // Acquisti merci
            '2.60.05.001',  // Prestito sociale (specifico coop)
            '2.60.10.001',  // Ristorni (specifico coop)
        ];

        foreach ($codiciFondamentali as $codice) {
            $conto = ContoContabile::where('codice', $codice)->first();
            expect($conto)->not->toBeNull("Conto $codice mancante");
            expect($conto->movimentabile)->toBeTrue("Conto $codice dovrebbe essere movimentabile");
        }
    });

    it('la classe padre aggrega correttamente via parent_id', function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);

        $classeAttivo = ContoContabile::where('codice', '1')->first();
        expect($classeAttivo)->not->toBeNull();
        expect($classeAttivo->parent_id)->toBeNull();
        expect($classeAttivo->livello)->toBe(1);
        expect($classeAttivo->hasChildren())->toBeTrue();

        // I figli diretti della classe 1 sono i mastri 1.10, 1.20, 1.25, ecc.
        $figliDiretti = $classeAttivo->children()->pluck('codice')->toArray();
        expect($figliDiretti)->toContain('1.10');
        expect($figliDiretti)->toContain('1.20');
        expect($figliDiretti)->toContain('1.60');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Multi-tenancy
// ─────────────────────────────────────────────────────────────────────────────

describe('isolamento multi-tenant', function () {

    it('il piano conti di un tenant non è visibile ad altri tenant', function () {
        $tenantA = $this->tenant; // già seedato dall'observer in beforeEach
        $countA = ContoContabile::count();
        expect($countA)->toBeGreaterThan(0);

        $tenantB = Tenant::create([
            'name'              => 'Altra Coop',
            'slug'              => 'altra-coop',
            'organization_type' => 'cooperative',
            'plan'              => 'free',
            'is_active'         => true,
        ]);

        // Passa lo scope a tenantB: l'observer ha già seedato anche tenantB
        app()->instance('current_tenant', $tenantB);
        $countB = ContoContabile::count();
        expect($countB)->toBe($countA);

        // Nessun record di A visibile nello scope di B
        $tenantIdsInScope = ContoContabile::pluck('tenant_id')->unique()->values()->all();
        expect($tenantIdsInScope)->toBe([$tenantB->id]);

        // Totale senza scope: A + B separati
        $totaleTutti = ContoContabile::withoutGlobalScope('tenant')->count();
        expect($totaleTutti)->toBe($countA * 2);

        // Ripristina tenant A: conti invariati
        app()->instance('current_tenant', $tenantA);
        expect(ContoContabile::count())->toBe($countA);
    });

    it('TenantObserver precarica automaticamente il piano conti per nuove cooperative', function () {
        app()->forgetInstance('current_tenant');

        $nuovaCoop = Tenant::create([
            'name'              => 'Nuova Coop Auto',
            'slug'              => 'nuova-coop-auto',
            'organization_type' => 'cooperative',
            'plan'              => 'free',
            'is_active'         => true,
        ]);

        $countConti = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $nuovaCoop->id)
            ->count();

        expect($countConti)->toBeGreaterThan(130);
    });

    it('TenantObserver NON precarica il piano conti per tenant non-cooperativi (ETS)', function () {
        app()->forgetInstance('current_tenant');

        $ets = Tenant::create([
            'name'              => 'ETS Test',
            'slug'              => 'ets-test',
            'organization_type' => 'ets',   // non cooperative
            'plan'              => 'free',
            'is_active'         => true,
        ]);

        $countConti = ContoContabile::withoutGlobalScope('tenant')
            ->where('tenant_id', $ets->id)
            ->count();

        expect($countConti)->toBe(0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// Scopes Eloquent
// ─────────────────────────────────────────────────────────────────────────────

describe('scopes ContoContabile', function () {

    beforeEach(function () {
        (new PianoContiCooperativaSeeder)->perTenant($this->tenant);
    });

    it('scope movimentabili ritorna solo sottoconti', function () {
        $mov = ContoContabile::movimentabili()->get();
        expect($mov)->not->toBeEmpty();
        foreach ($mov as $c) {
            expect($c->livello)->toBe(4);
            expect($c->movimentabile)->toBeTrue();
        }
    });

    it('scope diStatoPatrimoniale esclude costi e ricavi', function () {
        $sp = ContoContabile::diStatoPatrimoniale()->get();
        foreach ($sp as $c) {
            expect($c->natura)->toBeIn(['attivo', 'passivo', 'patrimonio_netto']);
        }
    });

    it('scope diContoEconomico esclude stato patrimoniale', function () {
        $ce = ContoContabile::diContoEconomico()->get();
        foreach ($ce as $c) {
            expect($c->natura)->toBeIn(['costo', 'ricavo']);
        }
    });

    it('scope discendenti filtra per prefisso codice', function () {
        // Tutti i conti sotto "1.60 Disponibilità liquide"
        $liquidi = ContoContabile::discendenti('1.60')->get();
        expect($liquidi)->not->toBeEmpty();
        foreach ($liquidi as $c) {
            expect($c->codice)->toStartWith('1.60.');
        }
    });

    it('scope byLivello filtra per livello gerarchico', function () {
        expect(ContoContabile::byLivello(1)->count())->toBeGreaterThan(0);
        expect(ContoContabile::byLivello(4)->count())->toBeGreaterThan(50);
    });

    it('scope search trova per codice o descrizione', function () {
        $perCodice = ContoContabile::search('1.60.05')->get();
        expect($perCodice)->not->toBeEmpty();

        $perDesc = ContoContabile::search('Cassa')->get();
        expect($perDesc)->not->toBeEmpty();
    });

    it('scope diSistema ritorna solo conti del template', function () {
        $sistema = ContoContabile::diSistema()->get();
        foreach ($sistema as $c) {
            expect($c->di_sistema)->toBeTrue();
        }
        expect($sistema->count())->toBe(ContoContabile::count());
    });
});
