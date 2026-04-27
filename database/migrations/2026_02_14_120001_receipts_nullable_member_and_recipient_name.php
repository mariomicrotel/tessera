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
            // SQLite: FK constraints are not enforced unless PRAGMA foreign_keys=ON (off in tests).
            // recipient_name was already added by the Schema::table block above.
            // We only need to make member_id nullable; rebuild the table without FK triggers
            // so we don't create stale trigger references on other tables.
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE receipts_rebuild AS SELECT * FROM receipts');
            DB::statement('DROP TABLE receipts');
            DB::statement('CREATE TABLE receipts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id INTEGER NULL,
                recipient_name VARCHAR(255) NULL,
                receivable_type VARCHAR(255) NOT NULL,
                receivable_id INTEGER NOT NULL,
                number VARCHAR(255) NOT NULL,
                issued_at DATE NOT NULL,
                file_path VARCHAR(512) NULL,
                type VARCHAR(20) NOT NULL DEFAULT \'liberale\',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL
            )');
            DB::statement('CREATE UNIQUE INDEX receipts_number_unique ON receipts (number)');
            DB::statement('INSERT INTO receipts SELECT id, member_id, recipient_name, receivable_type, receivable_id, number, issued_at, file_path, type, created_at, updated_at FROM receipts_rebuild');
            DB::statement('DROP TABLE receipts_rebuild');
            DB::statement('PRAGMA foreign_keys = ON');
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
