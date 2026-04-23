<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = app('current_tenant');
        if (!$tenant) {
            $this->command->warn('⚠️ Nessun tenant trovato. Saltando EmailTemplatesSeeder.');
            return;
        }

        $types = config('email_templates.types', []);
        foreach ($types as $tipo => $config) {
            EmailTemplate::firstOrCreate(
                ['tenant_id' => $tenant->id, 'tipo' => $tipo],
                [
                    'subject' => $config['default_subject'] ?? '[{{appName}}]',
                    'body_html' => $config['default_body'] ?? '',
                ]
            );
        }
    }
}
