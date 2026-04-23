<?php

namespace App\Console\Commands;

use App\Models\Conto;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\MemberTypeSeeder;
use Database\Seeders\OrganiHardcodedSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedTenant extends Command
{
    protected $signature = 'tenant:seed
        {--tenant= : Slug del tenant (default: "default")}
        {--name= : Nome utente admin}
        {--email= : Email utente admin}
        {--password= : Password utente admin}';

    protected $description = 'Esegue il seed di un tenant con dati iniziali e crea un utente admin.';

    public function handle(): int
    {
        $slug = $this->option('tenant') ?? 'default';
        $tenant = Tenant::where('slug', $slug)->first();

        if (! $tenant) {
            $this->error("Tenant '{$slug}' non trovato.");
            return 1;
        }

        // Imposta il tenant corrente
        app()->instance('current_tenant', $tenant);

        $this->info("Inizializzazione tenant: {$tenant->name} ({$tenant->slug})");

        // Seed dei ruoli (globali, non dipendono dal tenant)
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        // Seed dei dati tenant-scoped
        $this->call('db:seed', ['--class' => MemberTypeSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => OrganiHardcodedSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => EmailTemplatesSeeder::class, '--force' => true]);

        // Settings
        Settings::set('quota_annuale', 50);
        Settings::set('nome_associazione', $tenant->name);
        Settings::set('indirizzo_associazione', '');
        Settings::set('codice_fiscale_associazione', '');
        Settings::set('partita_iva_associazione', '');
        Settings::set('causale_default_donazione', 'Erogazione liberale');
        Settings::set('causale_default_quota', 'Quota associativa');
        Settings::set('causale_default_rimborso', 'Rimborso spese');

        // Conto cassa
        Conto::firstOrCreate(
            ['code' => 'Cassa', 'tenant_id' => $tenant->id],
            ['name' => 'Cassa contanti', 'type' => 'cassa', 'ordine' => 0, 'attivo' => true]
        );

        // Utente admin
        $email = $this->option('email') ?? 'admin@infotelsistemi.it';
        $name = $this->option('name') ?? 'Amministratore';
        $password = $this->option('password') ?? 'password';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_super_admin' => true,
            ]
        );

        // Associa al tenant come admin
        if (! $tenant->users()->where('user_id', $user->id)->exists()) {
            $tenant->users()->attach($user->id, ['role' => 'admin']);
        }

        // Associa il ruolo admin (per compatibilità)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole && ! $user->roles()->where('role_id', $adminRole->id)->exists()) {
            $user->roles()->attach($adminRole);
        }

        $this->info("Utente admin creato: {$email} / {$password}");
        $this->info("Tenant seed completato.");

        return 0;
    }
}
