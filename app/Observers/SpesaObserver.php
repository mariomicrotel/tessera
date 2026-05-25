<?php

namespace App\Observers;

use App\Models\Spesa;
use App\Services\Consultant\ConsultantStatsService;

/**
 * Invalida la cache del cruscotto consulente quando una spesa cambia.
 */
class SpesaObserver
{
    public function __construct(private ConsultantStatsService $stats) {}

    public function created(Spesa $model): void   { $this->invalidate($model); }
    public function updated(Spesa $model): void   { $this->invalidate($model); }
    public function deleted(Spesa $model): void   { $this->invalidate($model); }
    public function restored(Spesa $model): void  { $this->invalidate($model); }
    public function forceDeleted(Spesa $model): void { $this->invalidate($model); }

    private function invalidate(Spesa $model): void
    {
        if ($model->tenant_id) {
            $this->stats->invalidate((string) $model->tenant_id);
        }
    }
}
