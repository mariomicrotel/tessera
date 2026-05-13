<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        // Crea tenant PRIMA di altri seeders multi-tenant
        if (in_array(app()->environment(), ['local', 'testing'])) {
            $this->call([
                CooperativaSeeder::class,
            ]);
        }

        $this->call([
            MemberTypeSeeder::class,
            EmailTemplatesSeeder::class,
            EtsComplianceRulesSeeder::class,
            TenantTestDataSeeder::class,
            ConsultantAndAdministrativeMovementsSeeder::class,
        ]);

        Settings::set('quota_annuale', 50);
        Settings::set('nome_associazione', 'La mia Associazione ETS');
        Settings::set('indirizzo_associazione', '');
        Settings::set('codice_fiscale_associazione', '');
        Settings::set('partita_iva_associazione', '');
        Settings::set('causale_default_donazione', 'Erogazione liberale');
        Settings::set('causale_default_quota', 'Quota associativa');
        Settings::set('causale_default_rimborso', 'Rimborso spese');

        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );
        if (! $user->roles()->where('name', 'admin')->exists()) {
            $user->roles()->attach(Role::where('name', 'admin')->first());
        }
    }
}
