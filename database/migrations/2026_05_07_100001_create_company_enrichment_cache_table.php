<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_enrichment_cache', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('lookup_key', 50)->comment('P.IVA, CF o ID');
            $table->string('endpoint', 50);
            $table->json('response_json');
            $table->json('normalized_json')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at');
            $table->string('source_provider', 50)->default('openapi');
            $table->string('response_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'lookup_key', 'endpoint'], 'enrichment_cache_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_enrichment_cache');
    }
};
