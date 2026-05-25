<?php

namespace App\Observers;

use App\Models\MovimentoBancario;
use App\Services\Consultant\ConsultantStatsService;

/**
 * Invalida la cache del cruscotto consulente quando un movimento bancario cambia.
 */
class MovimentoBancarioObserver
{
    public function __construct(private ConsultantStatsService $stats) {}

    public function created(MovimentoBancario $model): void   { $this->invalidate($model); }
    public function updated(MovimentoBancario $model): void   { $this->invalidate($model); }
    public function deleted(MovimentoBancario $model): void   { $this->invalidate($model); }
    public function restored(MovimentoBancario $model): void  { $this->invalidate($model); }
    public function forceDeleted(MovimentoBancario $model): void { $this->invalidate($model); }

    private function invalidate(MovimentoBancario $model): void
    {
        if ($model->tenant_id) {
            $this->stats->invalidate((string) $model->tenant_id);
        }
    }
}
