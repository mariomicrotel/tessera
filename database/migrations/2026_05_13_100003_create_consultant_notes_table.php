<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Note del consulente per ente cliente.
 *
 * Visibilità:
 *   - 'interna': solo il consulente la vede (memo privato)
 *   - 'condivisa': visibile anche agli utenti del tenant (admin/responsabile)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_notes', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->unsignedBigInteger('consultant_user_id')->index();
            $table->text('testo');
            $table->enum('visibilita', ['interna', 'condivisa'])->default('interna');
            $table->boolean('fissata')->default(false);  // nota in evidenza
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'consultant_user_id'], 'cn_tenant_consultant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_notes');
    }
};
