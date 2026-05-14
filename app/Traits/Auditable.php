<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Automatically called by Laravel when the model boots.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->auditEvent('created');
        });

        static::updated(function (Model $model) {
            $model->auditEvent('updated');
        });

        static::deleted(function (Model $model) {
            $model->auditEvent('deleted');
        });
    }

    /**
     * Save an audit log record for the current model event.
     */
    protected function auditEvent(string $event): void
    {
        $oldValues = [];
        $newValues = [];

        $excluded = array_merge(
            ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );

        if ($event === 'created') {
            $newValues = array_diff_key($this->getAttributes(), array_flip($excluded));
        } elseif ($event === 'deleted') {
            $oldValues = array_diff_key($this->getOriginal(), array_flip($excluded));
        } elseif ($event === 'updated') {
            $changes = $this->getChanges();
            
            foreach ($changes as $key => $newValue) {
                if (in_array($key, $excluded)) {
                    continue;
                }
                
                $oldValues[$key] = $this->getOriginal($key);
                $newValues[$key] = $newValue;
            }
            
            // If no tracked fields actually changed (e.g. only timestamp was touched), skip logging
            if (empty($newValues) && empty($oldValues)) {
                return;
            }
        }

        // Build Context-Rich Log Entry
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => request()->fullUrl(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Relates this model to its audit trail logs.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
