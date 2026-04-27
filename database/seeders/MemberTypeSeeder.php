<?php

namespace Database\Seeders;

use App\Models\MemberType;
use Illuminate\Database\Seeder;

class MemberTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->bound('current_tenant')) {
            return;
        }

        $tenant = app('current_tenant');
        if (! $tenant) {
            return;
        }

        $types = [
            ['name' => 'socio', 'display_name' => 'Socio'],
            ['name' => 'volontario', 'display_name' => 'Volontario'],
            ['name' => 'collaboratore', 'display_name' => 'Collaboratore'],
        ];
        foreach ($types as $t) {
            MemberType::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $t['name']],
                ['display_name' => $t['display_name']]
            );
        }
    }
}
