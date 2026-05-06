<?php

use App\Http\Controllers\AccountingReportController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\RendicontoCassaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ElezioneController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\IncaricoController;
use App\Http\Controllers\OrganoController;
use App\Http\Controllers\CaricaSocialeController;
use App\Http\Controllers\ExpenseRefundController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\IncassoController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\CooperativeShareController;
use App\Http\Controllers\PrestitoSocialeController;
use App\Http\Controllers\RistorniController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberInviteController;
use App\Http\Controllers\MemberTypeController;
use App\Http\Controllers\ContoController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PublicDownloadController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpesaController;
use App\Http\Controllers\PrimaNotaController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceiptTemplateController;
use App\Http\Controllers\SaasController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerbaleController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\EsercizioContabileController;
use App\Http\Controllers\RateiRiscontiController;
use App\Http\Controllers\ScadenzaController;
use App\Http\Controllers\ScadenzarioController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\IvaController;
use App\Http\Controllers\FatturaPassivaController;
use App\Http\Controllers\FatturaAttivaController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CespitiController;
use App\Http\Controllers\AmmortamentoController;
use App\Http\Controllers\AssetCategoryController;
use App\Http\Controllers\DismissioneCespitiController;
use App\Http\Controllers\CompensaTerziController;
use App\Http\Controllers\ModelloF24Controller;
use App\Http\Controllers\BilancioController;
use App\Http\Controllers\ErogazioneLiberaleController;
use App\Http\Controllers\RelazioneMissioneController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CentriDiCostoController;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route SaaS (piattaforma)
|--------------------------------------------------------------------------
*/

// Landing page e pagine marketing
Route::get('/', [SaasController::class, 'landing'])->name('saas.landing');
Route::get('/pricing', [SaasController::class, 'pricing'])->name('saas.pricing');

// Registrazione nuovo tenant
Route::middleware('guest')->group(function () {
    Route::get('/register', [TenantRegistrationController::class, 'showRegistrationForm'])->name('saas.register');
    Route::post('/register', [TenantRegistrationController::class, 'register'])->name('saas.register.store');
});

// Selezione tenant dopo login (se utente appartiene a più tenant)
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/select-tenant', [SaasController::class, 'selectTenant'])->name('saas.select-tenant');
    Route::post('/switch-tenant/{tenant}', [SaasController::class, 'switchTenant'])->name('saas.switch-tenant');
});

/*
|--------------------------------------------------------------------------
| Route Installer (setup iniziale piattaforma)
|--------------------------------------------------------------------------
*/

Route::middleware(['install'])->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::get('/back', [InstallController::class, 'back'])->name('back');
    Route::get('/database', [InstallController::class, 'showDatabaseForm'])->name('database.form');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database');
    Route::get('/skip-smtp', [InstallController::class, 'skipSmtp'])->name('skip-smtp');
    Route::post('/smtp', [InstallController::class, 'storeSmtp'])->name('smtp');
    Route::post('/complete', [InstallController::class, 'complete'])->name('complete');
});

/*
|--------------------------------------------------------------------------
| Route Sito Pubblico Tenant
|--------------------------------------------------------------------------
*/

// Homepage pubblica del tenant
Route::get('/org/{tenant}', PublicSiteController::class)->name('home');

// Domanda di ammissione socio (link invito email) - pubblico, con throttle
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/org/{tenant}/admission-request/{token}', [MemberInviteController::class, 'showAdmissionRequestForm'])->name('members.admission-request.form');
});
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/org/{tenant}/admission-request/{token}', [MemberInviteController::class, 'storeAdmissionRequest'])->name('members.admission-request.store');
});

// Download pubblici firmati
Route::middleware('signed')->group(function () {
    Route::get('/public/logo/{attachment}', [PublicDownloadController::class, 'logo'])->name('public.logo.show');
    Route::get('/public/statuto/{attachment}', [PublicDownloadController::class, 'statuto'])->name('public.statuto.download');
    Route::get('/public/rendiconto/{anno}', [PublicDownloadController::class, 'rendiconto'])->name('public.rendiconto.download');
    Route::get('/public/section-background/{sectionId}/{attachment}', [PublicDownloadController::class, 'sectionBackground'])->name('public.section-background.show');
});

/*
|--------------------------------------------------------------------------
| Route Super Admin (gestione piattaforma)
|--------------------------------------------------------------------------
*/

// ── OpenAPI Documentation (A6) ───────────────────────────────────────────
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/api/docs',      [ApiDocsController::class, 'index'])->name('api-docs.index');
    Route::get('/api/docs/spec', [ApiDocsController::class, 'spec'])->name('api-docs.spec');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants', [AdminController::class, 'tenants'])->name('tenants');
    Route::get('/tenants/create', [AdminController::class, 'createTenant'])->name('tenants.create');
    Route::post('/tenants', [AdminController::class, 'storeTenant'])->name('tenants.store');
    Route::get('/tenants/{tenant}/wizard', [AdminController::class, 'wizard'])->name('tenants.wizard');
    Route::post('/tenants/{tenant}/wizard', [AdminController::class, 'wizardSave'])->name('tenants.wizard.save');
    Route::get('/tenants/{tenant}', [AdminController::class, 'showTenant'])->name('tenants.show');
    Route::put('/tenants/{tenant}', [AdminController::class, 'updateTenant'])->name('tenants.update');
    Route::delete('/tenants/{tenant}', [AdminController::class, 'destroyTenant'])->name('tenants.destroy');
    Route::post('/tenants/{tenant}/toggle-active', [AdminController::class, 'toggleTenantActive'])->name('tenants.toggle-active');
    Route::post('/tenants/{tenant}/seed', [AdminController::class, 'seedTenant'])->name('tenants.seed');
});

