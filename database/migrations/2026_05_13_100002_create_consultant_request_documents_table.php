<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documenti allegati a una richiesta consulente.
 *
 * Caricati dal referente del tenant (o dal consulente stesso come esempio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_request_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultant_request_id')->index();
            $table->char('tenant_id', 36)->index();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->string('filename_originale', 255);  // nome file come caricato
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->unsignedBigInteger('size')->nullable();  // byte
            $table->string('mime_type', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['consultant_request_id', 'tenant_id'], 'crd_req_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_request_documents');
    }
};
