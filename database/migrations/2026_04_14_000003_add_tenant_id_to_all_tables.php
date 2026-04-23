<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelle che ricevono tenant_id per il multi-tenancy.
     */
    private array $tables = [
        // Soci e utenza
        'members',
        'member_types',
        'member_invites',
        'subscriptions',

        // Finanza
        'incassi',
        'spese',
        'prima_nota_entries',
        'receipts',
        'expense_refunds',
        'refund_items',
        'conti',
        'receipt_templates',

        // Documenti
        'documents',
        'verbali',
        'templates',
        'email_templates',
        'attachments',

        // Eventi
        'events',
        'event_registrations',

        // Organi e governance
        'organi',
        'cariche_sociali',
        'incarichi',
        'elezioni',
        'candidature',
        'partecipazioni_voto',
        'voti',

        // Patrimonio
        'properties',
        'assets',
        'items',
        'warehouses',
        'warehouse_stocks',
        'locations',

        // Configurazione
        'settings',
    ];

    public function up(): void
    {
        // Crea un tenant "default" per i dati esistenti
        $defaultTenantId = \Illuminate\Support\Str::uuid()->toString();

        DB::table('tenants')->insert([
            'id' => $defaultTenantId,
            'name' => 'Tenant Default',
            'slug' => 'default',
            'plan' => 'enterprise',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Settings non ha colonna 'id', usa 'key' come primary key
            $afterColumn = Schema::hasColumn($table, 'id') ? 'id' : null;

            Schema::table($table, function (Blueprint $blueprint) use ($afterColumn) {
                $col = $blueprint->uuid('tenant_id')->nullable();
                if ($afterColumn) {
                    $col->after($afterColumn);
                }
            });

            // Assegna tutti i record esistenti al tenant default
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);

            // Rendi NOT NULL e aggiungi foreign key + index
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->uuid('tenant_id')->nullable(false)->change();
                $blueprint->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
                $blueprint->index('tenant_id', $table.'_tenant_id_index');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign([$table.'_tenant_id_foreign']);
                $blueprint->dropIndex($table.'_tenant_id_index');
                $blueprint->dropColumn('tenant_id');
            });
        }

        DB::table('tenants')->where('slug', 'default')->delete();
    }
};
