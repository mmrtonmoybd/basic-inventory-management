<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        $totals = DB::table('stock_balances')
            ->selectRaw('warehouse_id, SUM(available + inspection + damaged) as physical, SUM(available) as available')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        return Inertia::render('admin/warehouses', [
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get()->map(fn (Warehouse $warehouse): array => [
                ...$warehouse->only(['id', 'name', 'code', 'address', 'is_default', 'is_active']),
                'physical' => (int) ($totals[$warehouse->id]->physical ?? 0),
                'available' => (int) ($totals[$warehouse->id]->available ?? 0),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $warehouse = Warehouse::query()->create($this->validated($request));
        $this->syncDefault($warehouse);
        $this->toast("গুদাম {$warehouse->name} যোগ হয়েছে।");

        return back();
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));
        $this->syncDefault($warehouse);
        $this->toast('গুদামের তথ্য সেভ হয়েছে।');

        return back();
    }

    private function syncDefault(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            Warehouse::query()->whereKeyNot($warehouse->id)->update(['is_default' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        return [...$data, 'code' => Str::upper($data['code'])];
    }
}
