<?php

namespace Database\Seeders;

use App\Models\Conto;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder usato dall'installer web: ruoli, tipi socio, organi e cariche (da config),
 * conto Cassa contanti, impostazioni default e un solo utente admin con i dati passati da config.
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            MemberTypeSeeder::class,
            OrganiHardcodedSeeder::class,
            EmailTemplatesSeeder::class,
        ]);

        Settings::set('quota_annuale', 50);
        Settings::set('nome_associazione', 'La mia Associazione ETS');
        Settings::set('indirizzo_associazione', '');
        Settings::set('codice_fiscale_associazione', '');
        Settings::set('partita_iva_associazione', '');
        Settings::set('causale_default_donazione', 'Erogazione liberale');
        Settings::set('causale_default_quota', 'Quota associativa');
        Settings::set('causale_default_rimborso', 'Rimborso spese');

        Conto::firstOrCreate(
            ['code' => 'Cassa'],
            ['name' => 'Cassa contanti', 'type' => 'cassa', 'ordine' => 0, 'attivo' => true]
        );

        $admin = config('install.admin');
        if (empty($admin['email'])) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'] ?? 'Amministratore',
                'password' => bcrypt($admin['password']),
            ]
        );
        $user->password = bcrypt($admin['password']);
        $user->name = $admin['name'] ?? $user->name;
        $user->save();

        if (! $user->roles()->where('name', 'admin')->exists()) {
            $user->roles()->attach(Role::where('name', 'admin')->first());
        }
    }
}
