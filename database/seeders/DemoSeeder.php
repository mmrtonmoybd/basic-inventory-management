<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantAttribute;
use App\Models\Warehouse;
use App\Services\AdjustmentService;
use App\Services\BusinessCalendar;
use App\Services\DamageService;
use App\Services\ExchangeClaimService;
use App\Services\ProductCatalogService;
use App\Services\ReturnService;
use App\Services\StockEntryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Realistic demo data generated exclusively through the domain services, so every balance is
 * backed by real ledger entries.
 */
class DemoSeeder extends Seeder
{
    /**
     * Demo staff accounts. They are removed again when demo data is cleared.
     */
    public const USER_EMAILS = [
        'manager@example.com',
        'operator@example.com',
        'qc@example.com',
        'coordinator@example.com',
        'approver@example.com',
        'viewer@example.com',
    ];

    /**
     * The administrator recorded as creating the demo catalogue; defaults to the first Super Admin.
     */
    public ?User $admin = null;

    public function __construct(
        private ProductCatalogService $catalog,
        private StockEntryService $stock,
        private ReturnService $returns,
        private DamageService $damage,
        private ExchangeClaimService $claims,
        private AdjustmentService $adjustments,
        private BusinessCalendar $calendar,
    ) {}

    public function run(): void
    {
        mt_srand(2026);

        $admin = $this->admin ?? User::role(Role::SuperAdmin->value)->orderBy('id')->firstOrFail();
        $users = $this->seedUsers();
        $warehouse = Warehouse::default();

        $suppliers = collect([
            ['ঢাকা নিট কম্পোজিট', 'DKC', 'রফিক হাসান', '01711000001', 'টঙ্গী শিল্প এলাকা, গাজীপুর'],
            ['চট্টগ্রাম গার্মেন্টস লিমিটেড', 'CGL', 'নুসরাত জাহান', '01811000002', 'ইপিজেড রোড, চট্টগ্রাম'],
            ['নারায়ণগঞ্জ টেক্সটাইল মিলস', 'NTM', 'কামাল উদ্দিন', '01911000003', 'বিসিক, নারায়ণগঞ্জ'],
            ['লেদারক্রাফট বিডি', 'LBD', 'সাদিয়া করিম', '01611000004', 'হাজারীবাগ, ঢাকা'],
        ])->map(fn (array $row) => Supplier::query()->firstOrCreate(['code' => $row[1]], [
            'name' => $row[0], 'contact_person' => $row[2], 'phone' => $row[3], 'address' => $row[4], 'is_active' => true,
        ]));

        $categories = collect(['Tops' => 'টপস', 'Shirts' => 'শার্ট', 'Accessories' => 'এক্সেসরিজ', 'Packaging' => 'প্যাকেজিং'])
            ->mapWithKeys(fn (string $name, string $key) => [$key => Category::query()->firstOrCreate(['slug' => Str::slug($key)], ['name' => $name])]);

        $products = $this->seedProducts($categories, $admin);

        $today = $this->calendar->today();
        $variantIds = $products->flatMap(fn (Product $product) => $product->variants()->pluck('id'));

        foreach ($products as $index => $product) {
            $this->stock->post(MovementType::Opening, [
                'warehouse_id' => $warehouse->id,
                'business_date' => $today->subDays(30)->toDateString(),
                'notes' => 'চালুর দিনের শুরুর মজুদ',
                'lines' => $product->variants()->get()->map(fn ($variant) => ['variant_id' => $variant->id, 'quantity' => mt_rand(20, 60)])->all(),
            ], $admin);
        }

        for ($day = 29; $day >= 0; $day--) {
            $date = $today->subDays($day)->toDateString();

            if ($day % 4 === 0) {
                $this->stock->post(MovementType::StockIn, [
                    'warehouse_id' => $warehouse->id,
                    'business_date' => $date,
                    'supplier_id' => $suppliers->random()->id,
                    'reference' => 'PO-'.mt_rand(1000, 9999),
                    'lines' => $this->pick($variantIds, 6)->map(fn ($id) => ['variant_id' => $id, 'quantity' => mt_rand(10, 40)])->values()->all(),
                ], $users['operator']);
            }

            $available = StockBalance::query()->where('warehouse_id', $warehouse->id)->pluck('available', 'product_variant_id');
            $outLines = $this->pick($variantIds, 5)
                ->map(fn ($id) => ['variant_id' => $id, 'quantity' => min(mt_rand(1, 6), (int) ($available[$id] ?? 0))])
                ->filter(fn (array $line) => $line['quantity'] > 0)
                ->values()
                ->all();

            if ($outLines !== []) {
                $this->stock->post(MovementType::StockOut, [
                    'warehouse_id' => $warehouse->id,
                    'business_date' => $date,
                    'reference' => 'ORD-'.mt_rand(10000, 99999),
                    'lines' => $outLines,
                ], $users['operator']);
            }
        }

        $polo = $products['POLO']->variants()->get()->keyBy('name');
        $tee = $products['TEE']->variants()->get()->keyBy('name');

        [$return] = $this->returns->create([
            'warehouse_id' => $warehouse->id,
            'return_date' => $today->subDays(6)->toDateString(),
            'customer_name' => 'আয়েশা রহমান',
            'reference' => 'ORD-55120',
            'reason' => 'সাইজ বদল',
            'lines' => [
                ['variant_id' => $polo['L / লাল']->id, 'quantity' => 5, 'reason' => 'ভুল সাইজ'],
                ['variant_id' => $polo['XL / কালো']->id, 'quantity' => 3, 'reason' => 'সেলাইয়ে ত্রুটি'],
            ],
        ], $users['qc']);

        $lines = $return->lines()->get()->keyBy('product_variant_id');
        $this->returns->inspect($return, [
            'damage_reason' => 'কলারের সেলাই ছেঁড়া',
            'lines' => [
                ['return_line_id' => $lines[$polo['L / লাল']->id]->id, 'good_quantity' => 5, 'damaged_quantity' => 0],
                ['return_line_id' => $lines[$polo['XL / কালো']->id]->id, 'good_quantity' => 1, 'damaged_quantity' => 2],
            ],
        ], $users['qc']);

        $this->returns->create([
            'warehouse_id' => $warehouse->id,
            'return_date' => $today->subDay()->toDateString(),
            'customer_name' => 'তানভীর আহমেদ',
            'reference' => 'ORD-56004',
            'reason' => 'মত পরিবর্তন',
            'lines' => [
                ['variant_id' => $tee['M / কালো']->id, 'quantity' => 2],
                ['variant_id' => $tee['L / সাদা']->id, 'quantity' => 1],
            ],
        ], $users['qc']);

        [$caseA] = $this->damage->record([
            'warehouse_id' => $warehouse->id,
            'damage_date' => $today->subDays(12)->toDateString(),
            'reason' => 'কিউসিতে রং উঠে যাওয়া পাওয়া গেছে',
            'supplier_id' => $suppliers[0]->id,
            'supplier_message' => 'অনুগ্রহ করে L/সবুজ ও XXL/সবুজ রং-পাকা মাল দিয়ে বদলে দিন।',
            'request_exchange' => true,
            'lines' => [['variant_id' => $polo['L / সবুজ']->id, 'quantity' => 4], ['variant_id' => $polo['XXL / সবুজ']->id, 'quantity' => 2]],
        ], $users['operator']);

        $claim = $caseA->claims()->firstOrFail();
        $this->claims->review($claim, $users['manager'], 'ছবি দেখে যাচাই করা হয়েছে।');
        $this->claims->recordSupplierAgreement($claim, [
            'supplier_reference' => 'DKC-RMA-8812',
            'note' => 'সরবরাহকারী পুরোটাই বদলে দিতে রাজি।',
            'lines' => $claim->lines()->get()->map(fn ($line) => ['line_id' => $line->id, 'approved_quantity' => $line->claimed_quantity])->all(),
        ], $users['coordinator']);
        $this->claims->approve($claim, $users['approver'], 'অনুমোদন দেওয়া হলো।');
        $this->claims->dispatch($claim, ['dispatch_date' => $today->subDays(9)->toDateString()], $users['operator']);
        $this->claims->receive($claim, [
            'received_date' => $today->subDays(3)->toDateString(),
            'lines' => $claim->lines()->get()->map(fn ($line) => ['line_id' => $line->id, 'quantity' => min(3, $line->dispatched_quantity)])->all(),
        ], $users['operator']);

        [$caseB] = $this->damage->record([
            'warehouse_id' => $warehouse->id,
            'damage_date' => $today->subDays(2)->toDateString(),
            'reason' => 'কার্টনে পানি লেগে নষ্ট',
            'supplier_id' => $suppliers[1]->id,
            'supplier_message' => 'কার্টন ভেজা অবস্থায় এসেছে, বদলে দেওয়ার অনুরোধ।',
            'request_exchange' => true,
            'lines' => [['variant_id' => $tee['S / সাদা']->id, 'quantity' => 3]],
        ], $users['operator']);

        $this->claims->review($caseB->claims()->firstOrFail(), $users['manager']);

        $this->damage->record([
            'warehouse_id' => $warehouse->id,
            'damage_date' => $today->toDateString(),
            'reason' => 'বাকল ভাঙা',
            'lines' => [['variant_id' => $products['BELT']->variants()->firstOrFail()->id, 'quantity' => 1]],
        ], $users['operator']);

        $variant = $tee['M / সাদা'];
        $this->adjustments->request([
            'warehouse_id' => $warehouse->id,
            'reason' => 'মাসিক গণনা',
            'lines' => [['variant_id' => $variant->id, 'condition' => 'available', 'counted_quantity' => max(0, $variant->balances()->value('available') - 2)]],
        ], $users['operator']);
    }

