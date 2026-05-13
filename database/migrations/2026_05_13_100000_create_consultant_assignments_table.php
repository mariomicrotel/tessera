<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella di assegnazione consulenti a tenant.
 *
 * Cross-tenant: non usa la colonna tenant_id come scope principale —
 * ogni riga dice "l'utente X è consulente del tenant Y".
 * Il consultant_user_id è l'utente con ruolo 'consultant'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultant_user_id')->index(); // references users.id
            $table->char('tenant_id', 36)->index();                    // references tenants.id (UUID)
            $table->unsignedBigInteger('assigned_by_user_id')->nullable(); // chi ha assegnato
            $table->enum('ruolo', ['primario', 'secondario'])->default('primario');
            $table->boolean('active')->default(true)->index();
            $table->date('started_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['consultant_user_id', 'tenant_id'], 'ca_unique_assignment');
            $table->index(['tenant_id', 'active'], 'ca_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_assignments');
    }
};
