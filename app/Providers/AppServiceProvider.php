<?php

namespace App\Providers;

use App\Models\FatturaAttiva;
use App\Models\FatturaPassiva;
use App\Models\Incasso;
use App\Models\MovimentoBancario;
use App\Models\Settings;
use App\Models\Spesa;
use App\Models\Tenant;
use App\Observers\FatturaAttivaObserver;
use App\Observers\FatturaPassivaObserver;
use App\Observers\IncassoObserver;
use App\Observers\MovimentoBancarioObserver;
use App\Observers\SpesaObserver;
use App\Observers\TenantObserver;
use App\Services\Consultant\ConsultantStatsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Chiave temporanea usata quando APP_KEY è vuoto, così Laravel può avviarsi
     * e mostrare l'installer. Il middleware EnsureNotInstalled considera ancora
     * l'app "non installata" se la chiave è questa.
     */
    public const INSTALL_PLACEHOLDER_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (empty(Config::get('app.key'))) {
            Config::set('app.key', self::INSTALL_PLACEHOLDER_KEY);
        }

        // Singleton stateless: nessuna dipendenza dal tenant corrente
        $this->app->singleton(ConsultantStatsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Config::get('app.key') === self::INSTALL_PLACEHOLDER_KEY || empty(Config::get('app.key'))) {
            Config::set('session.driver', 'file');
            $nameFromExample = $this->appNameFromEnvExample();
            if ($nameFromExample !== null) {
                Config::set('app.name', $nameFromExample);
            }
        } else {
            // In modalità SaaS, il nome mail viene impostato dal middleware tenant
            // Non leggere Settings durante il boot globale: il tenant non è ancora risolto
            // Il nome verrà impostato dal ResolveTenant middleware per ogni richiesta
        }

        // Observer: precarica codici IVA di sistema alla creazione di un tenant cooperativa
        Tenant::observe(TenantObserver::class);

        // Observer: invalida cache cruscotto consulente su ogni modifica ai dati economici
        FatturaAttiva::observe(FatturaAttivaObserver::class);
        FatturaPassiva::observe(FatturaPassivaObserver::class);
        Incasso::observe(IncassoObserver::class);
        Spesa::observe(SpesaObserver::class);
        MovimentoBancario::observe(MovimentoBancarioObserver::class);
    }

    /**
     * Legge APP_NAME da .env.example così l'installer mostra il nome corretto (es. "Infotel Sistemi") invece di "Laravel".
     */
    private function appNameFromEnvExample(): ?string
    {
        $path = base_path('.env.example');
        if (! is_file($path)) {
            return null;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }
        if (preg_match('/^\s*APP_NAME\s*=\s*(.+)$/m', $content, $m)) {
            $value = trim($m[1], " \t\"'");
            return $value !== '' ? $value : null;
        }

        return null;
    }
}
