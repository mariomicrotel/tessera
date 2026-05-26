<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Indici su mail_messages ───────────────────────────────────────────
        Schema::table('mail_messages', function (Blueprint $table) {
            // Per il badge non-letti (shared prop su ogni request)
            // Nota: mail_messages_tenant_sent_idx già presente nella migration originale
            $table->index(['tenant_id', 'is_read'], 'mail_messages_tenant_read_idx');
        });

        // ── Campi SMTP su mail_accounts ───────────────────────────────────────
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->string('smtp_host')->nullable()->after('imap_folder');
            $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_encryption', 20)->nullable()->after('smtp_port'); // ssl|tls|starttls|none
            $table->string('smtp_username')->nullable()->after('smtp_encryption');
            $table->text('smtp_password')->nullable()->after('smtp_username'); // cifrata
            $table->string('smtp_from_name', 150)->nullable()->after('smtp_password');
            $table->string('smtp_from_email', 255)->nullable()->after('smtp_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropIndex('mail_messages_tenant_read_idx');
        });

        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'smtp_host', 'smtp_port', 'smtp_encryption',
                'smtp_username', 'smtp_password',
                'smtp_from_name', 'smtp_from_email',
            ]);
        });
    }
};
