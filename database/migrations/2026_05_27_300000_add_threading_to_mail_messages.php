<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->string('in_reply_to', 500)->nullable()->after('message_id');
            // thread_id = message-id radice della conversazione (per raggruppare)
            $table->string('thread_id', 500)->nullable()->after('in_reply_to');
            $table->index(['tenant_id', 'thread_id'], 'mail_messages_tenant_thread_idx');
        });

        // Backfill: ogni messaggio esistente diventa radice del proprio thread
        DB::statement("UPDATE mail_messages SET thread_id = message_id WHERE thread_id IS NULL AND message_id IS NOT NULL");
        // Messaggi senza message_id: chiave sintetica basata sull'id (portabile sqlite/mysql)
        $concat = DB::getDriverName() === 'sqlite' ? "'local-' || id" : "CONCAT('local-', id)";
        DB::statement("UPDATE mail_messages SET thread_id = {$concat} WHERE thread_id IS NULL");
    }

    public function down(): void
    {
        Schema::table('mail_messages', function (Blueprint $table) {
            $table->dropIndex('mail_messages_tenant_thread_idx');
            $table->dropColumn(['in_reply_to', 'thread_id']);
        });
    }
};
