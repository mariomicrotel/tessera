<?php

use App\Models\MemberInvite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('member-invites:cleanup', function () {
    $deleted = MemberInvite::cleanupExpired();
    $this->info("Eliminati {$deleted} inviti scaduti.");
})->purpose('Elimina gli inviti a domanda di ammissione scaduti (opzionale: la pulizia avviene anche in automatico durante l\'uso dell\'app)');
