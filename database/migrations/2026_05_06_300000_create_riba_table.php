<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('riba', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('fattura_attiva_id')->nullable()->constrained('fatture_attive')->nullOnDelete();

            $table->string('numero_riba')->nullable();
            $table->string('nome_debitore');
            $table->string('cf_piva_debitore', 20)->nullable();
            $table->string('iban_debitore', 34)->nullable();
            $table->decimal('importo', 12, 2);
            $table->date('data_scadenza');
            $table->date('data_emissione');
            $table->date('data_invio_banca')->nullable();

            $table->enum('stato', ['da_inviare', 'inviata', 'accettata', 'pagata', 'insoluta', 'annullata'])
                  ->default('da_inviare');

            $table->string('codice_sia', 5)->nullable()->comment('Codice SIA mittente (5 caratteri alfanumerici)');
            $table->string('banca_presentatrice')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stato']);
            $table->index(['tenant_id', 'data_scadenza']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riba');
    }
};
