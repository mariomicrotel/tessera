<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelle per le "consegne" del consulente: documenti che il commercialista
 * carica per l'ente (es. F24 da pagare, bilancio firmato, comunicazioni).
 *
 * Pattern simmetrico a ConsultantRequest:
 *  - ConsultantRequest    = consulente CHIEDE all'ente (ente carica risposta)
 *  - ConsultantDelivery   = consulente CONSEGNA all'ente (consulente carica file)
 *
 * Tabella `consultant_request_documents` viene estesa con `uploaded_as_response`
 * per distinguere upload del consulente (richiesta) da upload dell'ente (risposta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultant_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('consultant_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('titolo');
            $table->text('descrizione')->nullable();

            // Categoria: utile per filtri e badge nella UI
            $table->enum('tipo', [
                'documento_fiscale',  // CU, 770, dichiarazioni, ricevute pagamento
                'f24',                // F24 compilato pronto al versamento
                'bilancio',           // bilancio firmato, relazione di missione
                'comunicazione',      // nota generica, parere, comunicazione
                'altro',
            ])->default('comunicazione');

            // Lifecycle: consulente carica → consegnato; ente apre → letto;
            // ente segnala feedback → accettato/contestato
            $table->enum('stato', [
                'bozza',        // consulente sta preparando, non ancora visibile all'ente
                'consegnato',   // visibile all'ente
                'letto',        // l'ente ha aperto il dettaglio
                'accettato',    // l'ente conferma di averlo ricevuto/eseguito
                'contestato',   // l'ente segnala un problema
            ])->default('bozza');

            $table->timestamp('data_consegna')->nullable();      // quando passa da bozza→consegnato
            $table->timestamp('data_lettura')->nullable();       // prima apertura ente
            $table->timestamp('data_feedback')->nullable();      // accettato o contestato

            $table->text('feedback_note')->nullable();           // note dell'ente (su accetta/contesta)

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'stato'], 'cd_tenant_stato_idx');
            $table->index(['consultant_user_id', 'stato'], 'cd_consultant_stato_idx');
            $table->index('data_consegna', 'cd_data_consegna_idx');
        });

        Schema::create('consultant_delivery_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('delivery_id');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('filename_originale');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('delivery_id')->references('id')->on('consultant_deliveries')->cascadeOnDelete();
            $table->index('delivery_id', 'cdd_delivery_idx');
        });

        // Estensione `consultant_request_documents` per distinguere
        // documenti caricati come "risposta dell'ente" alla richiesta consulente.
        Schema::table('consultant_request_documents', function (Blueprint $table) {
            $table->boolean('uploaded_as_response')->default(false)->after('note');
            $table->index(['consultant_request_id', 'uploaded_as_response'], 'crd_req_resp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('consultant_request_documents', function (Blueprint $table) {
            $table->dropIndex('crd_req_resp_idx');
            $table->dropColumn('uploaded_as_response');
        });

        Schema::dropIfExists('consultant_delivery_documents');
        Schema::dropIfExists('consultant_deliveries');
    }
};
