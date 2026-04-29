<?php

/**
 * Test suite W1 — TipologiaAziendaCatalog
 *
 * Verifica che la matrice delle tipologie aziendali sia coerente:
 *  - ogni forma giuridica ha un profilo valido
 *  - i moduli inclusi/esclusi rispettano le regole di business
 *  - gli override (forfettario, micro, ecc.) funzionano correttamente
 *  - lo schema bilancio è corretto per ogni combinazione forma+dimensione
 *  - il Tenant model riconosce correttamente le famiglie
 */

use App\Models\Tenant;
use App\Services\Onboarding\TipologiaAziendaCatalog as Catalog;

// ─────────────────────────────────────────────────────────────────────────────
// Helper: crea tenant con forma giuridica impostata (senza DB)
// ─────────────────────────────────────────────────────────────────────────────

function tenantConForma(string $formaGiuridica, array $extra = []): Tenant
{
    $t = new Tenant();
    $t->forma_giuridica   = $formaGiuridica;
    $t->organization_type = str_starts_with($formaGiuridica, 'ets_')  ? 'ets'
        : (str_starts_with($formaGiuridica, 'coop_') ? 'cooperative' : 'commerciale');
    foreach ($extra as $k => $v) {
        $t->$k = $v;
    }
    return $t;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Profilo completo — struttura
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → struttura profilo', function () {

    it('ogni forma giuridica restituisce un profilo con tutte le chiavi richieste', function () {
        $formeCoperte = [
            Tenant::FG_ETS_ODV, Tenant::FG_ETS_APS, Tenant::FG_ETS_FONDAZIONE, Tenant::FG_ETS_GENERICO,
            Tenant::FG_COOP_LAVORO, Tenant::FG_COOP_SOCIALE_A, Tenant::FG_COOP_SOCIALE_B,
            Tenant::FG_COOP_AGRICOLA, Tenant::FG_COOP_CONSORTILE, Tenant::FG_COOP_CONSUMO,
            Tenant::FG_COOP_ABITAZIONE, Tenant::FG_COOP_COMUNITA,
            Tenant::FG_SRL, Tenant::FG_SRLS, Tenant::FG_SPA, Tenant::FG_SAPA,
            Tenant::FG_SAS, Tenant::FG_SNC, Tenant::FG_SS,
            Tenant::FG_DITTA_IND, Tenant::FG_LIBERO_PROF, Tenant::FG_STUDIO_PROF,
            Tenant::FG_ASS_NON_ETS, Tenant::FG_FOND_NON_ETS,
            Tenant::FG_FORFETTARIO, Tenant::FG_ALTRO,
        ];

        $chiavi = [
            'moduli', 'piano_conti_template', 'schema_bilancio_default',
            'dimensioni_disponibili', 'regimi_contabili', 'regimi_iva',
            'campi_obbligatori', 'gruppo', 'descrizione_breve',
        ];

        foreach ($formeCoperte as $fg) {
            $profilo = Catalog::profilo($fg);
            foreach ($chiavi as $chiave) {
                expect(array_key_exists($chiave, $profilo))
                    ->toBeTrue("Forma {$fg} manca chiave {$chiave}");
            }
            expect($profilo['moduli'])->not->toBeEmpty("Forma {$fg} non ha moduli");
        }
    });

    it('una forma giuridica sconosciuta restituisce il profilo di fallback', function () {
        $profilo = Catalog::profilo('forma_inesistente_xyz');
        expect($profilo)->toHaveKey('moduli');
        expect($profilo['moduli'])->not->toBeEmpty();
    });

    it('tutteRaggruppate restituisce gruppi non vuoti', function () {
        $gruppi = Catalog::tutteRaggruppate();
        expect($gruppi)->not->toBeEmpty();
        // Almeno ETS, Cooperative, Società di capitali, Autonomi
        expect(count($gruppi))->toBeGreaterThanOrEqual(4);
        foreach ($gruppi as $gruppoLabel => $voci) {
            expect($voci)->not->toBeEmpty("Gruppo '{$gruppoLabel}' è vuoto");
            foreach ($voci as $v) {
                expect($v)->toHaveKey('value');
                expect($v)->toHaveKey('label');
                expect($v)->toHaveKey('descrizione');
            }
        }
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Moduli — regole di business per tipologia
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → moduli per tipologia', function () {

    // ETS
    it('ETS OdV ha moduli ETS e soci, non ha moduli cooperative', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_ETS_ODV);
        expect($moduli)->toContain(Catalog::MOD_ETS);
        expect($moduli)->toContain(Catalog::MOD_SOCI);
        expect($moduli)->toContain(Catalog::MOD_RENDICONTO_CASSA);
        expect($moduli)->not->toContain(Catalog::MOD_COOPERATIVE);
        expect($moduli)->not->toContain(Catalog::MOD_BILANCIO_CEE);
    });

    // Cooperativa
    it('cooperative hanno moduli cooperative e soci, non hanno moduli ETS (eccetto sociale e comunità)', function () {
        $moduliLavoro = Catalog::moduliAbilitati(Tenant::FG_COOP_LAVORO);
        expect($moduliLavoro)->toContain(Catalog::MOD_COOPERATIVE);
        expect($moduliLavoro)->toContain(Catalog::MOD_SOCI);
        expect($moduliLavoro)->toContain(Catalog::MOD_BILANCIO_CEE);
        expect($moduliLavoro)->not->toContain(Catalog::MOD_ETS);

        // Cooperativa sociale: ha ANCHE modulo ETS
        $moduliSocA = Catalog::moduliAbilitati(Tenant::FG_COOP_SOCIALE_A);
        expect($moduliSocA)->toContain(Catalog::MOD_COOPERATIVE);
        expect($moduliSocA)->toContain(Catalog::MOD_ETS);
    });

    // SRL
    it('SRL ha bilancio CEE e IVA, non ha soci ne cooperative', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_SRL);
        expect($moduli)->toContain(Catalog::MOD_BILANCIO_CEE);
        expect($moduli)->toContain(Catalog::MOD_IVA);
        expect($moduli)->toContain(Catalog::MOD_FATTURE_ATTIVE);
        expect($moduli)->not->toContain(Catalog::MOD_SOCI);
        expect($moduli)->not->toContain(Catalog::MOD_COOPERATIVE);
        expect($moduli)->not->toContain(Catalog::MOD_ETS);
    });

    // SS
    it('SS non ha IVA, né bilancio CEE, né fatture passive obbligatorie', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_SS);
        expect($moduli)->not->toContain(Catalog::MOD_IVA);
        expect($moduli)->not->toContain(Catalog::MOD_BILANCIO_CEE);
        expect($moduli)->not->toContain(Catalog::MOD_FATTURE_PASSIVE);
    });

    // Forfettario
    it('forfettario non ha IVA, libro giornale ne bilancio CEE', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_FORFETTARIO);
        expect($moduli)->not->toContain(Catalog::MOD_IVA);
        expect($moduli)->not->toContain(Catalog::MOD_LIBRO_GIORNALE);
        expect($moduli)->not->toContain(Catalog::MOD_BILANCIO_CEE);
        expect($moduli)->not->toContain(Catalog::MOD_RATEI_RISCONTI);
        // Ha fatture (per verifica compensi forfettari)
        expect($moduli)->toContain(Catalog::MOD_FATTURE_ATTIVE);
    });

    // Override regime forfettario su ditta individuale
    it('ditta individuale con regime forfettario perde IVA e libro giornale', function () {
        $moduli = Catalog::moduliAbilitati(
            Tenant::FG_DITTA_IND,
            Tenant::DIM_NON_APPLICABILE,
            Tenant::RC_FORFETTARIO
        );
        expect($moduli)->not->toContain(Catalog::MOD_IVA);
        expect($moduli)->not->toContain(Catalog::MOD_LIBRO_GIORNALE);
        expect($moduli)->not->toContain(Catalog::MOD_BILANCIO_CEE);
    });

    // Studio professionale
    it('studio professionale ha compensi_terzi e non ha moduli cooperative/soci', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_STUDIO_PROF);
        expect($moduli)->toContain(Catalog::MOD_COMPENSI_TERZI);
        expect($moduli)->not->toContain(Catalog::MOD_COOPERATIVE);
        expect($moduli)->not->toContain(Catalog::MOD_ETS);
        expect($moduli)->not->toContain(Catalog::MOD_SOCI);
    });

    // Associazione non ETS
    it('associazione non ETS non ha moduli ETS, IVA, fatture attive', function () {
        $moduli = Catalog::moduliAbilitati(Tenant::FG_ASS_NON_ETS);
        expect($moduli)->not->toContain(Catalog::MOD_ETS);
        expect($moduli)->not->toContain(Catalog::MOD_IVA);
        expect($moduli)->not->toContain(Catalog::MOD_FATTURE_ATTIVE);
        expect($moduli)->toContain(Catalog::MOD_SOCI);
        expect($moduli)->toContain(Catalog::MOD_RENDICONTO_CASSA);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 3. moduloAbilitato() helper
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → moduloAbilitato()', function () {

    it('restituisce true per modulo presente', function () {
        expect(Catalog::moduloAbilitato(Tenant::FG_SRL, Catalog::MOD_BILANCIO_CEE))->toBeTrue();
        expect(Catalog::moduloAbilitato(Tenant::FG_ETS_ODV, Catalog::MOD_ETS))->toBeTrue();
    });

    it('restituisce false per modulo assente', function () {
        expect(Catalog::moduloAbilitato(Tenant::FG_SRL, Catalog::MOD_COOPERATIVE))->toBeFalse();
        expect(Catalog::moduloAbilitato(Tenant::FG_FORFETTARIO, Catalog::MOD_IVA))->toBeFalse();
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Schema bilancio
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → schemaBilancio()', function () {

    it('ETS restituisce sempre schema ETS-D', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_ETS_ODV))->toBe(Catalog::SCHEMA_ETS_D);
        expect(Catalog::schemaBilancio(Tenant::FG_ETS_APS))->toBe(Catalog::SCHEMA_ETS_D);
        expect(Catalog::schemaBilancio(Tenant::FG_ETS_FONDAZIONE))->toBe(Catalog::SCHEMA_ETS_D);
    });

    it('cooperative restituiscono sempre schema cooperativa', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_COOP_LAVORO))->toBe(Catalog::SCHEMA_COOPERATIVA);
        expect(Catalog::schemaBilancio(Tenant::FG_COOP_SOCIALE_A))->toBe(Catalog::SCHEMA_COOPERATIVA);
    });

    it('SRL con dimensione micro restituisce schema micro', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_SRL, Tenant::DIM_MICRO))->toBe(Catalog::SCHEMA_CEE_MICRO);
    });

    it('SRL con dimensione abbreviato restituisce schema abbreviato', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_SRL, Tenant::DIM_ABBREVIATO))->toBe(Catalog::SCHEMA_CEE_ABBREVIATO);
    });

    it('SRL con dimensione ordinario restituisce schema CEE ordinario', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_SRL, Tenant::DIM_ORDINARIO_CEE))->toBe(Catalog::SCHEMA_CEE_ORDINARIO);
    });

    it('forfettario e SS restituiscono schema nessuno', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_FORFETTARIO))->toBe(Catalog::SCHEMA_NESSUNO);
        expect(Catalog::schemaBilancio(Tenant::FG_SS))->toBe(Catalog::SCHEMA_NESSUNO);
    });

    it('libero professionista senza dimensione restituisce schema rendiconto', function () {
        expect(Catalog::schemaBilancio(Tenant::FG_LIBERO_PROF))->toBe(Catalog::SCHEMA_RENDICONTO);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Piano dei conti template
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → pianoContiTemplate()', function () {

    it('ETS usa template ETS', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_ETS_ODV))->toBe(Catalog::PDC_ETS);
        expect(Catalog::pianoContiTemplate(Tenant::FG_ETS_APS))->toBe(Catalog::PDC_ETS);
    });

    it('cooperative usano template cooperativa', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_COOP_LAVORO))->toBe(Catalog::PDC_COOPERATIVA);
        expect(Catalog::pianoContiTemplate(Tenant::FG_COOP_SOCIALE_B))->toBe(Catalog::PDC_COOPERATIVA);
    });

    it('SRL e SPA usano template CEE ordinario', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_SRL))->toBe(Catalog::PDC_CEE_ORDINARIO);
        expect(Catalog::pianoContiTemplate(Tenant::FG_SPA))->toBe(Catalog::PDC_CEE_ORDINARIO);
    });

    it('SRLS, SAS, SNC usano template CEE abbreviato', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_SRLS))->toBe(Catalog::PDC_CEE_ABBREVIATO);
        expect(Catalog::pianoContiTemplate(Tenant::FG_SAS))->toBe(Catalog::PDC_CEE_ABBREVIATO);
        expect(Catalog::pianoContiTemplate(Tenant::FG_SNC))->toBe(Catalog::PDC_CEE_ABBREVIATO);
    });

    it('professionisti usano template professionista', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_LIBERO_PROF))->toBe(Catalog::PDC_PROFESSIONISTA);
        expect(Catalog::pianoContiTemplate(Tenant::FG_STUDIO_PROF))->toBe(Catalog::PDC_PROFESSIONISTA);
        expect(Catalog::pianoContiTemplate(Tenant::FG_DITTA_IND))->toBe(Catalog::PDC_PROFESSIONISTA);
    });

    it('forfettario usa template forfettario', function () {
        expect(Catalog::pianoContiTemplate(Tenant::FG_FORFETTARIO))->toBe(Catalog::PDC_FORFETTARIO);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Dimensioni e regimi disponibili
// ─────────────────────────────────────────────────────────────────────────────

describe('Catalog → dimensioni e regimi disponibili', function () {

    it('SRL ha micro, abbreviato e ordinario come dimensioni', function () {
        $dim = Catalog::dimensioniDisponibili(Tenant::FG_SRL);
        expect($dim)->toContain(Tenant::DIM_MICRO);
        expect($dim)->toContain(Tenant::DIM_ABBREVIATO);
        expect($dim)->toContain(Tenant::DIM_ORDINARIO_CEE);
    });

    it('ETS ha solo dimensione ETS-D', function () {
        $dim = Catalog::dimensioniDisponibili(Tenant::FG_ETS_ODV);
        expect($dim)->toContain(Tenant::DIM_ETS_D);
        expect($dim)->not->toContain(Tenant::DIM_MICRO);
        expect($dim)->not->toContain(Tenant::DIM_ORDINARIO_CEE);
    });

    it('forfettario ha solo regime forfettario contabile', function () {
        $rc = Catalog::regimiContabiliDisponibili(Tenant::FG_FORFETTARIO);
        expect($rc)->toBe([Tenant::RC_FORFETTARIO]);
    });

    it('ditta individuale può essere ordinario, semplificato o forfettario', function () {
        $rc = Catalog::regimiContabiliDisponibili(Tenant::FG_DITTA_IND);
        expect($rc)->toContain(Tenant::RC_ORDINARIO);
        expect($rc)->toContain(Tenant::RC_SEMPLIFICATO);
        expect($rc)->toContain(Tenant::RC_FORFETTARIO);
    });

    it('cooperativa agricola ha regime IVA agricolo disponibile', function () {
        $iva = Catalog::regimiIvaDisponibili(Tenant::FG_COOP_AGRICOLA);
        expect($iva)->toContain(Tenant::IVA_AGRICOLO);
    });

    it('ETS può essere esente IVA', function () {
        $iva = Catalog::regimiIvaDisponibili(Tenant::FG_ETS_ODV);
        expect($iva)->toContain(Tenant::IVA_ESENTE);
    });

});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Tenant model — helper methods
// ─────────────────────────────────────────────────────────────────────────────

describe('Tenant → helper methods W1', function () {

    it('isETS() true per forme giuridiche ETS', function () {
        foreach ([Tenant::FG_ETS_ODV, Tenant::FG_ETS_APS, Tenant::FG_ETS_FONDAZIONE, Tenant::FG_ETS_GENERICO] as $fg) {
            expect(tenantConForma($fg)->isETS())->toBeTrue("isETS() deve essere true per {$fg}");
        }
    });

    it('isCooperativa() true per forme giuridiche cooperative', function () {
        foreach ([Tenant::FG_COOP_LAVORO, Tenant::FG_COOP_SOCIALE_A, Tenant::FG_COOP_AGRICOLA] as $fg) {
            expect(tenantConForma($fg)->isCooperativa())->toBeTrue("isCooperativa() deve essere true per {$fg}");
        }
    });

    it('isSocietaCapitali() true solo per SRL/SRLS/SPA/SAPA', function () {
        foreach ([Tenant::FG_SRL, Tenant::FG_SRLS, Tenant::FG_SPA, Tenant::FG_SAPA] as $fg) {
            expect(tenantConForma($fg)->isSocietaCapitali())->toBeTrue();
        }
        expect(tenantConForma(Tenant::FG_SAS)->isSocietaCapitali())->toBeFalse();
        expect(tenantConForma(Tenant::FG_ETS_ODV)->isSocietaCapitali())->toBeFalse();
    });

    it('isSocietaPersone() true solo per SAS/SNC/SS', function () {
        foreach ([Tenant::FG_SAS, Tenant::FG_SNC, Tenant::FG_SS] as $fg) {
            expect(tenantConForma($fg)->isSocietaPersone())->toBeTrue();
        }
        expect(tenantConForma(Tenant::FG_SRL)->isSocietaPersone())->toBeFalse();
    });

    it('isProfessionista() true per ditta individuale e professionisti', function () {
        foreach ([Tenant::FG_DITTA_IND, Tenant::FG_LIBERO_PROF, Tenant::FG_STUDIO_PROF] as $fg) {
            expect(tenantConForma($fg)->isProfessionista())->toBeTrue();
        }
    });

    it('isForfettario() true se forma_giuridica=forfettario o regime_contabile=forfettario', function () {
        expect(tenantConForma(Tenant::FG_FORFETTARIO)->isForfettario())->toBeTrue();
        $t = tenantConForma(Tenant::FG_DITTA_IND, ['regime_contabile' => Tenant::RC_FORFETTARIO]);
        expect($t->isForfettario())->toBeTrue();
    });

    it('wizardCompletato() false quando wizard_completato_at è null', function () {
        $t = new Tenant();
        $t->wizard_completato_at = null;
        expect($t->wizardCompletato())->toBeFalse();
        expect($t->wizardPendente())->toBeTrue();
    });

    it('formaGiuridicaLabel() restituisce etichetta leggibile', function () {
        $t = tenantConForma(Tenant::FG_SRL);
        expect($t->formaGiuridicaLabel())->toContain('S.r.l.');
    });

    it('formaGiuridicaLabels() copre tutte le costanti definite nel Tenant', function () {
        $labels = Tenant::formaGiuridicaLabels();
        $costanti = [
            Tenant::FG_ETS_ODV, Tenant::FG_ETS_APS, Tenant::FG_ETS_FONDAZIONE, Tenant::FG_ETS_GENERICO,
            Tenant::FG_COOP_LAVORO, Tenant::FG_COOP_SOCIALE_A, Tenant::FG_COOP_SOCIALE_B,
            Tenant::FG_COOP_AGRICOLA, Tenant::FG_COOP_CONSORTILE, Tenant::FG_COOP_CONSUMO,
            Tenant::FG_COOP_ABITAZIONE, Tenant::FG_COOP_COMUNITA,
            Tenant::FG_SRL, Tenant::FG_SRLS, Tenant::FG_SPA, Tenant::FG_SAPA,
            Tenant::FG_SAS, Tenant::FG_SNC, Tenant::FG_SS,
            Tenant::FG_DITTA_IND, Tenant::FG_LIBERO_PROF, Tenant::FG_STUDIO_PROF,
            Tenant::FG_ASS_NON_ETS, Tenant::FG_FOND_NON_ETS,
            Tenant::FG_FORFETTARIO, Tenant::FG_ALTRO,
        ];
        foreach ($costanti as $c) {
            expect(array_key_exists($c, $labels))->toBeTrue("Label mancante per costante {$c}");
            expect($labels[$c])->not->toBeEmpty("Label vuota per {$c}");
        }
    });

    it('dimensioneBilancioLabel() restituisce stringa non vuota per tutti i valori', function () {
        $valori = [
            Tenant::DIM_MICRO, Tenant::DIM_ABBREVIATO, Tenant::DIM_ORDINARIO_CEE,
            Tenant::DIM_ETS_D, Tenant::DIM_COOPERATIVA, Tenant::DIM_NON_APPLICABILE,
        ];
        foreach ($valori as $v) {
            $t = new Tenant(['dimensione_bilancio' => $v]);
            expect($t->dimensioneBilancioLabel())->not->toBeEmpty("Label vuota per dimensione {$v}");
        }
    });

    it('campi obbligatori cooperative includono numero_iscrizione_albo_coop', function () {
        $campi = Catalog::campiObbligatori(Tenant::FG_COOP_LAVORO);
        expect($campi)->toContain('numero_iscrizione_albo_coop');
    });

    it('campi obbligatori forfettario includono attivita_ateco', function () {
        $campi = Catalog::campiObbligatori(Tenant::FG_FORFETTARIO);
        expect($campi)->toContain('attivita_ateco');
    });

    it('isETS() backward-compat: funziona anche senza forma_giuridica (solo organization_type)', function () {
        $t = new Tenant();
        $t->forma_giuridica   = null;
        $t->organization_type = 'ets';
        expect($t->isETS())->toBeTrue();

        $t2 = new Tenant();
        $t2->forma_giuridica   = null;
        $t2->organization_type = 'cooperative';
        expect($t2->isCooperativa())->toBeTrue();
    });

});
