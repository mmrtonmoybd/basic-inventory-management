<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class Notifier
{
    /**
     * Sends a notification to every active user holding the given permission (directly or via a role).
     */
    public function notifyPermission(string $permission, Notification $notification, ?User $except = null): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($permission): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', Role::SuperAdmin->value))
                    ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', $permission))
                    ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', $permission));
            })
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->get();

        if ($recipients->isNotEmpty()) {
            NotificationFacade::send($recipients, $notification);
        }
    }
}
