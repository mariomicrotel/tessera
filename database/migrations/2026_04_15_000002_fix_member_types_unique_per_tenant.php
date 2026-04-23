<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corregge il vincolo unique su member_types per supportare il multi-tenant.
 *
 * Il vincolo originale era solo su `name` (globale), impedendo a tenant diversi
 * di avere tipi con lo stesso nome (es. 'socio'). In un sistema multi-tenant
 * il vincolo deve essere su (name, tenant_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_types', function (Blueprint $table) {
            // Rimuove il vecchio unique su `name` solo
            $table->dropUnique(['name']);

            // Aggiunge unique su (name, tenant_id): nomi unici per tenant
            $table->unique(['name', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('member_types', function (Blueprint $table) {
            $table->dropUnique(['name', 'tenant_id']);
            $table->unique(['name']);
        });
    }
};
