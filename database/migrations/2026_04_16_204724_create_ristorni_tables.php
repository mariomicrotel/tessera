<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ristorni', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 36);
            $table->unsignedSmallInteger('anno');
            $table->decimal('importo_totale_deliberato', 14, 2);
            $table->decimal('aliquota_ritenuta', 5, 4)->default(0.3000);
            $table->date('data_delibera_assemblea');
            $table->date('data_pagamento')->nullable();
            $table->unsignedBigInteger('verbale_id')->nullable();
            $table->enum('status', ['deliberato', 'in_pagamento', 'pagato', 'annullato'])->default('deliberato');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('verbale_id')->references('id')->on('verbali')->onDelete('set null');

            $table->index(['tenant_id', 'anno']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ristorno_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('ristorno_id');
            $table->unsignedBigInteger('member_id');
            $table->decimal('importo_lordo', 12, 2);
            $table->decimal('aliquota_ritenuta', 5, 4);
            $table->decimal('importo_ritenuta', 12, 2);
            $table->decimal('importo_netto', 12, 2);
            $table->date('data_pagamento')->nullable();
            $table->enum('status', ['deliberato', 'pagato'])->default('deliberato');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('ristorno_id')->references('id')->on('ristorni')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('restrict');

            $table->unique(['ristorno_id', 'member_id']);
            $table->index(['tenant_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ristorno_entries');
        Schema::dropIfExists('ristorni');
    }
};