/*
|--------------------------------------------------------------------------
| Route App Tenant (area autenticata con contesto organizzazione)
|--------------------------------------------------------------------------
|
| Tutte le route sotto /app/{tenant}/... richiedono:
| - Autenticazione
| - Risoluzione tenant valido
| - Appartenenza utente al tenant
|
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'tenant',
])->prefix('app/{tenant}')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Utenti (solo admin)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
    Route::put('users/{user}/member', [UserController::class, 'linkMember'])->name('users.member.update');
    Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordResetLink'])->name('users.send-password-reset');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Soci e volontari
    Route::get('members/invites/create', [MemberInviteController::class, 'create'])->name('members.invites.create')->middleware('role:admin,segreteria');
    Route::post('members/invites', [MemberInviteController::class, 'store'])->name('members.invites.store')->middleware('role:admin,segreteria');
    Route::resource('members', MemberController::class);
    Route::post('members/accept-admission-bulk', [MemberController::class, 'acceptAdmissionBulk'])->name('members.accept-admission-bulk');
    Route::post('members/{member}/accept-admission', [MemberController::class, 'acceptAdmission'])->name('members.accept-admission');
    Route::post('members/{member}/reject-admission', [MemberController::class, 'rejectAdmission'])->name('members.reject-admission');
    Route::post('members/{member}/communicate-rejection', [MemberController::class, 'communicateRejection'])->name('members.communicate-rejection');
    Route::post('members/{member}/register-appeal', [MemberController::class, 'registerAppeal'])->name('members.register-appeal');
    Route::post('members/{member}/assembly-outcome', [MemberController::class, 'assemblyOutcome'])->name('members.assembly-outcome');
    Route::post('members/{member}/register-death', [MemberController::class, 'registerDeath'])->name('members.register-death');
    Route::post('members/{member}/register-morosita', [MemberController::class, 'registerMorosita'])->name('members.register-morosita');
    Route::post('members/{member}/register-dimissioni', [MemberController::class, 'registerDimissioni'])->name('members.register-dimissioni');
    Route::post('members/{member}/register-esclusione', [MemberController::class, 'registerEsclusione'])->name('members.register-esclusione');
    Route::post('members/{member}/enable-access', [MemberController::class, 'enableMemberAccess'])->name('members.enable-access');
    Route::post('members/{member}/revoke-access', [MemberController::class, 'revokeMemberAccess'])->name('members.revoke-access');
    Route::put('members/{member}/user-roles', [MemberController::class, 'updateMemberUserRoles'])->name('members.user-roles.update')->middleware('role:admin');
    Route::post('members/{member}/incarichi', [IncaricoController::class, 'store'])->name('members.incarichi.store');
    Route::put('incarichi/{incarico}', [IncaricoController::class, 'update'])->name('incarichi.update');
    Route::delete('incarichi/{incarico}', [IncaricoController::class, 'destroy'])->name('incarichi.destroy');
    Route::get('libro-soci', [MemberController::class, 'libroSoci'])->name('libro-soci.index');
    Route::get('libro-soci/export-coop', [MemberController::class, 'exportLibroSociCoop'])->name('libro-soci.export-coop');

    // Capitale sociale (solo cooperative)
    Route::middleware(['cooperative'])->prefix('capitale-sociale')->name('capitale-sociale.')->group(function () {
        Route::get('/', [CooperativeShareController::class, 'index'])->name('index');
        Route::get('/create', [CooperativeShareController::class, 'create'])->name('create');
        Route::post('/', [CooperativeShareController::class, 'store'])->name('store');
        Route::get('/export', [CooperativeShareController::class, 'export'])->name('export');
        Route::get('/{share}', [CooperativeShareController::class, 'show'])->name('show');
        Route::post('/{share}/versa', [CooperativeShareController::class, 'versa'])->name('versa');
        Route::post('/{share}/riscatta', [CooperativeShareController::class, 'riscatta'])->name('riscatta');
    });

    // Prestito Sociale (solo cooperative)
    Route::middleware(['cooperative'])->prefix('prestito-sociale')->name('prestito-sociale.')->group(function () {
        Route::get('/', [PrestitoSocialeController::class, 'index'])->name('index');
        Route::get('/create', [PrestitoSocialeController::class, 'create'])->name('create');
        Route::post('/', [PrestitoSocialeController::class, 'store'])->name('store');
        Route::post('/calcola-interessi', [PrestitoSocialeController::class, 'calcolaInteressi'])->name('calcola-interessi');
        Route::get('/{libretto}', [PrestitoSocialeController::class, 'show'])->name('show');
        Route::post('/{libretto}/deposita', [PrestitoSocialeController::class, 'deposita'])->name('deposita');
        Route::post('/{libretto}/preleva', [PrestitoSocialeController::class, 'preleva'])->name('preleva');
        Route::post('/{libretto}/chiudi', [PrestitoSocialeController::class, 'chiudi'])->name('chiudi');
        Route::get('/{libretto}/export', [PrestitoSocialeController::class, 'exportEstrattoConto'])->name('export');
    });

    // Ristorni ai soci (solo cooperative)
    Route::middleware(['cooperative'])->prefix('ristorni')->name('ristorni.')->group(function () {
        Route::get('/', [RistorniController::class, 'index'])->name('index');
        Route::get('/create', [RistorniController::class, 'create'])->name('create');
        Route::post('/', [RistorniController::class, 'store'])->name('store');
        Route::get('/{ristorno}', [RistorniController::class, 'show'])->name('show');
        Route::post('/{ristorno}/pagato', [RistorniController::class, 'markPagato'])->name('mark-pagato');
        Route::post('/{ristorno}/annulla', [RistorniController::class, 'annulla'])->name('annulla');
        Route::get('/{ristorno}/export', [RistorniController::class, 'export'])->name('export');
    });

    Route::resource('member-types', MemberTypeController::class)->except(['show']);
    Route::get('organi', [OrganoController::class, 'index'])->name('organi.index');
    Route::get('organi/{organo:slug}', [OrganoController::class, 'show'])->name('organi.show');
    Route::put('organi/{organo:slug}', [OrganoController::class, 'update'])->name('organi.update');

    // ── Cariche Sociali (A1) ───────────────────────────────────────────────
    Route::get('cariche-sociali',                   [CaricaSocialeController::class, 'index'])->name('cariche-sociali.index');
    Route::get('cariche-sociali/create',            [CaricaSocialeController::class, 'create'])->name('cariche-sociali.create')->middleware('role:admin');
    Route::post('cariche-sociali',                  [CaricaSocialeController::class, 'store'])->name('cariche-sociali.store')->middleware('role:admin');
    Route::get('cariche-sociali/{caricaSociale}',   [CaricaSocialeController::class, 'show'])->name('cariche-sociali.show');
    Route::get('cariche-sociali/{caricaSociale}/edit', [CaricaSocialeController::class, 'edit'])->name('cariche-sociali.edit')->middleware('role:admin');
    Route::put('cariche-sociali/{caricaSociale}',   [CaricaSocialeController::class, 'update'])->name('cariche-sociali.update')->middleware('role:admin');
    Route::delete('cariche-sociali/{caricaSociale}',[CaricaSocialeController::class, 'destroy'])->name('cariche-sociali.destroy')->middleware('role:admin');
    Route::middleware('role:admin,segreteria')->group(function () {
        Route::get('elezioni', [ElezioneController::class, 'index'])->name('elezioni.index');
        Route::get('elezioni/create', [ElezioneController::class, 'create'])->name('elezioni.create');
        Route::post('elezioni', [ElezioneController::class, 'store'])->name('elezioni.store');
        Route::get('elezioni/{elezione}', [ElezioneController::class, 'show'])->name('elezioni.show');
        Route::get('elezioni/{elezione}/edit', [ElezioneController::class, 'edit'])->name('elezioni.edit');
        Route::put('elezioni/{elezione}', [ElezioneController::class, 'update'])->name('elezioni.update');
        Route::delete('elezioni/{elezione}', [ElezioneController::class, 'destroy'])->name('elezioni.destroy');
        Route::post('elezioni/{elezione}/open', [ElezioneController::class, 'open'])->name('elezioni.open');
        Route::post('elezioni/{elezione}/close', [ElezioneController::class, 'close'])->name('elezioni.close');
        Route::post('elezioni/{elezione}/invalida', [ElezioneController::class, 'invalida'])->name('elezioni.invalida');
        Route::post('elezioni/{elezione}/candidati', [ElezioneController::class, 'addCandidato'])->name('elezioni.candidati.store');
        Route::delete('elezioni/{elezione}/candidati/{candidatura}', [ElezioneController::class, 'removeCandidato'])->name('elezioni.candidati.destroy');
        Route::get('elezioni/{elezione}/risultati', [ElezioneController::class, 'risultati'])->name('elezioni.risultati');
    });
    Route::get('elezioni/{elezione}/vota', [ElezioneController::class, 'vota'])->name('elezioni.vota');
    Route::post('elezioni/{elezione}/vota', [ElezioneController::class, 'storeVoto'])->name('elezioni.vota.store');
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
    Route::delete('settings/logo', [SettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
    Route::post('settings/site-section-background/{sectionId}', [SettingsController::class, 'uploadSectionBackground'])->name('settings.site-section-background.upload');
    Route::delete('settings/site-section-background/{sectionId}', [SettingsController::class, 'deleteSectionBackground'])->name('settings.site-section-background.delete');
    Route::post('settings/test-email', [SettingsController::class, 'sendTestEmail'])->name('settings.test-email');
    Route::get('settings/letterhead-preview', [SettingsController::class, 'letterheadPreview'])->name('settings.letterhead-preview');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // File manager (sola lettura, stile cloud drive)
    Route::get('file', [MediaController::class, 'index'])->name('file.index')->middleware('role:admin,segreteria');

    // Modulo IVA
    Route::middleware('cooperative')->group(function () {
        Route::get('iva', [IvaController::class, 'dashboard'])->name('iva.dashboard');
        Route::get('iva/codici', [IvaController::class, 'codiciIndex'])->name('iva.codici.index');
        Route::get('iva/codici/create', [IvaController::class, 'codiciCreate'])->name('iva.codici.create')->middleware('role:admin,contabile');
        Route::post('iva/codici', [IvaController::class, 'codiciStore'])->name('iva.codici.store')->middleware('role:admin,contabile');
        Route::get('iva/codici/{codiceIva}', [IvaController::class, 'codiceShow'])->name('iva.codici.show');
        Route::get('iva/codici/{codiceIva}/edit', [IvaController::class, 'codiciEdit'])->name('iva.codici.edit')->middleware('role:admin,contabile');
        Route::put('iva/codici/{codiceIva}', [IvaController::class, 'codiciUpdate'])->name('iva.codici.update')->middleware('role:admin,contabile');
        Route::delete('iva/codici/{codiceIva}', [IvaController::class, 'codiciDestroy'])->name('iva.codici.destroy')->middleware('role:admin');
        // ── Fatture Passive CRUD (C2) ──────────────────────────────────────
        Route::get('iva/fatture-passive', [FatturaPassivaController::class, 'index'])->name('iva.fatture-passive.index');
        Route::get('iva/fatture-passive/create', [FatturaPassivaController::class, 'create'])->name('iva.fatture-passive.create')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive', [FatturaPassivaController::class, 'store'])->name('iva.fatture-passive.store')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive/preview-totali', [FatturaPassivaController::class, 'previewTotali'])->name('iva.fatture-passive.preview-totali');
        Route::get('iva/fatture-passive/{fatturaPassiva}', [FatturaPassivaController::class, 'show'])->name('iva.fatture-passive.show');
        Route::get('iva/fatture-passive/{fatturaPassiva}/edit', [FatturaPassivaController::class, 'edit'])->name('iva.fatture-passive.edit')->middleware('role:admin,segreteria,contabile');
        Route::put('iva/fatture-passive/{fatturaPassiva}', [FatturaPassivaController::class, 'update'])->name('iva.fatture-passive.update')->middleware('role:admin,segreteria,contabile');
        Route::delete('iva/fatture-passive/{fatturaPassiva}', [FatturaPassivaController::class, 'destroy'])->name('iva.fatture-passive.destroy')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive/{fatturaPassiva}/marca-pagata', [FatturaPassivaController::class, 'marcaPagata'])->name('iva.fatture-passive.marca-pagata')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive/{fatturaPassiva}/marca-parzialmente-pagata', [FatturaPassivaController::class, 'marcaParzialmentePagata'])->name('iva.fatture-passive.marca-parzialmente-pagata')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive/{fatturaPassiva}/reimposta-da-pagare', [FatturaPassivaController::class, 'reimpostaDaPagare'])->name('iva.fatture-passive.reimposta-da-pagare')->middleware('role:admin,segreteria,contabile');
        Route::post('iva/fatture-passive/{fatturaPassiva}/annulla', [FatturaPassivaController::class, 'annulla'])->name('iva.fatture-passive.annulla')->middleware('role:admin,segreteria,contabile');
        Route::get('iva/liquidazioni', [IvaController::class, 'liquidazioniIndex'])->name('iva.liquidazioni.index');
        Route::get('iva/liquidazioni/{liquidazioneIva}', [IvaController::class, 'liquidazioneShow'])->name('iva.liquidazioni.show');
        // Registri e liquidazione periodica
        Route::get('iva/registro-acquisti', [IvaController::class, 'registroAcquisti'])->name('iva.registro-acquisti');
        Route::get('iva/registro-vendite', [IvaController::class, 'registroVendite'])->name('iva.registro-vendite');
        Route::get('iva/liquidazione', [IvaController::class, 'liquidazione'])->name('iva.liquidazione');
        Route::post('iva/liquidazione/chiudi', [IvaController::class, 'chiudiLiquidazione'])->name('iva.liquidazione.chiudi');
    });
    Route::get('file/download', [MediaController::class, 'download'])->name('file.download')->middleware('role:admin,segreteria');
    Route::get('file/preview', [MediaController::class, 'preview'])->name('file.preview')->middleware('role:admin,segreteria');

    // Quote sociali e erogazioni liberali (pagine distinte); create/show condivisi
    Route::get('quote-sociali', [IncassoController::class, 'indexQuote'])->name('quote-sociali.index');
    Route::get('donazioni', [IncassoController::class, 'indexDonazioni'])->name('donazioni.index');
    Route::get('incassi-generici', [IncassoController::class, 'indexIncassiGenerici'])->name('incassi-generici.index');
    Route::get('incassi', [IncassoController::class, 'index'])->name('incassi.index');
    Route::get('incassi/create', [IncassoController::class, 'create'])->name('incassi.create');
    Route::post('incassi', [IncassoController::class, 'store'])->name('incassi.store');
    Route::get('incassi/{incasso}', [IncassoController::class, 'show'])->name('incassi.show');
    Route::get('incassi/{incasso}/edit', [IncassoController::class, 'edit'])->name('incassi.edit');
    Route::put('incassi/{incasso}', [IncassoController::class, 'update'])->name('incassi.update');
    Route::delete('incassi/{incasso}', [IncassoController::class, 'destroy'])->name('incassi.destroy');
    Route::post('incassi/{incasso}/attachments', [IncassoController::class, 'storeAttachment'])->name('incassi.attachments.store');
    Route::delete('incassi/{incasso}/attachments/{attachment}', [IncassoController::class, 'destroyAttachment'])->name('incassi.attachments.destroy');
    Route::get('donations', fn () => redirect()->route('donazioni.index', ['tenant' => request()->route('tenant')]))->name('donations.redirect');
    Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download'])->name('receipts.download');
    Route::post('receipts/{receipt}/send-email', [ReceiptController::class, 'sendEmail'])->name('receipts.send-email');
    Route::post('receipts/{receipt}/regenerate', [ReceiptController::class, 'regenerate'])->name('receipts.regenerate');

    // ── Anagrafica Fornitori (C1) ──────────────────────────────────────────
    Route::resource('suppliers', SupplierController::class);

    // Spese (uscite di cassa con opzione prima nota e voce rendiconto)
    Route::get('spese', [SpesaController::class, 'index'])->name('spese.index');
    Route::get('spese/create', [SpesaController::class, 'create'])->name('spese.create');
    Route::post('spese', [SpesaController::class, 'store'])->name('spese.store');
    Route::get('spese/{spesa}', [SpesaController::class, 'show'])->name('spese.show');
    Route::get('spese/{spesa}/edit', [SpesaController::class, 'edit'])->name('spese.edit');
    Route::put('spese/{spesa}', [SpesaController::class, 'update'])->name('spese.update');
    Route::delete('spese/{spesa}', [SpesaController::class, 'destroy'])->name('spese.destroy');
    Route::post('spese/{spesa}/attachments', [SpesaController::class, 'storeAttachment'])->name('spese.attachments.store');
    Route::delete('spese/{spesa}/attachments/{attachment}', [SpesaController::class, 'destroyAttachment'])->name('spese.attachments.destroy');

    // Rimborsi spese (richiesta → approvazione con contabilizzazione automatica)
    Route::post('expense-refunds/{expense_refund}/approva', [ExpenseRefundController::class, 'approva'])->name('expense-refunds.approva')->middleware('role:admin,contabile');
    Route::post('expense-refunds/{expense_refund}/attachments', [ExpenseRefundController::class, 'storeAttachment'])->name('expense-refunds.attachments.store');
    Route::delete('expense-refunds/{expense_refund}/attachments/{attachment}', [ExpenseRefundController::class, 'destroyAttachment'])->name('expense-refunds.attachments.destroy');
    Route::get('expense-refunds', [ExpenseRefundController::class, 'index'])->name('expense-refunds.index');
    Route::get('expense-refunds/create', [ExpenseRefundController::class, 'create'])->name('expense-refunds.create');
    Route::post('expense-refunds', [ExpenseRefundController::class, 'store'])->name('expense-refunds.store');
    Route::get('expense-refunds/{expense_refund}', [ExpenseRefundController::class, 'show'])->name('expense-refunds.show');
    Route::put('expense-refunds/{expense_refund}', [ExpenseRefundController::class, 'update'])->name('expense-refunds.update');
    Route::get('expense-refunds/{expense_refund}/print', [ExpenseRefundController::class, 'print'])->name('expense-refunds.print');

    // Contabilità
    Route::resource('conti', ContoController::class)->except(['show']);
    Route::get('prima-nota', [PrimaNotaController::class, 'index'])->name('prima-nota.index');
    Route::get('prima-nota/create', [PrimaNotaController::class, 'create'])->name('prima-nota.create');
    Route::post('prima-nota', [PrimaNotaController::class, 'store'])->name('prima-nota.store');
    Route::get('prima-nota/giroconto', [PrimaNotaController::class, 'createGiroconto'])->name('prima-nota.giroconto.create');
    Route::post('prima-nota/giroconto', [PrimaNotaController::class, 'storeGiroconto'])->name('prima-nota.giroconto.store');
    Route::get('prima-nota/{prima_nota_entry}/edit', [PrimaNotaController::class, 'edit'])->name('prima-nota.edit');
    Route::put('prima-nota/{prima_nota_entry}', [PrimaNotaController::class, 'update'])->name('prima-nota.update');
    Route::delete('prima-nota/{prima_nota_entry}', [PrimaNotaController::class, 'destroy'])->name('prima-nota.destroy');
    Route::get('reports/accounting', [AccountingReportController::class, 'index'])->name('reports.accounting');
    Route::get('reports/accounting/export', [AccountingReportController::class, 'export'])->name('reports.accounting.export');
    Route::get('reports/conto-economico', [AccountingReportController::class, 'contoEconomico'])->name('reports.conto-economico');
    Route::get('reports/conto-economico/export', [AccountingReportController::class, 'exportContoEconomico'])->name('reports.conto-economico.export');
    Route::get('iva/lipe-xml',    [IvaController::class, 'lipeXml'])->name('iva.lipe-xml')->middleware('role:admin,contabile');
    Route::get('iva/acconto-iva', [IvaController::class, 'accontoIva'])->name('iva.acconto-iva')->middleware('role:admin,contabile');
    // ── Fatture Attive (F-ATT) — accessibili a tutti i tipi organizzazione ──
    Route::get('iva/fatture-attive', [FatturaAttivaController::class, 'index'])->name('iva.fatture-attive.index');
    Route::get('iva/fatture-attive/create', [FatturaAttivaController::class, 'create'])->name('iva.fatture-attive.create')->middleware('role:admin,contabile');
    Route::post('iva/fatture-attive', [FatturaAttivaController::class, 'store'])->name('iva.fatture-attive.store')->middleware('role:admin,contabile');
    Route::get('iva/fatture-attive/{fatturaAttiva}', [FatturaAttivaController::class, 'show'])->name('iva.fatture-attive.show');
    Route::get('iva/fatture-attive/{fatturaAttiva}/edit', [FatturaAttivaController::class, 'edit'])->name('iva.fatture-attive.edit')->middleware('role:admin,contabile');
    Route::put('iva/fatture-attive/{fatturaAttiva}', [FatturaAttivaController::class, 'update'])->name('iva.fatture-attive.update')->middleware('role:admin,contabile');
    Route::post('iva/fatture-attive/{fatturaAttiva}/paga', [FatturaAttivaController::class, 'paga'])->name('iva.fatture-attive.paga')->middleware('role:admin,contabile');
    Route::post('iva/fatture-attive/{fatturaAttiva}/storna', [FatturaAttivaController::class, 'storna'])->name('iva.fatture-attive.storna')->middleware('role:admin,contabile');
    Route::get('iva/fatture-attive/{fatturaAttiva}/nota-credito/create', [FatturaAttivaController::class, 'creaNotaCredito'])->name('iva.fatture-attive.crea-nota-credito')->middleware('role:admin,contabile');
    Route::post('iva/fatture-attive/{fatturaAttiva}/nota-credito', [FatturaAttivaController::class, 'storeNotaCredito'])->name('iva.fatture-attive.store-nota-credito')->middleware('role:admin,contabile');
    Route::delete('iva/fatture-attive/{fatturaAttiva}', [FatturaAttivaController::class, 'destroy'])->name('iva.fatture-attive.destroy')->middleware('role:admin,contabile');
    Route::get('iva/fatture-attive/{fatturaAttiva}/pdf', [FatturaAttivaController::class, 'exportPdf'])->name('iva.fatture-attive.pdf');
    Route::get('iva/fatture-attive/{fatturaAttiva}/xml', [FatturaAttivaController::class, 'downloadXml'])->name('iva.fatture-attive.xml');
    Route::post('iva/fatture-attive/{fatturaAttiva}/sdi', [FatturaAttivaController::class, 'aggiornaStatoSdi'])->name('iva.fatture-attive.sdi')->middleware('role:admin,contabile');
    Route::get('reports/libro-giornale',         [AccountingReportController::class, 'libroGiornale'])->name('reports.libro-giornale');
    Route::get('reports/libro-giornale/export',  [AccountingReportController::class, 'exportLibroGiornale'])->name('reports.libro-giornale.export');
    Route::get('reports/registro-vendite',        [AccountingReportController::class, 'registroVendite'])->name('reports.registro-vendite');
    Route::get('reports/registro-vendite/export', [AccountingReportController::class, 'exportRegistroVendite'])->name('reports.registro-vendite.export');

    // ── Compensi a Terzi / Ritenute d'Acconto (G1) ───────────────────────
    Route::get('compensi-terzi',                                      [CompensaTerziController::class, 'index'])->name('compensi-terzi.index')->middleware('role:admin,contabile');
    Route::get('compensi-terzi/create',                               [CompensaTerziController::class, 'create'])->name('compensi-terzi.create')->middleware('role:admin,contabile');
    Route::post('compensi-terzi',                                     [CompensaTerziController::class, 'store'])->name('compensi-terzi.store')->middleware('role:admin,contabile');
    Route::get('compensi-terzi/riepilogo',                            [CompensaTerziController::class, 'riepilogo'])->name('compensi-terzi.riepilogo');
    Route::get('compensi-terzi/versamenti',                           [CompensaTerziController::class, 'versamenti'])->name('compensi-terzi.versamenti');
    Route::post('compensi-terzi/versa',                               [CompensaTerziController::class, 'versa'])->name('compensi-terzi.versa')->middleware('role:admin,contabile');
    Route::get('compensi-terzi/versamenti/{versamento}',              [CompensaTerziController::class, 'versamentoShow'])->name('compensi-terzi.versamento.show');
    Route::get('compensi-terzi/{compensiTerzi}',                      [CompensaTerziController::class, 'show'])->name('compensi-terzi.show');
    Route::get('compensi-terzi/{compensiTerzi}/edit',                 [CompensaTerziController::class, 'edit'])->name('compensi-terzi.edit')->middleware('role:admin,contabile');
    Route::put('compensi-terzi/{compensiTerzi}',                      [CompensaTerziController::class, 'update'])->name('compensi-terzi.update')->middleware('role:admin,contabile');
    Route::delete('compensi-terzi/{compensiTerzi}',                   [CompensaTerziController::class, 'destroy'])->name('compensi-terzi.destroy')->middleware('role:admin,contabile');

    // ── Modello F24 (G2) ─────────────────────────────────────────────────
    Route::get('f24',                         [ModelloF24Controller::class, 'index'])->name('f24.index')->middleware('role:admin,contabile');
    Route::get('f24/create',                  [ModelloF24Controller::class, 'create'])->name('f24.create')->middleware('role:admin,contabile');
    Route::post('f24',                        [ModelloF24Controller::class, 'store'])->name('f24.store')->middleware('role:admin,contabile');
    Route::get('f24/{f24}',                   [ModelloF24Controller::class, 'show'])->name('f24.show');
    Route::get('f24/{f24}/edit',              [ModelloF24Controller::class, 'edit'])->name('f24.edit')->middleware('role:admin,contabile');
    Route::put('f24/{f24}',                   [ModelloF24Controller::class, 'update'])->name('f24.update')->middleware('role:admin,contabile');
    Route::post('f24/{f24}/versa',            [ModelloF24Controller::class, 'segnaVersato'])->name('f24.versa')->middleware('role:admin,contabile');
    Route::delete('f24/{f24}',               [ModelloF24Controller::class, 'destroy'])->name('f24.destroy')->middleware('role:admin,contabile');
    Route::get('f24/{f24}/pdf',              [ModelloF24Controller::class, 'exportPdf'])->name('f24.pdf');
    Route::get('f24/{f24}/xml',              [ModelloF24Controller::class, 'exportXml'])->name('f24.xml');

    // ── Relazione di Missione ETS (G3) ───────────────────────────────────
    Route::get('bilancio/relazione-missione',                                     [RelazioneMissioneController::class, 'index'])->name('relazione-missione.index');
    Route::get('bilancio/relazione-missione/create',                              [RelazioneMissioneController::class, 'create'])->name('relazione-missione.create')->middleware('role:admin,contabile');
    Route::post('bilancio/relazione-missione',                                    [RelazioneMissioneController::class, 'store'])->name('relazione-missione.store')->middleware('role:admin,contabile');
    Route::get('bilancio/relazione-missione/{relazioneMissione}',                 [RelazioneMissioneController::class, 'show'])->name('relazione-missione.show');
    Route::get('bilancio/relazione-missione/{relazioneMissione}/edit',            [RelazioneMissioneController::class, 'edit'])->name('relazione-missione.edit')->middleware('role:admin,contabile');
    Route::put('bilancio/relazione-missione/{relazioneMissione}',                 [RelazioneMissioneController::class, 'update'])->name('relazione-missione.update')->middleware('role:admin,contabile');
    Route::post('bilancio/relazione-missione/{relazioneMissione}/approva',        [RelazioneMissioneController::class, 'approva'])->name('relazione-missione.approva')->middleware('role:admin,contabile');
    Route::delete('bilancio/relazione-missione/{relazioneMissione}',              [RelazioneMissioneController::class, 'destroy'])->name('relazione-missione.destroy')->middleware('role:admin,contabile');
    Route::get('bilancio/relazione-missione/{relazioneMissione}/pdf',             [RelazioneMissioneController::class, 'exportPdf'])->name('relazione-missione.pdf');

    // ── Erogazioni Liberali ETS (G4) ─────────────────────────────────────
    Route::get('bilancio/erogazioni-liberali',                                                [ErogazioneLiberaleController::class, 'index'])->name('erogazioni-liberali.index');
    Route::get('bilancio/erogazioni-liberali/create',                                         [ErogazioneLiberaleController::class, 'create'])->name('erogazioni-liberali.create')->middleware('role:admin,contabile');
    Route::post('bilancio/erogazioni-liberali',                                               [ErogazioneLiberaleController::class, 'store'])->name('erogazioni-liberali.store')->middleware('role:admin,contabile');
    Route::get('bilancio/erogazioni-liberali/riepilogo',                                      [ErogazioneLiberaleController::class, 'riepilogo'])->name('erogazioni-liberali.riepilogo');
    Route::get('bilancio/erogazioni-liberali/export-csv',                                     [ErogazioneLiberaleController::class, 'exportCsv'])->name('erogazioni-liberali.csv');
    Route::get('bilancio/erogazioni-liberali/export-xml',                                     [ErogazioneLiberaleController::class, 'exportXml'])->name('erogazioni-liberali.xml');
    Route::post('bilancio/erogazioni-liberali/importa-incassi',                               [ErogazioneLiberaleController::class, 'importaDaIncassi'])->name('erogazioni-liberali.importa')->middleware('role:admin,contabile');
    Route::get('bilancio/erogazioni-liberali/{erogazioneLiberale}',                           [ErogazioneLiberaleController::class, 'show'])->name('erogazioni-liberali.show');
    Route::get('bilancio/erogazioni-liberali/{erogazioneLiberale}/edit',                      [ErogazioneLiberaleController::class, 'edit'])->name('erogazioni-liberali.edit')->middleware('role:admin,contabile');
    Route::put('bilancio/erogazioni-liberali/{erogazioneLiberale}',                           [ErogazioneLiberaleController::class, 'update'])->name('erogazioni-liberali.update')->middleware('role:admin,contabile');
    Route::delete('bilancio/erogazioni-liberali/{erogazioneLiberale}',                        [ErogazioneLiberaleController::class, 'destroy'])->name('erogazioni-liberali.destroy')->middleware('role:admin,contabile');

    // ── Bilancio CEE / Rendiconto Gestionale ETS (G5) ────────────────────
    Route::get('bilancio/cee',                    [BilancioController::class, 'index'])->name('bilancio.cee.index');
    Route::get('bilancio/cee/pdf-sp',             [BilancioController::class, 'exportPdfSp'])->name('bilancio.cee.pdf-sp');
    Route::get('bilancio/cee/pdf-ce',             [BilancioController::class, 'exportPdfCe'])->name('bilancio.cee.pdf-ce');
    Route::get('bilancio/cee/pdf-rendiconto',     [BilancioController::class, 'exportPdfRendiconto'])->name('bilancio.cee.pdf-rendiconto');
    Route::get('bilancio/cee/csv',                [BilancioController::class, 'exportCsv'])->name('bilancio.cee.csv');

    Route::get('scadenzario-quote', [ScadenzarioController::class, 'index'])->name('scadenzario.index');
    Route::get('scadenzario-quote/export', [ScadenzarioController::class, 'exportMorosi'])->name('scadenzario.export');
    Route::post('scadenzario-quote/sollecito-massivo', [ScadenzarioController::class, 'sendSollecitoMassivo'])->name('scadenzario.sollecito-massivo');
    Route::post('scadenzario-quote/{member}/sollecito', [ScadenzarioController::class, 'sendSollecito'])->name('scadenzario.sollecito');

    // ── Esercizio Contabile ───────────────────────────────────────────────
    Route::get('esercizi',                              [EsercizioContabileController::class, 'index'])->name('esercizi.index');
    Route::post('esercizi',                             [EsercizioContabileController::class, 'store'])->name('esercizi.store');
    Route::put('esercizi/{esercizio}',                  [EsercizioContabileController::class, 'update'])->name('esercizi.update');
    Route::post('esercizi/{esercizio}/close',           [EsercizioContabileController::class, 'close'])->name('esercizi.close');
    Route::post('esercizi/{esercizio}/reopen',          [EsercizioContabileController::class, 'reopen'])->name('esercizi.reopen');
    Route::delete('esercizi/{esercizio}',               [EsercizioContabileController::class, 'destroy'])->name('esercizi.destroy');

    // ── Ratei e Risconti ─────────────────────────────────────────────────
    Route::get('ratei-risconti',                                   [RateiRiscontiController::class, 'index'])->name('ratei-risconti.index');
    Route::post('ratei-risconti',                                  [RateiRiscontiController::class, 'store'])->name('ratei-risconti.store');
    Route::put('ratei-risconti/{rateoRisconto}',                   [RateiRiscontiController::class, 'update'])->name('ratei-risconti.update');
    Route::post('ratei-risconti/{rateoRisconto}/registra',         [RateiRiscontiController::class, 'registra'])->name('ratei-risconti.registra');
    Route::post('ratei-risconti/{rateoRisconto}/storna',           [RateiRiscontiController::class, 'storna'])->name('ratei-risconti.storna');
    Route::delete('ratei-risconti/{rateoRisconto}',                [RateiRiscontiController::class, 'destroy'])->name('ratei-risconti.destroy');
    Route::post('ratei-risconti-batch',                            [RateiRiscontiController::class, 'registraBatch'])->name('ratei-risconti.batch');

    // ── Scadenzario Completo ───────────────────────────────────────────────
    Route::get('scadenze',                    [ScadenzaController::class, 'index'])->name('scadenze.index');
    Route::get('scadenze/dashboard',          [ScadenzaController::class, 'dashboard'])->name('scadenze.dashboard');
    Route::get('scadenze/fornitori',          [ScadenzaController::class, 'fornitori'])->name('scadenze.fornitori');
    Route::get('scadenze/clienti',            [ScadenzaController::class, 'clienti'])->name('scadenze.clienti');
    Route::post('scadenze/riprendi',          [ScadenzaController::class, 'riprendi'])->name('scadenze.riprendi');
    Route::get('scadenze/create',             [ScadenzaController::class, 'create'])->name('scadenze.create');
    Route::post('scadenze',                   [ScadenzaController::class, 'store'])->name('scadenze.store');
    Route::get('scadenze/{scadenza}/edit',    [ScadenzaController::class, 'edit'])->name('scadenze.edit');
    Route::put('scadenze/{scadenza}',         [ScadenzaController::class, 'update'])->name('scadenze.update');
    Route::delete('scadenze/{scadenza}',      [ScadenzaController::class, 'destroy'])->name('scadenze.destroy');
    Route::post('scadenze/{scadenza}/pagata', [ScadenzaController::class, 'markPagata'])->name('scadenze.mark-pagata');
    Route::get('reports/rendiconto-cassa', [RendicontoCassaController::class, 'index'])->name('reports.rendiconto-cassa');
    Route::get('reports/rendiconto-cassa/export-pdf', [RendicontoCassaController::class, 'exportPdf'])->name('reports.rendiconto-cassa.export-pdf');
    Route::post('reports/rendiconto-cassa/export-pdf', [RendicontoCassaController::class, 'exportPdfFromPayload'])->name('reports.rendiconto-cassa.export-pdf.post');

    // Report cooperativa (solo cooperative)
    Route::middleware(['cooperative'])->group(function () {
        Route::get('reports/conto-economico-coop', [AccountingReportController::class, 'contoEconomicoCooperativa'])->name('reports.conto-economico-coop');
        Route::get('reports/conto-economico-coop/export', [AccountingReportController::class, 'exportContoEconomicoCooperativa'])->name('reports.conto-economico-coop.export');
        Route::get('reports/situazione-capitale', [AccountingReportController::class, 'situazioneCapitale'])->name('reports.situazione-capitale');
        Route::get('reports/situazione-capitale/export', [AccountingReportController::class, 'exportSituazioneCapitale'])->name('reports.situazione-capitale.export');
    });

    // Immobili e magazzino
    Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
    Route::post('properties/{property}/assets', [PropertyController::class, 'storeAsset'])->name('properties.assets.store');
    Route::delete('properties/{property}/assets/{asset}', [PropertyController::class, 'destroyAsset'])->name('properties.assets.destroy');
    Route::get('items', [ItemController::class, 'index'])->name('items.index');
    Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::get('locations', [LocationController::class, 'index'])->name('locations.index');
    Route::get('locations/create', [LocationController::class, 'create'])->name('locations.create');
    Route::post('locations', [LocationController::class, 'store'])->name('locations.store');
    Route::get('locations/{location}/edit', [LocationController::class, 'edit'])->name('locations.edit');
    Route::put('locations/{location}', [LocationController::class, 'update'])->name('locations.update');
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    Route::post('warehouses/{warehouse}/stocks', [WarehouseController::class, 'storeStock'])->name('warehouses.stocks.store');
    Route::put('warehouses/{warehouse}/stocks/{stock}', [WarehouseController::class, 'updateStock'])->name('warehouses.stocks.update');
    Route::delete('warehouses/{warehouse}/stocks/{stock}', [WarehouseController::class, 'destroyStock'])->name('warehouses.stocks.destroy');
    // ── Cespiti e Ammortamenti (D4) ──────────────────────────────────────────
    Route::prefix('cespiti')->name('cespiti.')->middleware('role:admin,contabile,segreteria')->group(function () {
        // Categorie fiscali
        Route::get('categorie', [AssetCategoryController::class, 'index'])->name('categorie.index');
        Route::post('categorie', [AssetCategoryController::class, 'store'])->name('categorie.store')->middleware('role:admin');
        Route::put('categorie/{categoria}', [AssetCategoryController::class, 'update'])->name('categorie.update')->middleware('role:admin');

        // Dashboard ammortamenti esercizio
        Route::get('ammortamento', [AmmortamentoController::class, 'index'])->name('ammortamento.index');
        Route::post('ammortamento/genera', [AmmortamentoController::class, 'genera'])->name('ammortamento.genera');
        Route::post('ammortamento/{schedule}/registra', [AmmortamentoController::class, 'registra'])->name('ammortamento.registra');
        Route::post('ammortamento/conferma-esercizio', [AmmortamentoController::class, 'confermaEsercizio'])->name('ammortamento.conferma-esercizio');

        // Dismissione (deve stare PRIMA di {asset} per evitare conflitti)
        Route::post('{asset}/dismetti', [DismissioneCespitiController::class, 'store'])->name('dismetti');
        Route::post('{asset}/preview-dismissione', [DismissioneCespitiController::class, 'preview'])->name('preview-dismissione');

        // PDF Registro Cespiti
        Route::get('registro-pdf', [CespitiController::class, 'registroPdf'])->name('registro-pdf');

        // CRUD cespiti
        Route::get('/', [CespitiController::class, 'index'])->name('index');
        Route::get('create', [CespitiController::class, 'create'])->name('create');
        Route::post('/', [CespitiController::class, 'store'])->name('store');
        Route::get('{asset}', [CespitiController::class, 'show'])->name('show');
        Route::get('{asset}/edit', [CespitiController::class, 'edit'])->name('edit');
        Route::put('{asset}', [CespitiController::class, 'update'])->name('update');
        Route::delete('{asset}', [CespitiController::class, 'destroy'])->name('destroy');
    });

    Route::get('documents/{document}/pdf', [DocumentController::class, 'downloadPdf'])->name('documents.pdf');
    Route::post('documents/{document}/attachments', [DocumentController::class, 'storeAttachment'])->name('documents.attachments.store');
    Route::delete('documents/{document}/attachments/{attachment}', [DocumentController::class, 'destroyAttachment'])->name('documents.attachments.destroy');
    Route::resource('documents', DocumentController::class);
    Route::resource('templates', TemplateController::class)->except(['show']);
    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('email-templates/{tipo}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('email-templates/{tipo}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('email-templates/{tipo}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');
    Route::post('email-templates/{tipo}/send-test', [EmailTemplateController::class, 'sendTest'])->name('email-templates.send-test');
    Route::get('receipt-templates', [ReceiptTemplateController::class, 'index'])->name('receipt-templates.index');
    Route::get('receipt-templates/{tipo}/edit', [ReceiptTemplateController::class, 'edit'])->name('receipt-templates.edit');
    Route::put('receipt-templates/{tipo}', [ReceiptTemplateController::class, 'update'])->name('receipt-templates.update');
    Route::get('verbali/prossimo-numero', [VerbaleController::class, 'prossimoNumero'])->name('verbali.prossimo-numero');
    Route::get('verbali/{verbale}/pdf', [VerbaleController::class, 'downloadPdf'])->name('verbali.pdf');
    Route::post('verbali/{verbale}/conferma', [VerbaleController::class, 'conferma'])->name('verbali.conferma');
    Route::post('verbali/{verbale}/attachments', [VerbaleController::class, 'storeAttachment'])->name('verbali.attachments.store');
    Route::delete('verbali/{verbale}/attachments/{attachment}', [VerbaleController::class, 'destroyAttachment'])->name('verbali.attachments.destroy');
    Route::resource('verbali', VerbaleController::class)->parameters(['verbali' => 'verbale']);

    // Eventi
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}/poster', [EventController::class, 'destroyPoster'])->name('events.poster.destroy');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('events/{event}/register', [EventController::class, 'register'])->name('events.register');
    Route::delete('events/{event}/registrations/{registration}', [EventController::class, 'unregister'])->name('events.registrations.destroy');

    // ── Centri di Costo (A5) ───────────────────────────────────────────────
    Route::get('centri-di-costo',                              [CentriDiCostoController::class, 'index'])->name('centri-di-costo.index');
    Route::get('centri-di-costo/create',                       [CentriDiCostoController::class, 'create'])->name('centri-di-costo.create')->middleware('role:admin,contabile');
    Route::post('centri-di-costo',                             [CentriDiCostoController::class, 'store'])->name('centri-di-costo.store')->middleware('role:admin,contabile');
    Route::get('centri-di-costo/{centroCosto}',                [CentriDiCostoController::class, 'show'])->name('centri-di-costo.show');
    Route::get('centri-di-costo/{centroCosto}/edit',           [CentriDiCostoController::class, 'edit'])->name('centri-di-costo.edit')->middleware('role:admin,contabile');
    Route::put('centri-di-costo/{centroCosto}',                [CentriDiCostoController::class, 'update'])->name('centri-di-costo.update')->middleware('role:admin,contabile');
    Route::delete('centri-di-costo/{centroCosto}',             [CentriDiCostoController::class, 'destroy'])->name('centri-di-costo.destroy')->middleware('role:admin');
    Route::post('centri-di-costo/{centroCosto}/toggle-attivo', [CentriDiCostoController::class, 'toggleAttivo'])->name('centri-di-costo.toggle-attivo')->middleware('role:admin,contabile');

    // ── Audit Trail (C2) ────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('admin/audit',                       [AuditController::class, 'index'])->name('audit.index');
        Route::get('admin/audit/export',                [AuditController::class, 'export'])->name('audit.export');
        Route::get('admin/audit/{auditLog}',            [AuditController::class, 'show'])->name('audit.show');
        Route::get('admin/audit/entity/{type}/{id}',    [AuditController::class, 'forEntity'])->name('audit.entity');
    });
});
