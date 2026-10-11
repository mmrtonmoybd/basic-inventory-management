<?php

namespace App\Http\Controllers;

use App\Services\Bangla;
use App\Enums\MovementType;
use App\Http\Requests\Stock\StockEntryRequest;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\BusinessCalendar;
use App\Services\StockEntryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockEntryController extends Controller
{
    public function create(Request $request, string $type, BusinessCalendar $calendar): Response
    {
        $movementType = $this->movementType($type);

        abort_unless($request->user()->can($this->permission($type)), 403);

        return Inertia::render('stock/entry', [
            'mode' => $type,
            'title' => $movementType->label(),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'today' => $calendar->today()->toDateString(),
        ]);
    }

    public function store(StockEntryRequest $request, string $type, StockEntryService $service): RedirectResponse
    {
        [$movement, $created] = $service->post($this->movementType($type), $request->validated(), $request->user());

        $created
            ? $this->toast("{$movement->reference_no} সেভ হয়েছে: ".Bangla::number($movement->total_quantity).' পিস।')
            : $this->toast("{$movement->reference_no} আগেই সেভ হয়েছে, তাই আবার জমা নেওয়া হয়নি।", 'info');

        return to_route('movements.show', $movement);
    }

    private function movementType(string $type): MovementType
    {
        return match ($type) {
            'in' => MovementType::StockIn,
            'out' => MovementType::StockOut,
            'opening' => MovementType::Opening,
            default => abort(404),
        };
    }

    private function permission(string $type): string
    {
        return $type === 'opening' ? 'stock.opening' : "stock.{$type}";
    }
}