    /**
     * Deterministic sample (seeded by mt_srand) so demo data is reproducible.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, int>
     */
    private function pick($ids, int $count)
    {
        $pool = $ids->values()->all();
        $picked = [];

        while (count($picked) < min($count, count($pool))) {
            $candidate = $pool[mt_rand(0, count($pool) - 1)];

            if (! in_array($candidate, $picked, true)) {
                $picked[] = $candidate;
            }
        }

        return collect($picked);
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $definitions = [
            'manager' => ['মাহমুদ হোসেন', 'manager@example.com', Role::InventoryManager],
            'operator' => ['সুমন মিয়া', 'operator@example.com', Role::StockOperator],
            'qc' => ['ফারজানা আক্তার', 'qc@example.com', Role::ReturnQcOperator],
            'coordinator' => ['ইমরান কবির', 'coordinator@example.com', Role::SupplierCoordinator],
            'approver' => ['নাদিয়া ইসলাম', 'approver@example.com', Role::ExchangeApprover],
            'viewer' => ['রেজা চৌধুরী', 'viewer@example.com', Role::ReportViewer],
        ];

        $users = [];

        foreach ($definitions as $key => [$name, $email, $role]) {
            $users[$key] = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
            $users[$key]->forceFill(['email_verified_at' => $users[$key]->email_verified_at ?? now()])->save();
            $users[$key]->assignRole($role->value);
        }

        return $users;
    }

