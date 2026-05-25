<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indici compositi per ConsultantStatsService.
 *
 * Ogni query di aggregazione filtra sempre per (tenant_id, date_column).
 * Questi indici portano le GROUP BY su milioni di righe sotto i 100ms.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Fatture attive ────────────────────────────────────────────────
        if (Schema::hasTable('fatture_attive')) {
            Schema::table('fatture_attive', function (Blueprint $table) {
                if (! $this->hasIndex('fatture_attive', 'fa_tenant_data_idx')) {
                    $table->index(['tenant_id', 'data_fattura'], 'fa_tenant_data_idx');
                }
                if (! $this->hasIndex('fatture_attive', 'fa_tenant_stato_data_idx')) {
                    $table->index(['tenant_id', 'stato', 'data_fattura'], 'fa_tenant_stato_data_idx');
                }
                if (! $this->hasIndex('fatture_attive', 'fa_tenant_pagamento_idx')) {
                    $table->index(['tenant_id', 'stato_pagamento'], 'fa_tenant_pagamento_idx');
                }
            });
        }

        // ── Fatture passive ───────────────────────────────────────────────
        if (Schema::hasTable('fatture_passive')) {
            Schema::table('fatture_passive', function (Blueprint $table) {
                if (! $this->hasIndex('fatture_passive', 'fp_tenant_data_idx')) {
                    $table->index(['tenant_id', 'data_fattura'], 'fp_tenant_data_idx');
                }
                if (! $this->hasIndex('fatture_passive', 'fp_tenant_pagamento_idx')) {
                    $table->index(['tenant_id', 'stato_pagamento'], 'fp_tenant_pagamento_idx');
                }
                if (! $this->hasIndex('fatture_passive', 'fp_tenant_scadenza_idx')) {
                    $table->index(['tenant_id', 'data_scadenza'], 'fp_tenant_scadenza_idx');
                }
            });
        }

        // ── Incassi ───────────────────────────────────────────────────────
        if (Schema::hasTable('incassi')) {
            Schema::table('incassi', function (Blueprint $table) {
                if (! $this->hasIndex('incassi', 'inc_tenant_paid_idx')) {
                    $table->index(['tenant_id', 'paid_at'], 'inc_tenant_paid_idx');
                }
                if (! $this->hasIndex('incassi', 'inc_tenant_type_paid_idx')) {
                    $table->index(['tenant_id', 'type', 'paid_at'], 'inc_tenant_type_paid_idx');
                }
            });
        }

        // ── Spese ─────────────────────────────────────────────────────────
        if (Schema::hasTable('spese')) {
            Schema::table('spese', function (Blueprint $table) {
                if (! $this->hasIndex('spese', 'sp_tenant_date_idx')) {
                    $table->index(['tenant_id', 'date'], 'sp_tenant_date_idx');
                }
                if (! $this->hasIndex('spese', 'sp_tenant_rendiconto_idx')) {
                    $table->index(['tenant_id', 'rendiconto_code'], 'sp_tenant_rendiconto_idx');
                }
            });
        }

        // ── Rimborsi spese ────────────────────────────────────────────────
        if (Schema::hasTable('expense_refunds')) {
            Schema::table('expense_refunds', function (Blueprint $table) {
                if (! $this->hasIndex('expense_refunds', 'er_tenant_date_status_idx')) {
                    $table->index(['tenant_id', 'refund_date', 'status'], 'er_tenant_date_status_idx');
                }
            });
        }

        // ── Movimenti bancari ─────────────────────────────────────────────
        if (Schema::hasTable('movimenti_bancari')) {
            Schema::table('movimenti_bancari', function (Blueprint $table) {
                if (! $this->hasIndex('movimenti_bancari', 'mb_tenant_valuta_idx')) {
                    $table->index(['tenant_id', 'data_valuta'], 'mb_tenant_valuta_idx');
                }
                if (! $this->hasIndex('movimenti_bancari', 'mb_tenant_riconciliato_idx')) {
                    $table->index(['tenant_id', 'riconciliato'], 'mb_tenant_riconciliato_idx');
                }
            });
        }

        // ── Quote cooperative ─────────────────────────────────────────────
        if (Schema::hasTable('cooperative_shares')) {
            Schema::table('cooperative_shares', function (Blueprint $table) {
                if (! $this->hasIndex('cooperative_shares', 'cs_tenant_status_idx')) {
                    $table->index(['tenant_id', 'status', 'data_versamento'], 'cs_tenant_status_idx');
                }
            });
        }

        // ── Movimenti prestito sociale ────────────────────────────────────
        if (Schema::hasTable('prestito_sociale_movimenti')) {
            Schema::table('prestito_sociale_movimenti', function (Blueprint $table) {
                if (! $this->hasIndex('prestito_sociale_movimenti', 'psm_tenant_valuta_tipo_idx')) {
                    $table->index(['tenant_id', 'data_valuta', 'tipo'], 'psm_tenant_valuta_tipo_idx');
                }
            });
        }
    }

    public function down(): void
    {
        $indexes = [
            'fatture_attive'             => ['fa_tenant_data_idx', 'fa_tenant_stato_data_idx', 'fa_tenant_pagamento_idx'],
            'fatture_passive'            => ['fp_tenant_data_idx', 'fp_tenant_pagamento_idx', 'fp_tenant_scadenza_idx'],
            'incassi'                    => ['inc_tenant_paid_idx', 'inc_tenant_type_paid_idx'],
            'spese'                      => ['sp_tenant_date_idx', 'sp_tenant_rendiconto_idx'],
            'expense_refunds'            => ['er_tenant_date_status_idx'],
            'movimenti_bancari'          => ['mb_tenant_valuta_idx', 'mb_tenant_riconciliato_idx'],
            'cooperative_shares'         => ['cs_tenant_status_idx'],
            'prestito_sociale_movimenti' => ['psm_tenant_valuta_tipo_idx'],
        ];

        foreach ($indexes as $table => $idxList) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($idxList) {
                    foreach ($idxList as $idx) {
                        try { $t->dropIndex($idx); } catch (\Throwable) {}
                    }
                });
            }
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return ! empty($indexes);
        } catch (\Throwable) {
            return false;
        }
    }
};
