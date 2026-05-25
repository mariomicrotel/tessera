<?php

namespace App\Observers;

use App\Models\Incasso;
use App\Services\Consultant\ConsultantStatsService;

/**
 * Invalida la cache del cruscotto consulente quando un incasso cambia.
 */
class IncassoObserver
{
    public function __construct(private ConsultantStatsService $stats) {}

    public function created(Incasso $model): void   { $this->invalidate($model); }
    public function updated(Incasso $model): void   { $this->invalidate($model); }
    public function deleted(Incasso $model): void   { $this->invalidate($model); }
    public function restored(Incasso $model): void  { $this->invalidate($model); }
    public function forceDeleted(Incasso $model): void { $this->invalidate($model); }

    private function invalidate(Incasso $model): void
    {
        if ($model->tenant_id) {
            $this->stats->invalidate((string) $model->tenant_id);
        }
    }
}
