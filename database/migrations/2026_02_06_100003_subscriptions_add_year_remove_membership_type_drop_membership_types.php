<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'year')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unsignedSmallInteger('year')->nullable()->after('member_id');
            });
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("UPDATE subscriptions SET year = CAST(strftime('%Y', ends_at) AS INTEGER) WHERE year IS NULL AND ends_at IS NOT NULL");
        } else {
            DB::statement('UPDATE subscriptions SET year = YEAR(ends_at) WHERE year IS NULL AND ends_at IS NOT NULL');
        }
        DB::table('subscriptions')->whereNull('year')->update(['year' => (int) date('Y')]);

        if (Schema::hasColumn('subscriptions', 'year')) {
            $driver = DB::getDriverName();
            $indexName = 'subscriptions_member_id_year_unique';
            if ($driver === 'sqlite') {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS ' . $indexName . ' ON subscriptions (member_id, year)');
            } elseif (! $this->indexExists('subscriptions', $indexName)) {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->unique(['member_id', 'year'], 'subscriptions_member_id_year_unique');
                });
            }
        }

        if (Schema::hasColumn('subscriptions', 'membership_type_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign(['membership_type_id']);
                $table->dropIndex(['member_id', 'membership_type_id', 'ends_at']);
                $table->dropColumn('membership_type_id');
            });
        }

        Schema::dropIfExists('membership_types');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $result = DB::selectOne(
                "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
                [$table, $indexName]
            );
            return $result !== null;
        }
        return false;
    }

    public function down(): void
    {
        Schema::create('membership_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('period', 20)->default('annuale');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'year']);
            $table->foreignId('membership_type_id')->nullable()->after('member_id')->constrained()->cascadeOnDelete();
        });

        $defaultTypeId = DB::table('membership_types')->insertGetId([
            'name' => 'Quota annuale',
            'amount' => 0,
            'period' => 'annuale',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subscriptions')->update(['membership_type_id' => $defaultTypeId]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('membership_type_id')->nullable(false)->change();
            $table->index(['member_id', 'membership_type_id', 'ends_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }
};
