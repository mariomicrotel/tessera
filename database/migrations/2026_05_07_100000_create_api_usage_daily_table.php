<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('provider', 50);
            $table->string('endpoint', 100);
            $table->string('method', 10)->default('GET');
            $table->date('usage_date');
            $table->unsignedInteger('calls_count')->default(0);
            $table->unsignedInteger('limit_count')->default(100);
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'endpoint', 'usage_date'], 'api_usage_daily_unique');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_daily');
    }
};
