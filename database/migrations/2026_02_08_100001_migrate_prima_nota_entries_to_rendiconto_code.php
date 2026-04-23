<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Popola rendiconto_code in base a ledger_account_id (mappa codici vecchi → MOD_D).
 */
return new class extends Migration
{
    /** Mappa ledger_accounts.code → rendiconto_code MOD_D */
    protected const CODE_MAP = [
        '2.1' => 'INC_A_1',   // Quote associative
        '2.2' => 'INC_A_4',   // Donazioni / Erogazioni liberali
        '3.1' => 'EXP_A_5',   // Rimborsi spese
    ];

    protected const DEFAULT_INCOME = 'INC_A_10';  // Altre entrate

    protected const DEFAULT_EXPENSE = 'EXP_A_5';  // Uscite diverse di gestione

    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasColumn('prima_nota_entries', 'ledger_account_id')) {
            return;
        }

        $accounts = DB::table('ledger_accounts')->pluck('code', 'id')->all();

        foreach (DB::table('prima_nota_entries')->get() as $row) {
            $newCode = self::resolveCode(
                $row->ledger_account_id,
                (float) $row->amount,
                $accounts
            );
            DB::table('prima_nota_entries')
                ->where('id', $row->id)
                ->update(['rendiconto_code' => $newCode]);
        }

        DB::table('prima_nota_entries')->whereNull('rendiconto_code')->update([
            'rendiconto_code' => self::DEFAULT_INCOME,
        ]);
    }

    public function down(): void
    {
        // Non ripristinabile senza ledger_account_id
    }

    protected static function resolveCode(?int $ledgerAccountId, float $amount, array $idToCode): string
    {
        if ($ledgerAccountId !== null && isset($idToCode[$ledgerAccountId])) {
            $oldCode = $idToCode[$ledgerAccountId];
            if (isset(self::CODE_MAP[$oldCode])) {
                return self::CODE_MAP[$oldCode];
            }
        }

        return $amount >= 0 ? self::DEFAULT_INCOME : self::DEFAULT_EXPENSE;
    }
};
