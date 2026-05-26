<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();

            $table->string('name', 100);               // Nome visualizzato es. "Casella principale"
            $table->string('email', 255);

            // IMAP
            $table->string('imap_host', 255);
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 10)->default('ssl'); // ssl | tls | starttls | none
            $table->string('imap_username', 255);
            $table->text('imap_password');              // cifrata con Crypt
            $table->string('imap_folder', 100)->default('INBOX');

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sync_days')->default(30); // giorni indietro al primo sync
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
