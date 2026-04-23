<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rimborsi spese: beneficiario, data, stato (bozza/stampata/contabilizzata), totale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('refund_date');
            $table->string('status', 20)->default('bozza'); // bozza, stampata, contabilizzata
            $table->decimal('total', 12, 2)->default(0);
            $table->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_refunds');
    }
};
