<?php

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\DamageCaseImage;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\WorkflowNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('does not offer public self-registration', function () {
    get('/register')->assertNotFound();
});

it('refuses to log in a deactivated user', function () {
    $user = User::factory()->create(['is_active' => false]);

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out a user who is deactivated mid-session', function () {
    $user = userWithRole(Role::StockOperator);
    $user->update(['is_active' => false]);

    actingAs($user)->get(route('profile.edit'))->assertRedirect(route('login'));
});

it('hides stock figures and search results from a user without a role', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 10]);
    $user = User::factory()->create();

    actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('restricted', true)->where('metrics', null));

    actingAs($user)->getJson(route('search', ['q' => $product->code]))->assertExactJson([]);
});

it('stops a super admin approving their own adjustment', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 10]);
    $admin = userWithRole(Role::SuperAdmin);

    actingAs($admin)->post(route('adjustments.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'count_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Count',
        'idempotency_key' => 'own',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'condition' => 'available', 'counted_quantity' => 3]],
    ]);

    actingAs($admin)->post(route('adjustments.approve', StockAdjustment::query()->sole()))->assertForbidden();

    expect(balanceOf(variantNamed($product, 'L'))->available)->toBe(10);
});

it('stops the person who raised a claim from giving it final approval', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 10]);
    $admin = userWithRole(Role::SuperAdmin);

    actingAs($admin)->post(route('damage.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'damage_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Torn',
        'supplier_id' => Supplier::factory()->create()->id,
        'request_exchange' => true,
        'idempotency_key' => 'dmg',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'quantity' => 2]],
    ]);
    $claim = SupplierExchangeClaim::query()->sole();
    actingAs($admin)->post(route('claims.review', $claim));
    actingAs($admin)->post(route('claims.agreement', $claim), ['lines' => [['line_id' => $claim->lines()->sole()->id, 'approved_quantity' => 2]]]);

    actingAs($admin)->post(route('claims.approve', $claim))->assertForbidden();
    actingAs(userWithRole(Role::ExchangeApprover))->post(route('claims.approve', $claim))->assertSessionHasNoErrors();
});

describe('privilege escalation', function () {
    function userManager(): User
    {
        $manager = userWithRole(Role::InventoryManager);
        $manager->givePermissionTo('users.manage');

        return $manager;
    }

    it('stops a non super admin granting the super admin role', function () {
        actingAs(userManager())->post(route('admin.users.store'), [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password',
            'roles' => [Role::SuperAdmin->value],
        ])->assertSessionHasErrors('roles');

        expect(User::query()->where('email', 'mallory@example.com')->exists())->toBeFalse();
    });

    it('stops a non super admin editing a super admin account', function () {
        $admin = userWithRole(Role::SuperAdmin);

        actingAs(userManager())->put(route('admin.users.update', $admin), [
            'name' => 'Hijacked',
            'email' => 'attacker@example.com',
            'is_active' => false,
            'roles' => [],
        ])->assertForbidden();

        expect($admin->refresh()->email)->not->toBe('attacker@example.com');
    });

    it('stops a non super admin changing role permissions', function () {
        $manager = userManager();
        $role = Spatie\Permission\Models\Role::findByName(Role::StockOperator->value);

        actingAs($manager)->put(route('admin.roles.update', $role), ['permissions' => ['users.manage']])->assertForbidden();

        expect($role->refresh()->hasPermissionTo('users.manage'))->toBeFalse();
    });
});

