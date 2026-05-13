<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorie libere per movimenti amministrativi semplificati.
 *
 * Ogni tenant può creare le proprie categorie (es. "Quote associative",
 * "Affitti", "Utenze") senza dover configurare un piano dei conti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrative_movement_categories', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36)->index();
            $table->string('nome', 100);
            $table->enum('tipo', ['entrata', 'uscita', 'qualsiasi'])->default('qualsiasi')->index();
            $table->string('colore', 7)->nullable();   // es. #3B82F6
            $table->string('icona', 10)->nullable();   // emoji o codice icona
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->boolean('attiva')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'tipo', 'attiva'], 'amc_tenant_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrative_movement_categories');
    }
};
