<?php

namespace App\Http\Middleware;

use App\Enums\AdjustmentStatus;
use App\Enums\ClaimStatus;
use App\Enums\ReturnStatus;
use App\Models\CustomerReturn;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\SupplierExchangeClaim;
use App\Services\DemoDataService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames() ?? [],
                'permissions' => fn () => $user ? ($user->hasRole('Super Admin') ? ['*'] : $user->getAllPermissions()->pluck('name')) : [],
            ],
            'notifications' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'latest' => $user->notifications()->limit(6)->get()->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
            ] : null,
            'badges' => fn () => $user ? [
                'returns' => $user->can('movements.view') ? CustomerReturn::query()->where('status', '!=', ReturnStatus::Inspected)->count() : 0,
                'claims' => $user->can('claims.view') ? SupplierExchangeClaim::query()->whereIn('status', ClaimStatus::awaitingApproval())->count() : 0,
                'adjustments' => $user->can('movements.view') ? StockAdjustment::query()->where('status', AdjustmentStatus::Pending)->count() : 0,
            ] : null,
            'businessTimezone' => config('inventory.business_timezone'),
            'demoMode' => fn () => $user ? (bool) (Setting::get(DemoDataService::FLAG)['loaded'] ?? false) : false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
