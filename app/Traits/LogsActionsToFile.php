<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

trait LogsActionsToFile
{
    public static function bootLogsActionsToFile()
    {
        static::created(function ($model) {
            $model->logToFile('created', null, $model->toArray());
        });

        static::updated(function ($model) {
            $model->logToFile('updated', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logToFile('deleted', $model->toArray(), null);
        });
    }

    protected function logToFile($action, $before = null, $after = null)
    {
        $user = Auth::guard('student')->user() ?: Auth::user();
        if (!$user) {
            $user = Auth::guard('admin')->user();
        }
        $logMessage = [
            'timestamp' => now()->toDateTimeString(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'action' => $action,
            'model' => get_class($this),
            'model_id' => $this->id,
            'before' => $before,
            'after' => $after,
        ];

        Log::channel('actions')->info(json_encode($logMessage, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
