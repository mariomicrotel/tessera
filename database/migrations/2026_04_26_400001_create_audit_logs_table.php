<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella di audit trail: registra ogni operazione su entità rilevanti.
 * Conserva old_values / new_values in formato JSON per diff completo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // null = sistema/CLI
            $table->string('user_email')->nullable();      // snapshot email (non FK)
            $table->string('entity_type', 100)->index();  // es. App\Models\FatturaAttiva
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('action', 30)->index();         // created | updated | deleted | restored
            $table->json('old_values')->nullable();        // valori prima della modifica
            $table->json('new_values')->nullable();        // valori dopo la modifica
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'entity_type', 'entity_id'], 'al_entity_idx');
            $table->index(['tenant_id', 'user_id', 'created_at'],   'al_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
