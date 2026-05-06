<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ets_statuti', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('versione', 20);
            $table->string('titolo')->default('Statuto');
            $table->enum('stato', ['bozza', 'approvato', 'archiviato'])->default('bozza');
            $table->date('data_approvazione')->nullable();
            $table->date('data_deposito')->nullable()->comment('Data deposito al notaio o registro');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'stato']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ets_statuti');
    }
};