it('keeps damage evidence private and rejects svg uploads', function () {
    Storage::fake('local');
    $product = makeProduct(['Size' => ['L']], [['L']]);
    receiveStock($product, ['L' => 10]);
    $operator = userWithRole(Role::StockOperator);
    $payload = fn (UploadedFile $file, string $key) => [
        'warehouse_id' => defaultWarehouse()->id,
        'damage_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Torn',
        'idempotency_key' => $key,
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'quantity' => 1]],
        'images' => [$file],
    ];

    actingAs($operator)->post(route('damage.store'), $payload(UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"/>'), 'svg'))
        ->assertSessionHasErrors('images.0');

    actingAs($operator)->post(route('damage.store'), $payload(UploadedFile::fake()->image('photo.jpg'), 'jpg'))->assertSessionHasNoErrors();
    $image = DamageCaseImage::query()->sole();

    Storage::disk('local')->assertExists($image->path);
    auth()->logout();
    get(route('damage.images.show', $image))->assertRedirect(route('login'));
    actingAs(User::factory()->create())->get(route('damage.images.show', $image))->assertForbidden();
    actingAs($operator)->get(route('damage.images.show', $image))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('neutralises spreadsheet formulas in exported reports', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    $product->update(['name' => '=HYPERLINK("http://evil.test","click")']);
    receiveStock($product, ['L' => 5]);

    $csv = actingAs(userWithRole(Role::ReportViewer))->get(route('reports.export', ['variant-stock', 'csv']))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('limits the user activity report to people who may view activity', function () {
    ActivityLog::query()->create(['action' => 'x', 'description' => 'secret', 'ip_address' => '10.0.0.1']);

    actingAs(userWithRole(Role::ReportViewer))->get(route('reports.show', 'user-activity'))->assertNotFound();
    actingAs(userWithRole(Role::ReportViewer))->get(route('reports.export', ['user-activity', 'csv']))->assertNotFound();
    actingAs(userWithRole(Role::InventoryManager))->get(route('reports.show', 'user-activity'))->assertOk();
});

it('rejects malformed report dates instead of failing', function () {
    actingAs(userWithRole(Role::ReportViewer))->get(route('reports.show', ['daily-in-out', 'from' => 'not-a-date']))->assertSessionHasErrors('from');
});

it('does not follow a notification link to another host', function () {
    $user = userWithRole(Role::StockOperator);
    $user->notify(new WorkflowNotification('claim', 'Phish', 'Click', 'https://evil.test/login'));

    actingAs($user)->get(route('notifications.read', $user->notifications()->sole()->id))->assertRedirect(route('notifications.index'));
});

it('scopes duplicate-submission keys to the submitting user', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    $payload = [
        'warehouse_id' => defaultWarehouse()->id,
        'business_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => 'shared-key',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'quantity' => 4]],
    ];

    actingAs(userWithRole(Role::StockOperator))->post(route('stock.store', 'in'), $payload);
    actingAs(userWithRole(Role::StockOperator))->post(route('stock.store', 'in'), $payload);

    expect(StockMovement::query()->where('type', MovementType::StockIn)->count())->toBe(2)
        ->and(balanceOf(variantNamed($product, 'L'))->available)->toBe(8);
});

it('rejects postings to an inactive warehouse', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    $closed = Warehouse::factory()->create(['is_active' => false]);

    actingAs(userWithRole(Role::StockOperator))->post(route('stock.store', 'in'), [
        'warehouse_id' => $closed->id,
        'business_date' => now('Asia/Dhaka')->toDateString(),
        'idempotency_key' => 'k',
        'lines' => [['variant_id' => variantNamed($product, 'L')->id, 'quantity' => 4]],
    ])->assertSessionHasErrors('warehouse_id');
});

it('rejects an adjustment that counts the same variant twice', function () {
    $product = makeProduct(['Size' => ['L']], [['L']]);
    $line = ['variant_id' => variantNamed($product, 'L')->id, 'condition' => 'available', 'counted_quantity' => 3];

    actingAs(userWithRole(Role::StockOperator))->post(route('adjustments.store'), [
        'warehouse_id' => defaultWarehouse()->id,
        'count_date' => now('Asia/Dhaka')->toDateString(),
        'reason' => 'Count',
        'idempotency_key' => 'dup',
        'lines' => [$line, $line],
    ])->assertSessionHasErrors('lines');
});

it('sends defensive security headers', function () {
    actingAs(userWithRole(Role::StockOperator))->get(route('dashboard'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('lets a super admin create a verified user with roles', function () {
    actingAs(userWithRole(Role::SuperAdmin))->post(route('admin.users.store'), [
        'name' => 'New Operator',
        'email' => 'new.operator@example.com',
        'password' => 'password',
        'roles' => [Role::StockOperator->value],
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'new.operator@example.com')->sole();

    expect($user->hasRole(Role::StockOperator->value))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});
