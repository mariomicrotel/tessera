<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Crea i ruoli predefiniti: admin, contabile, segreteria, socio.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'display_name' => 'Amministratore'],
            ['name' => 'contabile', 'display_name' => 'Contabile'],
            ['name' => 'segreteria', 'display_name' => 'Segreteria'],
            ['name' => 'socio', 'display_name' => 'Socio'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['display_name' => $role['display_name']]
            );
        }
    }
}
