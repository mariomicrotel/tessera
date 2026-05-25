<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella che traccia i bundle di export richiesti dai consulenti.
 *
 * Cross-tenant: NO BelongsToTenant trait sul modello, poiché il consulente
 * può chiedere export di tenant diversi. tenant_id è solo riferimento.
 *
 * Lifecycle: pending → processing → (ready | failed | cancelled) → expired
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_export_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ── Ownership cross-tenant ────────────────────────────────────
            $table->uuid('tenant_id');
            $table->foreignId('consultant_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // ── Periodo richiesto ─────────────────────────────────────────
            $table->date('period_from');
            $table->date('period_to');

            // ── Selezione formati e tabelle ───────────────────────────────
            // formats: array di chiavi formato (es. ['csv_generic','agenzia_entrate_xml'])
            // data_types: array di chiavi DataSource (es. ['fatture_attive','incassi','spese'])
            $table->json('formats');
            $table->json('data_types');

            // ── Stato e file ──────────────────────────────────────────────
            $table->enum('status', [
                'pending',     // creato, in coda
                'processing',  // job in esecuzione
                'ready',       // bundle scaricabile
                'failed',      // errore in generazione
                'cancelled',   // annullato dal consulente
                'expired',     // file rimosso dopo TTL
            ])->default('pending');

            $table->string('file_path', 500)->nullable();    // storage path
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->text('error_message')->nullable();

            // ── Timing ────────────────────────────────────────────────────
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');  // now() + 30gg
            $table->timestamps();

            // ── Indici ────────────────────────────────────────────────────
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['consultant_user_id', 'status'], 'ceb_consultant_status_idx');
            $table->index(['tenant_id', 'period_from', 'period_to'], 'ceb_tenant_period_idx');
            $table->index('expires_at', 'ceb_expires_idx'); // per purge job
            $table->index('status', 'ceb_status_idx');      // per liste in-flight
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_export_bundles');
    }
};
