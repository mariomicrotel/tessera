<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corregge i vincoli unique globali per supportare correttamente il multi-tenant.
 *
 * Problema: diversi vincoli unique erano definiti solo sulla colonna chiave (es. `slug`,
 * `tipo`, `numero`) senza includervi `tenant_id`. In un sistema multi-tenant ciò
 * impedisce a tenant diversi di avere record con gli stessi valori.
 *
 * Tabelle interessate:
 *  - organi.slug               → unique(slug, tenant_id)
 *  - email_templates.tipo      → unique(tipo, tenant_id)
 *  - receipt_templates.tipo    → unique(tipo, tenant_id)
 *  - receipts.number           → unique(number, tenant_id)
 *  - members.numero_tessera    → unique(numero_tessera, tenant_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        // organi: slug unico per tenant
        Schema::table('organi', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['slug', 'tenant_id']);
        });

        // email_templates: tipo unico per tenant
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['tipo']);
            $table->unique(['tipo', 'tenant_id']);
        });

        // receipt_templates: tipo unico per tenant
        Schema::table('receipt_templates', function (Blueprint $table) {
            $table->dropUnique(['tipo']);
            $table->unique(['tipo', 'tenant_id']);
        });

        // receipts: numero ricevuta unico per tenant (non globale)
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->unique(['number', 'tenant_id']);
        });

        // members: numero tessera unico per tenant
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['numero_tessera']);
            $table->unique(['numero_tessera', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['numero_tessera', 'tenant_id']);
            $table->unique(['numero_tessera']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['number', 'tenant_id']);
            $table->unique(['number']);
        });

        Schema::table('receipt_templates', function (Blueprint $table) {
            $table->dropUnique(['tipo', 'tenant_id']);
            $table->unique(['tipo']);
        });

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropUnique(['tipo', 'tenant_id']);
            $table->unique(['tipo']);
        });

        Schema::table('organi', function (Blueprint $table) {
            $table->dropUnique(['slug', 'tenant_id']);
            $table->unique(['slug']);
        });
    }
};
