<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('recipient_name', 255)->nullable()->after('member_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropForeign(['member_id']);
            });
            DB::statement('ALTER TABLE receipts MODIFY member_id BIGINT UNSIGNED NULL');
            Schema::table('receipts', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            });
        } elseif ($driver === 'sqlite') {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropUnique(['number']);
            });
            Schema::rename('receipts', 'receipts_old');
            Schema::create('receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
                $table->string('recipient_name', 255)->nullable();
                $table->string('receivable_type');
                $table->unsignedBigInteger('receivable_id');
                $table->string('number')->unique();
                $table->date('issued_at');
                $table->string('file_path', 512)->nullable();
                $table->string('type', 20)->default('liberale');
                $table->timestamps();
            });
            DB::statement('INSERT INTO receipts (id, member_id, recipient_name, receivable_type, receivable_id, number, issued_at, file_path, type, created_at, updated_at) SELECT id, member_id, recipient_name, receivable_type, receivable_id, number, issued_at, file_path, type, created_at, updated_at FROM receipts_old');
            Schema::drop('receipts_old');
        }
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('recipient_name');
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropForeign(['member_id']);
            });
            DB::statement('ALTER TABLE receipts MODIFY member_id BIGINT UNSIGNED NOT NULL');
            Schema::table('receipts', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            });
        }
    }
};
