<?php

use App\Models\MemberInvite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('member-invites:cleanup', function () {
    $deleted = MemberInvite::cleanupExpired();
    $this->info("Eliminati {$deleted} inviti scaduti.");
})->purpose('Elimina gli inviti a domanda di ammissione scaduti (opzionale: la pulizia avviene anche in automatico durante l\'uso dell\'app)');

// Pulizia giornaliera dei bundle export consulente scaduti (TTL 30gg).
// Esegue alle 03:00 quando il carico è minimo.
Schedule::command('consultant:purge-expired-exports')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Sincronizzazione caselle email ogni 15 minuti.
Schedule::call(function () {
    \App\Models\MailAccount::where('is_active', true)
        ->each(fn($account) => \App\Jobs\SyncMailboxJob::dispatch($account->id));
})->everyFifteenMinutes()
  ->name('mail:sync-all')
  ->withoutOverlapping();
