<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait per registrazione automatica audit trail.
 *
 * Aggiungilo a qualsiasi Eloquent model per tracciare:
 *  - created  → registra new_values (solo fillable)
 *  - updated  → registra old_values + new_values (solo campi modificati)
 *  - deleted  → registra old_values (snapshot pre-cancellazione)
 *  - restored → registra new_values (dopo SoftDelete::restore)
 *
 * Campi esclusi dal diff (credenziali, timestamp interni):
 *   password, remember_token, created_at, updated_at, deleted_at
 */
trait AuditsChanges
{
    // Campi da NON includere nel diff
    protected array $auditExclude = [
        'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
    ];

    public static function bootAuditsChanges(): void
    {
        // CREATED
        static::created(function (Model $model) {
            AuditLog::record(
                action:    AuditLog::ACTION_CREATED,
                entity:    $model,
                newValues: $model->auditableAttributes(),
            );
        });

        // UPDATED
        static::updated(function (Model $model) {
            $dirty = $model->getDirty();

            // Filtra campi esclusi
            $excluded = $model->auditExclude ?? [];
            foreach ($excluded as $field) {
                unset($dirty[$field]);
            }

            if (empty($dirty)) {
                return; // nessun campo rilevante modificato
            }

            $oldValues = [];
            $newValues = [];
            foreach ($dirty as $key => $_) {
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $model->getAttribute($key);
            }

            AuditLog::record(
                action:    AuditLog::ACTION_UPDATED,
                entity:    $model,
                oldValues: $oldValues,
                newValues: $newValues,
            );
        });

        // DELETED
        static::deleted(function (Model $model) {
            AuditLog::record(
                action:    AuditLog::ACTION_DELETED,
                entity:    $model,
                oldValues: $model->auditableAttributes(),
            );
        });

        // RESTORED (solo per modelli con SoftDeletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                AuditLog::record(
                    action:    AuditLog::ACTION_RESTORED,
                    entity:    $model,
                    newValues: $model->auditableAttributes(),
                );
            });
        }
    }

    /**
     * Attributi "auditabili": tutti i fillable meno i campi esclusi.
     */
    protected function auditableAttributes(): array
    {
        $excluded = $this->auditExclude ?? [];

        return collect($this->toArray())
            ->except(array_merge($excluded, ['tenant_id']))
            ->toArray();
    }

    /**
     * Recupera lo storico audit per questa istanza.
     */
    public function auditLogs()
    {
        return AuditLog::forEntity(static::class, $this->getKey())
            ->latest()
            ->get();
    }
}
