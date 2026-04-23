<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('incassi', 'conto_id')) {
            Schema::table('incassi', function (Blueprint $table) {
                $table->foreignId('conto_id')->after('paid_at')->nullable()->constrained('conti')->cascadeOnDelete();
            });

            $firstContoId = DB::table('conti')->orderBy('ordine')->value('id');
            if ($firstContoId !== null) {
                DB::table('incassi')->whereNull('conto_id')->update(['conto_id' => $firstContoId]);
            }

            $driver = DB::getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE incassi MODIFY conto_id BIGINT UNSIGNED NOT NULL');
            }
        }

        // Rimuovi payment_method_id: la tabella era "payments" prima del rename, la FK conserva il nome originario
        if (Schema::hasColumn('incassi', 'payment_method_id')) {
            $fkName = 'payments_payment_method_id_foreign';
            if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
                $fkExists = DB::selectOne("
                    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                    WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'incassi' AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ", [DB::getDatabaseName(), $fkName]);
                if ($fkExists) {
                    Schema::table('incassi', function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                    });
                }
            } else {
                Schema::table('incassi', function (Blueprint $table) {
                    $table->dropForeign(['payment_method_id']);
                });
            }
            Schema::table('incassi', function (Blueprint $table) {
                $table->dropColumn('payment_method_id');
            });
        }

        Schema::dropIfExists('payment_methods');
    }

    public function down(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('incassi', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete()->after('paid_at');
        });

        Schema::table('incassi', function (Blueprint $table) {
            $table->dropForeign(['conto_id']);
            $table->dropColumn('conto_id');
        });
    }
};
