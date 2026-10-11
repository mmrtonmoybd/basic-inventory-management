<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Http\Requests\Claims\ReceiveReplacementRequest;
use App\Http\Requests\Claims\SupplierAgreementRequest;
use App\Models\ApprovalRecord;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use App\Models\SupplierExchangeLine;
use App\Models\SupplierExchangeReceipt;
use App\Services\BusinessCalendar;
use App\Services\ExchangeClaimService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeClaimController extends Controller
{
    public function __construct(private ExchangeClaimService $claims) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'supplier', 'search']);

        $claims = SupplierExchangeClaim::query()
            ->with(['supplier:id,name', 'creator:id,name'])
            ->withSum('lines as claimed_quantity', 'claimed_quantity')
            ->withSum('lines as approved_quantity', 'approved_quantity')
            ->withSum('lines as dispatched_quantity', 'dispatched_quantity')
            ->withSum('lines as received_quantity', 'received_quantity')
            ->withSum('lines as written_off_quantity', 'written_off_quantity')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $status === 'open'
                ? $query->whereNotIn('status', [ClaimStatus::Closed, ClaimStatus::Rejected])
                : $query->where('status', $status))
            ->when($filters['supplier'] ?? null, fn (Builder $query, $supplier) => $query->where('supplier_id', $supplier))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where('claim_no', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupplierExchangeClaim $claim): array => [
                'id' => $claim->id,
                'claim_no' => $claim->claim_no,
                'supplier' => $claim->supplier->name,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'claimed_quantity' => (int) $claim->claimed_quantity,
                'approved_quantity' => (int) $claim->approved_quantity,
                'received_quantity' => (int) $claim->received_quantity,
                'outstanding_quantity' => (int) $claim->dispatched_quantity - (int) $claim->received_quantity - (int) $claim->written_off_quantity,
                'created_by' => $claim->creator?->name,
                'created_at' => $claim->created_at?->toIso8601String(),
            ]);

        return Inertia::render('claims/index', [
            'claims' => $claims,
            'filters' => $filters,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(ClaimStatus::cases())->map(fn (ClaimStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function show(Request $request, SupplierExchangeClaim $claim, BusinessCalendar $calendar): Response
    {
        $claim->load([
            'supplier', 'damageCase:id,case_no,reason', 'creator:id,name', 'warehouse:id,name',
            'lines.variant.product:id,name', 'lines.replacementVariant.product:id,name',
            'receipts.receiver:id,name', 'receipts.movement:id,reference_no', 'receipts.lines',
            'approvalRecords.user:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('claims/show', [
            'claim' => [
                'id' => $claim->id,
                'claim_no' => $claim->claim_no,
                'status' => $claim->status->value,
                'status_label' => $claim->status->label(),
                'supplier' => $claim->supplier->only(['id', 'name', 'code', 'contact_person', 'phone']),
                'damage_case' => $claim->damageCase->only(['id', 'case_no', 'reason']),
                'warehouse' => $claim->warehouse->name,
                'supplier_message' => $claim->supplier_message,
                'supplier_reference' => $claim->supplier_reference,
                'supplier_agreement_note' => $claim->supplier_agreement_note,
                'rejection_reason' => $claim->rejection_reason,
                'dispatch_movement_id' => $claim->dispatch_movement_id,
                'created_by' => $claim->creator?->name,
                'created_at' => $claim->created_at?->toIso8601String(),
                'lines' => $claim->lines->map(fn (SupplierExchangeLine $line): array => [
                    'id' => $line->id,
                    'product' => $line->variant->product->name,
                    'product_id' => $line->variant->product_id,
                    'sku' => $line->variant->sku,
                    'variant' => $line->variant->name,
                    'replacement_variant_id' => $line->replacement_variant_id,
                    'replacement' => $line->replacementVariant ? "{$line->replacementVariant->sku} · {$line->replacementVariant->name}" : null,
                    'replacement_differs' => $line->replacement_variant_id !== null && $line->replacement_variant_id !== $line->product_variant_id,
                    'claimed_quantity' => $line->claimed_quantity,
                    'approved_quantity' => $line->approved_quantity,
                    'dispatched_quantity' => $line->dispatched_quantity,
                    'received_quantity' => $line->received_quantity,
                    'written_off_quantity' => $line->written_off_quantity,
                    'outstanding_quantity' => $line->outstandingQuantity(),
                ]),
                'receipts' => $claim->receipts->map(fn (SupplierExchangeReceipt $receipt): array => [
                    'id' => $receipt->id,
                    'receipt_no' => $receipt->receipt_no,
                    'received_date' => $receipt->received_date->toDateString(),
                    'received_by' => $receipt->receiver?->name,
                    'quantity' => $receipt->lines->sum('quantity'),
                    'movement' => $receipt->movement?->only(['id', 'reference_no']),
                    'notes' => $receipt->notes,
                ]),
                'history' => $claim->approvalRecords->map(fn (ApprovalRecord $record): array => [
                    'id' => $record->id,
                    'action' => $record->action,
                    'from_status' => $record->from_status,
                    'to_status' => $record->to_status,
                    'comment' => $record->comment,
                    'user' => $record->user?->name,
                    'created_at' => $record->created_at?->toIso8601String(),
                ]),
            ],
            'can' => [
                'review' => $user->can('review', $claim),
                'recordAgreement' => $user->can('recordAgreement', $claim),
                'approve' => $user->can('approve', $claim),
                'reject' => $user->can('reject', $claim),
                'dispatch' => $user->can('dispatch', $claim),
                'receive' => $user->can('receive', $claim),
                'closeShort' => $user->can('closeShort', $claim),
            ],
            'today' => $calendar->today()->toDateString(),
        ]);
    }

    public function review(Request $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        Gate::authorize('review', $claim);
        $this->claims->review($claim, $request->user(), $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null);
        $this->toast("দাবি {$claim->claim_no} যাচাই করা হয়েছে।");

        return back();
    }

    public function recordAgreement(SupplierAgreementRequest $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        $this->claims->recordSupplierAgreement($claim, $request->validated(), $request->user());
        $this->toast('সরবরাহকারীর সম্মতি লেখা হয়েছে। এখন চূড়ান্ত অনুমোদনের অপেক্ষা।');

        return back();
    }

    public function approve(Request $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        Gate::authorize('approve', $claim);
        $this->claims->approve($claim, $request->user(), $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null);
        $this->toast("দাবি {$claim->claim_no} অনুমোদিত। মাল পাঠানোর আগ পর্যন্ত স্টক বদলাবে না।");

        return back();
    }

    public function reject(Request $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        Gate::authorize('reject', $claim);
        $this->claims->reject($claim, $request->user(), $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']);
        $this->toast("দাবি {$claim->claim_no} বাতিল। মালগুলো ক্ষতিগ্রস্ত স্টকেই থাকল।", 'info');

        return back();
    }

    public function dispatch(Request $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        Gate::authorize('dispatch', $claim);
        $this->claims->dispatch($claim, $request->validate([
            'dispatch_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]), $request->user());
        $this->toast('মাল পাঠানো হয়েছে। ক্ষতিগ্রস্ত মাল এখন "সরবরাহকারীর কাছে" দেখাবে।');

        return back();
    }

    public function receive(ReceiveReplacementRequest $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        [$receipt, $created] = $this->claims->receive($claim, $request->validated(), $request->user());

        $created
            ? $this->toast("রসিদ {$receipt->receipt_no} সেভ হয়েছে। বদলি মাল বিক্রয়যোগ্য স্টকে যোগ হয়েছে।")
            : $this->toast("রসিদ {$receipt->receipt_no} আগেই সেভ হয়েছে।", 'info');

        return back();
    }

    public function closeShort(Request $request, SupplierExchangeClaim $claim): RedirectResponse
    {
        Gate::authorize('closeShort', $claim);
        $this->claims->closeShort($claim, $request->user(), $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason']);
        $this->toast("দাবি {$claim->claim_no} বন্ধ করা হয়েছে। বাকি মাল হিসাব থেকে বাদ দেওয়া হয়েছে।", 'info');

        return back();
    }
}
