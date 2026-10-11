<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        return ActivityLog::query()->create([
            'user_id' => ($user ?? Auth::user())?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => Request::ip(),
        ]);
    }
}
