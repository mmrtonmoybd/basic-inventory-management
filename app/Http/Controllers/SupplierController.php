<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Enums\MovementType;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        $suppliers = Supplier::query()
            ->withCount([
                'claims as pending_claims_count' => fn (Builder $query) => $query->whereIn('status', ClaimStatus::awaitingApproval()),
                'claims as open_claims_count' => fn (Builder $query) => $query->whereNotIn('status', [ClaimStatus::Closed, ClaimStatus::Rejected]),
            ])
            ->addSelect(['outstanding_quantity' => DB::table('supplier_exchange_lines')
                ->join('supplier_exchange_claims', 'supplier_exchange_claims.id', '=', 'supplier_exchange_lines.supplier_exchange_claim_id')
                ->whereColumn('supplier_exchange_claims.supplier_id', 'suppliers.id')
                ->selectRaw('COALESCE(SUM(dispatched_quantity - received_quantity - written_off_quantity), 0)')])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('contact_person', 'like', "%{$term}%")))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('suppliers/index', ['suppliers' => $suppliers, 'filters' => $filters]);
    }

    public function store(SupplierRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $supplier = Supplier::query()->create([...$request->validated(), 'code' => Str::upper($request->validated('code'))]);

        $activity->log('supplier.created', "নতুন সরবরাহকারী {$supplier->name} যোগ হয়েছে", $supplier);
        $this->toast("সরবরাহকারী {$supplier->name} যোগ হয়েছে।");

        return to_route('suppliers.show', $supplier);
    }

    public function show(Supplier $supplier): Response
    {
        $lineTotals = DB::table('supplier_exchange_lines')
            ->join('supplier_exchange_claims', 'supplier_exchange_claims.id', '=', 'supplier_exchange_lines.supplier_exchange_claim_id')
            ->where('supplier_exchange_claims.supplier_id', $supplier->id)
            ->selectRaw('COALESCE(SUM(claimed_quantity),0) as claimed, COALESCE(SUM(approved_quantity),0) as approved, COALESCE(SUM(dispatched_quantity),0) as dispatched, COALESCE(SUM(received_quantity),0) as received, COALESCE(SUM(written_off_quantity),0) as written_off')
            ->first();

        $claims = $supplier->claims()
            ->withSum('lines as claimed_quantity', 'claimed_quantity')
            ->withSum('lines as received_quantity', 'received_quantity')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SupplierExchangeClaim $claim): array => [
                'id' => $claim->id,
                'claim_no' => $claim->claim_no,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'claimed_quantity' => (int) $claim->claimed_quantity,
                'received_quantity' => (int) $claim->received_quantity,
                'created_at' => $claim->created_at?->toIso8601String(),
            ]);

        $deliveries = $supplier->movements()
            ->where('type', MovementType::StockIn)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'reference_no', 'business_date', 'total_quantity', 'external_reference'])
            ->map(fn ($movement): array => [
                'id' => $movement->id,
                'reference_no' => $movement->reference_no,
                'business_date' => $movement->business_date->toDateString(),
                'total_quantity' => $movement->total_quantity,
                'external_reference' => $movement->external_reference,
            ]);

        return Inertia::render('suppliers/show', [
            'supplier' => $supplier,
            'stats' => [
                'pending_claims' => $supplier->claims()->whereIn('status', ClaimStatus::awaitingApproval())->count(),
                'approved_claims' => $supplier->claims()->whereIn('status', [ClaimStatus::Approved, ClaimStatus::Dispatched, ClaimStatus::PartiallyReceived, ClaimStatus::Closed])->count(),
                'claimed' => (int) $lineTotals->claimed,
                'approved' => (int) $lineTotals->approved,
                'received' => (int) $lineTotals->received,
                'outstanding' => (int) $lineTotals->dispatched - (int) $lineTotals->received - (int) $lineTotals->written_off,
                'written_off' => (int) $lineTotals->written_off,
                'delivered' => (int) $supplier->movements()->where('type', MovementType::StockIn)->sum('total_quantity'),
            ],
            'claims' => $claims,
            'deliveries' => $deliveries,
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, ActivityLogger $activity): RedirectResponse
    {
        $supplier->update([...$request->validated(), 'code' => Str::upper($request->validated('code'))]);

        $activity->log('supplier.updated', "সরবরাহকারী {$supplier->name} এর তথ্য বদলানো হয়েছে", $supplier, $supplier->getChanges());
        $this->toast('সরবরাহকারীর তথ্য সেভ হয়েছে।');

        return back();
    }
}
