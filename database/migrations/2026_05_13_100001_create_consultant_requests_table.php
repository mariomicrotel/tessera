<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richieste documenti/informazioni del consulente verso il cliente (tenant).
 *
 * Il consulente apre una richiesta (es. "Invia bilancio 2024") che il
 * referente del tenant può soddisfare caricando documenti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_requests', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->unsignedBigInteger('consultant_user_id')->index();
            $table->string('titolo', 255);
            $table->text('descrizione')->nullable();
            $table->enum('priorita', ['bassa', 'normale', 'alta', 'urgente'])->default('normale');
            $table->enum('stato', [
                'aperta',
                'in_attesa_risposta',
                'risposta_ricevuta',
                'chiusa',
                'annullata',
            ])->default('aperta')->index();
            $table->date('data_scadenza')->nullable();
            $table->timestamp('chiusa_il')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stato'], 'cr_tenant_stato_idx');
            $table->index(['consultant_user_id', 'stato'], 'cr_consultant_stato_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_requests');
    }
};
