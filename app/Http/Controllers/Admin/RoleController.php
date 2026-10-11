<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(): Response
    {
        return Inertia::render('admin/roles', [
            'roles' => Role::query()->with('permissions:id,name')->withCount('users')->orderBy('id')->get()->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'is_super_admin' => $role->name === RoleEnum::SuperAdmin->value,
                'permissions' => $role->permissions->pluck('name'),
            ]),
            'permissions' => collect(PermissionEnum::cases())->map(fn (PermissionEnum $permission): array => [
                'name' => $permission->value,
                'label' => $permission->label(),
                'group' => $permission->group(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate(['name' => ['required', 'string', 'max:64', 'unique:roles,name']]);
        Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $this->toast("রোল {$data['name']} তৈরি হয়েছে।");

        return back();
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        if ($role->name === RoleEnum::SuperAdmin->value) {
            throw ValidationException::withMessages(['role' => 'সুপার অ্যাডমিনের সব অনুমতি সবসময় থাকে।']);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionEnum::values())],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        $this->activity->log('role.permissions_updated', "রোল {$role->name} এর অনুমতি বদলানো হয়েছে", properties: ['permissions' => $data['permissions'] ?? []]);
        $this->toast("{$role->name} রোলের অনুমতি সেভ হয়েছে।");

        return back();
    }

    /**
     * Changing what a role may do is reserved for Super Admins, so no one can widen their own access.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole(RoleEnum::SuperAdmin->value), 403, 'শুধু সুপার অ্যাডমিন রোল বদলাতে পারেন।');
    }
}
