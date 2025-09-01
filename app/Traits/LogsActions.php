<?php

namespace App\Traits;

use App\Models\ActionLog;
use Illuminate\Support\Facades\Auth;

trait LogsActions
{
    public static function bootLogsActions()
    {
        static::created(function ($model) {
            $model->logAction('create', null, $model->toArray());
        });

        static::updated(function ($model) {
            $model->logAction('update', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->logAction('delete', $model->toArray(), null);
        });
    }

    protected function logAction($type, $before, $after)
    {
        ActionLog::create([
            'action_type' => $type,
            'model_type'  => get_class($this),
            'model_id'    => $this->id,
            'before_data' => $before ? json_encode($before) : null,
            'after_data'  => $after ? json_encode($after) : null,
            'user_id'     => Auth::guard('admin')->user()->id ?? '8',
        ]);
    }
}
