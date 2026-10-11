<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()->with('roles:id,name')->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles->pluck('name'),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'roles' => Role::query()->orderBy('id')->pluck('name'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->guardSuperAdminRole($request->user(), $data['roles'] ?? []);

        $user = User::query()->create([...Arr::only($data, ['name', 'email', 'password']), 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles($data['roles'] ?? []);

        $this->activity->log('user.created', "নতুন ব্যবহারকারী {$user->email} যোগ হয়েছে", $user, ['roles' => $data['roles'] ?? []]);
        $this->toast("ব্যবহারকারী {$user->name} যোগ হয়েছে।");

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $actor = $request->user();

        abort_if($user->hasRole(RoleEnum::SuperAdmin->value) && ! $actor->hasRole(RoleEnum::SuperAdmin->value), 403, 'শুধু সুপার অ্যাডমিন অন্য সুপার অ্যাডমিনের অ্যাকাউন্ট বদলাতে পারেন।');

        $this->guardSuperAdminRole($actor, $data['roles'] ?? [], $user);

        $removesOwnSuperAdmin = $user->hasRole(RoleEnum::SuperAdmin->value) && ! in_array(RoleEnum::SuperAdmin->value, $data['roles'] ?? [], true);

        if ($user->is($request->user()) && (! ($data['is_active'] ?? true) || $removesOwnSuperAdmin)) {
            throw ValidationException::withMessages(['roles' => 'নিজের অ্যাকাউন্ট বন্ধ করা বা নিজের সুপার অ্যাডমিন রোল সরানো যাবে না।']);
        }

        $user->update(collect($data)->except('roles')->all());
        $user->syncRoles($data['roles'] ?? []);

        $this->activity->log('user.updated', "ব্যবহারকারী {$user->email} এর তথ্য বদলানো হয়েছে", $user, ['roles' => $data['roles'] ?? [], 'is_active' => $user->is_active]);
        $this->toast('ব্যবহারকারীর তথ্য সেভ হয়েছে।');

        return back();
    }

    /**
     * Prevents privilege escalation: only a Super Admin may grant or revoke the Super Admin role.
     *
     * @param  list<string>  $roles
     */
    private function guardSuperAdminRole(User $actor, array $roles, ?User $target = null): void
    {
        if ($actor->hasRole(RoleEnum::SuperAdmin->value)) {
            return;
        }

        $grants = in_array(RoleEnum::SuperAdmin->value, $roles, true);
        $revokes = $target?->hasRole(RoleEnum::SuperAdmin->value) && ! $grants;

        if ($grants || $revokes) {
            throw ValidationException::withMessages(['roles' => 'শুধু সুপার অ্যাডমিন সুপার অ্যাডমিন রোল দিতে বা সরাতে পারেন।']);
        }
    }
}
