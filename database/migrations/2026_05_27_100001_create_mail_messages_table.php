<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('mail_account_id')->index();

            $table->unsignedBigInteger('uid');          // IMAP UID (univoco per cartella+account)
            $table->string('message_id', 500)->nullable(); // RFC Message-ID header
            $table->string('folder', 100)->default('INBOX');

            $table->string('subject', 500)->nullable();
            $table->string('from_name', 300)->nullable();
            $table->string('from_email', 300)->nullable();
            $table->json('to_addresses');               // array [{name, email}]
            $table->json('cc_addresses')->nullable();

            $table->datetime('sent_at');

            $table->longText('body_html')->nullable();
            $table->text('body_text')->nullable();

            $table->boolean('is_read')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('has_attachments')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Un UID deve essere unico per account + cartella
            $table->unique(['mail_account_id', 'folder', 'uid'], 'mail_messages_account_folder_uid_unique');
            $table->index(['tenant_id', 'is_read', 'sent_at'], 'mail_messages_tenant_read_sent_idx');
            $table->index(['tenant_id', 'sent_at'], 'mail_messages_tenant_sent_idx');

            $table->foreign('mail_account_id')
                  ->references('id')
                  ->on('mail_accounts')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
