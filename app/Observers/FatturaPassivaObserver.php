<?php

namespace App\Observers;

use App\Models\FatturaPassiva;
use App\Services\Consultant\ConsultantStatsService;

/**
 * Invalida la cache del cruscotto consulente quando una fattura passiva cambia.
 */
class FatturaPassivaObserver
{
    public function __construct(private ConsultantStatsService $stats) {}

    public function created(FatturaPassiva $model): void   { $this->invalidate($model); }
    public function updated(FatturaPassiva $model): void   { $this->invalidate($model); }
    public function deleted(FatturaPassiva $model): void   { $this->invalidate($model); }
    public function restored(FatturaPassiva $model): void  { $this->invalidate($model); }
    public function forceDeleted(FatturaPassiva $model): void { $this->invalidate($model); }

    private function invalidate(FatturaPassiva $model): void
    {
        if ($model->tenant_id) {
            $this->stats->invalidate((string) $model->tenant_id);
        }
    }
}