    /**
     * @param  Collection<string, Category>  $categories
     * @return Collection<string, Product>
     */
    private function seedProducts($categories, User $admin)
    {
        $attributes = VariantAttribute::query()->with('values')->get()->keyBy('slug');
        $value = fn (string $attribute, string $name): int => $attributes[$attribute]->values->firstWhere('value', $name)->id;

        $definitions = [
            'POLO' => ['প্রিমিয়াম পোলো টি-শার্ট', 'Tops', ['size', 'color'], [
                ['L', 'লাল'], ['L', 'সবুজ'], ['L', 'হলুদ'],
                ['XL', 'সাদা'], ['XL', 'কালো'], ['XL', 'নীল'],
                ['XXL', 'কালো'], ['XXL', 'সবুজ'],
            ], 15],
            'TEE' => ['ক্লাসিক গোল-গলা টি-শার্ট', 'Tops', ['size', 'color'], collect(['S', 'M', 'L'])->crossJoin(['সাদা', 'কালো', 'নেভি'])->all(), 12],
            'OXF' => ['অক্সফোর্ড বাটন-ডাউন শার্ট', 'Shirts', ['size', 'color', 'fabric'], [
                ['M', 'সাদা', 'সুতি'], ['L', 'সাদা', 'সুতি'], ['L', 'নীল', 'সুতি'], ['XL', 'নীল', 'পিকে'],
            ], 8],
            'TOTE' => ['ক্যানভাস টোট ব্যাগ', 'Accessories', ['color'], [['কালো'], ['নেভি'], ['লাল']], 10],
            'BELT' => ['চামড়ার বেল্ট', 'Accessories', ['size'], [['M'], ['L'], ['XL']], 5],
            'GIFT' => ['গিফট বক্স', 'Packaging', [], [], 25],
        ];

        $products = collect();

        foreach ($definitions as $code => [$name, $category, $axes, $combinations, $threshold]) {
            $products[$code] = Product::query()->where('code', $code)->first() ?? $this->catalog->create([
                'name' => $name,
                'code' => $code,
                'category_id' => $categories[$category]->id,
                'attribute_ids' => array_map(fn (string $axis): int => $attributes[$axis]->id, $axes),
                'variants' => $axes === []
                    ? [['low_stock_threshold' => $threshold]]
                    : array_map(fn (array $combination): array => [
                        'value_ids' => array_map(fn (string $axis, string $valueName): int => $value($axis, $valueName), $axes, $combination),
                        'low_stock_threshold' => $threshold,
                    ], $combinations),
            ], $admin);
        }

        return $products;
    }
}
