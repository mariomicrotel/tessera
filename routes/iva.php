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
    Route::get('/iva/fatture-passive/{fatturaPassiva}', [IvaController::class, 'fatturaPassivaShow'])->name('iva.fatture-passive.show');

    // Fatture attive
    Route::get('/iva/fatture-attive', [IvaController::class, 'fattureAttiveIndex'])->name('iva.fatture-attive.index');
    Route::get('/iva/fatture-attive/{fatturaAttiva}', [IvaController::class, 'fatturaAttivaShow'])->name('iva.fatture-attive.show');

    // Liquidazioni IVA
    Route::get('/iva/liquidazioni', [IvaController::class, 'liquidazioniIndex'])->name('iva.liquidazioni.index');
    Route::get('/iva/liquidazioni/{liquidazioneIva}', [IvaController::class, 'liquidazioneShow'])->name('iva.liquidazioni.show');
});
