<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabella cooperative_shares per gestire le quote di capitale
 * sociale sottoscritte dai soci delle cooperative.
 *
 * Una quota rappresenta una frazione del capitale sociale. Ogni socio
 * può possedere più quote (numero_quote). Il versamento può essere
 * parziale o completo, e il socio può riscattare le quote in qualsiasi momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooperative_shares', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('member_id');

            // Dati quote
            $table->unsignedInteger('numero_quote')->default(1);
            $table->decimal('valore_unitario', 10, 2);
            $table->decimal('totale_sottoscritto', 12, 2);
            $table->decimal('totale_versato', 12, 2)->default(0);

            // Date importanti
            $table->date('data_sottoscrizione');
            $table->date('data_versamento')->nullable();
            $table->date('data_riscatto')->nullable();

            // Motivo riscatto / note
            $table->string('motivo_riscatto')->nullable();
            $table->enum('status', [
                'sottoscritta',
                'parzialmente_versata',
                'versata',
                'riscattata',
                'annullata',
            ])->default('sottoscritta');
            $table->text('note')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');

            // Indici
            $table->unique(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooperative_shares');
    }
};
