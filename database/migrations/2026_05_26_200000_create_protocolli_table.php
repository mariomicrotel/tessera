<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('protocolli', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedSmallInteger('anno');
            $table->unsignedMediumInteger('numero');
            $table->string('tipo', 20);                // entrata | uscita
            $table->date('data_registrazione');
            $table->string('oggetto', 500);
            $table->string('mittente', 300)->nullable();
            $table->string('destinatario', 300)->nullable();
            $table->text('note')->nullable();
            $table->nullableMorphs('linked');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'anno', 'numero'], 'protocolli_tenant_anno_numero_unique');
            $table->index(['tenant_id', 'tipo', 'data_registrazione'], 'protocolli_tenant_tipo_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocolli');
    }
};
