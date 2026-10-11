<?php

namespace App\Http\Controllers;

use App\Enums\DamageCaseStatus;
use App\Http\Requests\Damage\StoreDamageRequest;
use App\Http\Requests\Damage\SubmitClaimRequest;
use App\Models\DamageCase;
use App\Models\DamageCaseImage;
use App\Models\DamageCaseLine;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\BusinessCalendar;
use App\Services\DamageService;
use App\Services\ExchangeClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DamageCaseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'source', 'search']);

        $cases = DamageCase::query()
            ->with(['reporter:id,name', 'supplier:id,name'])
            ->withSum('lines as total_quantity', 'quantity')
            ->withSum('lines as claimed_quantity', 'claimed_quantity')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->where('case_no', 'like', "%{$term}%")->orWhere('reason', 'like', "%{$term}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DamageCase $case): array => [
                'id' => $case->id,
                'case_no' => $case->case_no,
                'damage_date' => $case->damage_date->toDateString(),
                'source' => $case->source,
                'reason' => $case->reason,
                'supplier' => $case->supplier?->name,
                'status' => $case->status->value,
                'status_label' => $case->status->label(),
                'total_quantity' => (int) $case->total_quantity,
                'claimed_quantity' => (int) $case->claimed_quantity,
                'reported_by' => $case->reporter?->name,
            ]);

        return Inertia::render('damage/index', [
            'cases' => $cases,
            'filters' => $filters,
            'statuses' => collect(DamageCaseStatus::cases())->map(fn (DamageCaseStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function create(BusinessCalendar $calendar): Response
    {
        return Inertia::render('damage/create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'today' => $calendar->today()->toDateString(),
        ]);
    }

    public function store(StoreDamageRequest $request, DamageService $service): RedirectResponse
    {
        [$case, $created] = $service->record([...$request->validated(), 'images' => $request->file('images', [])], $request->user());

        $created
            ? $this->toast("ক্ষতিগ্রস্ত মাল {$case->case_no} লেখা হয়েছে। এই মাল আর বিক্রয়যোগ্য স্টকে নেই।")
            : $this->toast("ক্ষতিগ্রস্ত মাল {$case->case_no} আগেই লেখা হয়েছে।", 'info');

        return to_route('damage.show', $case);
    }

    public function show(DamageCase $damageCase): Response
    {
        $damageCase->load(['lines.variant.product:id,name', 'images', 'reporter:id,name', 'supplier:id,name', 'customerReturn:id,return_no', 'claims.supplier:id,name']);

        return Inertia::render('damage/show', [
            'damageCase' => [
                'id' => $damageCase->id,
                'case_no' => $damageCase->case_no,
                'damage_date' => $damageCase->damage_date->toDateString(),
                'source' => $damageCase->source,
                'reason' => $damageCase->reason,
                'internal_note' => $damageCase->internal_note,
                'supplier_message' => $damageCase->supplier_message,
                'supplier_id' => $damageCase->supplier_id,
                'supplier' => $damageCase->supplier?->name,
                'status' => $damageCase->status->value,
                'status_label' => $damageCase->status->label(),
                'reported_by' => $damageCase->reporter?->name,
                'created_at' => $damageCase->created_at?->toIso8601String(),
                'stock_movement_id' => $damageCase->stock_movement_id,
                'customer_return' => $damageCase->customerReturn?->only(['id', 'return_no']),
                'lines' => $damageCase->lines->map(fn (DamageCaseLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->variant->product->name,
                    'sku' => $line->variant->sku,
                    'variant' => $line->variant->name,
                    'quantity' => $line->quantity,
                    'claimed_quantity' => $line->claimed_quantity,
                    'unclaimed_quantity' => $line->unclaimedQuantity(),
                ]),
                'images' => $damageCase->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'url' => route('damage.images.show', $image),
                    'name' => $image->original_name,
                ]),
                'claims' => $damageCase->claims->map(fn ($claim): array => [
                    'id' => $claim->id,
                    'claim_no' => $claim->claim_no,
                    'supplier' => $claim->supplier->name,
                    'status' => $claim->status->value,
                    'status_label' => $claim->status->label(),
                ]),
            ],
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Streams an evidence photo from private storage to authorised users only.
     */
    public function image(DamageCaseImage $image): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($image->path), 404);

        return response()->file(Storage::disk('local')->path($image->path), [
            'Content-Type' => Storage::disk('local')->mimeType($image->path),
            'Content-Disposition' => 'inline; filename="evidence-'.$image->id.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function submitClaim(SubmitClaimRequest $request, DamageCase $damageCase, ExchangeClaimService $claims): RedirectResponse
    {
        $claim = $claims->submit($damageCase, $request->validated(), $request->user());

        $this->toast("বদলির দাবি {$claim->claim_no} যাচাইয়ের জন্য পাঠানো হয়েছে।");

        return to_route('claims.show', $claim);
    }
}
