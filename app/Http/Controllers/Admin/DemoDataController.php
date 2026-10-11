<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\DemoDataService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin screen to load sample data for training, and clear it before going live.
 */
class DemoDataController extends Controller
{
    public const CONFIRMATION = 'ডেমো মুছুন';

    public function __construct(private DemoDataService $demo) {}

    public function index(Request $request): Response
    {
        $this->ensureSuperAdmin($request);

        return Inertia::render('admin/demo-data', [
            'status' => $this->demo->status(),
            'canLoad' => ! $this->demo->isLoaded() && ! $this->demo->hasInventoryData(),
            'counts' => [
                'products' => Product::query()->count(),
                'variants' => ProductVariant::query()->count(),
                'movements' => StockMovement::query()->count(),
                'suppliers' => Supplier::query()->count(),
            ],
            'demoUsers' => DemoSeeder::USER_EMAILS,
            'confirmation' => self::CONFIRMATION,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);
        $this->demo->load($request->user());
        $this->toast('ডেমো ডেটা যোগ হয়েছে। এখন সিস্টেমটি ঘুরে দেখতে পারেন।');

        return to_route('admin.demo-data.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);
        $request->validate(['confirmation' => ['required', 'in:'.self::CONFIRMATION]], ['confirmation.in' => 'নিশ্চিত করতে ঠিক “'.self::CONFIRMATION.'” লিখুন।']);

        $this->demo->clear($request->user());
        $this->toast('ডেমো ডেটা মুছে ফেলা হয়েছে। এখন আসল পণ্য ও স্টক যোগ করতে পারেন।');

        return to_route('admin.demo-data.index');
    }

    private function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole(Role::SuperAdmin->value), 403);
    }
}
