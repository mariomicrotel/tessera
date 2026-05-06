<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ets_statuto_clausole', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('statuto_id')->constrained('ets_statuti')->cascadeOnDelete();
            $table->string('numero_articolo', 10);
            $table->string('titolo')->nullable();
            $table->text('testo');
            $table->string('articolo_cts', 80)->nullable()->comment('Riferimento CTS, es. art.5 D.Lgs.117/2017');
            $table->boolean('compliance_ok')->nullable()->comment('null=non verificato, true=ok, false=ko');
            $table->text('note_compliance')->nullable();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();

            $table->index(['statuto_id', 'ordine']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ets_statuto_clausole');
    }
};
