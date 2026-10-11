<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['user', 'search']);

        return Inertia::render('activity/index', [
            'logs' => ActivityLog::query()
                ->with('user:id,name')
                ->when($filters['user'] ?? null, fn (Builder $query, $user) => $query->where('user_id', $user))
                ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                    ->where('description', 'like', "%{$term}%")->orWhere('action', 'like', "%{$term}%")))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (ActivityLog $log): array => [
                    'id' => $log->id,
                    'user' => $log->user?->name ?? 'System',
                    'action' => $log->action,
                    'description' => $log->description,
                    'properties' => $log->properties,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }
}
