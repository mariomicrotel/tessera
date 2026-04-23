<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spese: uscite di cassa con opzione registrazione in prima nota e voce rendiconto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spese', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();
            $table->foreignId('conto_id')->constrained('conti')->cascadeOnDelete();
            $table->boolean('genera_prima_nota')->default(true);
            $table->string('rendiconto_code', 50)->nullable();
            $table->string('gestione', 20)->nullable();
            $table->boolean('competenza_cassa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spese');
    }
};
