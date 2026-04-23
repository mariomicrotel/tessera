<?php

use App\Http\Controllers\IvaController;
use Illuminate\Support\Facades\Route;

/**
 * Rotte per il modulo IVA.
 *
 * Accessibili solo a utenti autenticati su un tenant.
 * Le policy BelongsToTenant vengono applicate automaticamente ai modelli.
 */
Route::middleware(['web', 'auth', 'tenant'])->group(function () {
    // Dashboard IVA
    Route::get('/iva', [IvaController::class, 'dashboard'])->name('iva.dashboard');

    // Codici IVA
    Route::get('/iva/codici', [IvaController::class, 'codiciIndex'])->name('iva.codici.index');
    Route::get('/iva/codici/{codiceIva}', [IvaController::class, 'codiceShow'])->name('iva.codici.show');

    // Fatture passive
    Route::get('/iva/fatture-passive', [IvaController::class, 'fatturePassiveIndex'])->name('iva.fatture-passive.index');
    Route::get('/iva/fatture-passive/create', [IvaController::class, 'fatturePassiveCreate'])->name('iva.fatture-passive.create');
    Route::post('/iva/fatture-passive', [IvaController::class, 'fatturePassiveStore'])->name('iva.fatture-passive.store');
    Route::get('/iva/fatture-passive/{fatturaPassiva}', [IvaController::class, 'fatturaPassivaShow'])->name('iva.fatture-passive.show');
    Route::get('/iva/fatture-passive/{fatturaPassiva}/edit', [IvaController::class, 'fatturePassiveEdit'])->name('iva.fatture-passive.edit');
    Route::put('/iva/fatture-passive/{fatturaPassiva}', [IvaController::class, 'fatturePassiveUpdate'])->name('iva.fatture-passive.update');
    Route::delete('/iva/fatture-passive/{fatturaPassiva}', [IvaController::class, 'fatturePassiveDestroy'])->name('iva.fatture-passive.destroy');
    Route::post('/iva/fatture-passive/{fatturaPassiva}/marca-pagata', [IvaController::class, 'fatturePassiveMarkPaid'])->name('iva.fatture-passive.mark-paid');
    Route::post('/iva/fatture-passive/{fatturaPassiva}/marca-registrata', [IvaController::class, 'fatturePassiveMarkRegistered'])->name('iva.fatture-passive.mark-registered');
    Route::post('/iva/fatture-passive/{fatturaPassiva}/annulla', [IvaController::class, 'fatturePassiveCancelTTL'])->name('iva.fatture-passive.cancel');

    // Fatture attive
    Route::get('/iva/fatture-attive', [IvaController::class, 'fattureAttiveIndex'])->name('iva.fatture-attive.index');
    Route::get('/iva/fatture-attive/{fatturaAttiva}', [IvaController::class, 'fatturaAttivaShow'])->name('iva.fatture-attive.show');

    // Liquidazioni IVA
    Route::get('/iva/liquidazioni', [IvaController::class, 'liquidazioniIndex'])->name('iva.liquidazioni.index');
    Route::get('/iva/liquidazioni/{liquidazioneIva}', [IvaController::class, 'liquidazioneShow'])->name('iva.liquidazioni.show');
});
