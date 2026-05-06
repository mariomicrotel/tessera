<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ets_atti_costitutivi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('notaio')->nullable();
            $table->string('repertorio', 50)->nullable();
            $table->date('data_atto')->nullable();
            $table->date('data_registrazione_ae')->nullable()->comment('Data registrazione Agenzia Entrate');
            $table->string('ufficio_registro', 100)->nullable();
            $table->string('numero_registro', 50)->nullable();
            $table->enum('stato', ['bozza', 'registrato'])->default('bozza');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ets_atti_costitutivi');
    }
};
