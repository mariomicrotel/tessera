<?php

namespace App\Observers;

use App\Models\FatturaAttiva;
use App\Services\Consultant\ConsultantStatsService;

/**
 * Invalida la cache del cruscotto consulente quando una fattura attiva cambia.
 */
class FatturaAttivaObserver
{
    public function __construct(private ConsultantStatsService $stats) {}

    public function created(FatturaAttiva $model): void   { $this->invalidate($model); }
    public function updated(FatturaAttiva $model): void   { $this->invalidate($model); }
    public function deleted(FatturaAttiva $model): void   { $this->invalidate($model); }
    public function restored(FatturaAttiva $model): void  { $this->invalidate($model); }
    public function forceDeleted(FatturaAttiva $model): void { $this->invalidate($model); }

    private function invalidate(FatturaAttiva $model): void
    {
        if ($model->tenant_id) {
            $this->stats->invalidate((string) $model->tenant_id);
        }
    }
}
