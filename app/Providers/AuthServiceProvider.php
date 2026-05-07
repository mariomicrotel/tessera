<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Member::class             => \App\Policies\MemberPolicy::class,
        \App\Models\FatturaAttiva::class      => \App\Policies\FatturaAttivaPolicy::class,
        \App\Models\FatturaPassiva::class     => \App\Policies\FatturaPassivaPolicy::class,
        \App\Models\Incasso::class            => \App\Policies\IncassoPolicy::class,
        \App\Models\CooperativeShare::class   => \App\Policies\CooperativeSharePolicy::class,
        \App\Models\MovimentoContabile::class => \App\Policies\MovimentoContabilePolicy::class,
        \App\Models\Asset::class              => \App\Policies\CespitiPolicy::class,
        \App\Models\Tessera::class            => \App\Policies\TesseraPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Admin bypass: può fare tutto
        Gate::before(function ($user, $ability) {
            if ($user && $user->isAdmin()) {
                return true;
            }
        });
    }
}
