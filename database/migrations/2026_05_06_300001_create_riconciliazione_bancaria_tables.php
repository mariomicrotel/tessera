<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Estratti conto (upload header)
        Schema::create('estratti_conto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nome_file');
            $table->string('banca')->nullable();
            $table->string('iban', 34)->nullable();
            $table->date('periodo_dal');
            $table->date('periodo_al');
            $table->decimal('saldo_iniziale', 14, 2)->nullable();
            $table->decimal('saldo_finale',   14, 2)->nullable();
            $table->enum('formato', ['csv', 'mt940', 'ofx'])->default('csv');
            $table->timestamps();
            $table->index(['tenant_id', 'periodo_dal']);
        });

        // Movimenti bancari (righe estratto conto)
        Schema::create('movimenti_bancari', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('estratto_conto_id')->constrained('estratti_conto')->cascadeOnDelete();
            $table->date('data_valuta');
            $table->date('data_contabile')->nullable();
            $table->string('descrizione');
            $table->decimal('importo', 14, 2);
            $table->string('tipo', 10)->default('dare'); // dare | avere
            $table->string('riferimento')->nullable();
            $table->boolean('riconciliato')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'data_valuta']);
            $table->index(['tenant_id', 'riconciliato']);
        });

        // Abbinamenti riconciliazione (voce bancaria ↔ prima nota / fattura)
        Schema::create('riconciliazione_voci', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('movimento_bancario_id')->constrained('movimenti_bancari')->cascadeOnDelete();

            // Entità abbinata (polimorfica semplice)
            $table->string('entita_tipo')->nullable(); // 'prima_nota_entry' | 'fattura_passiva' | 'fattura_attiva' | 'incasso'
            $table->string('entita_id',  36)->nullable();

            $table->text('nota')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'movimento_bancario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riconciliazione_voci');
        Schema::dropIfExists('movimenti_bancari');
        Schema::dropIfExists('estratti_conto');
    }
};
