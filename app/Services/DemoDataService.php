<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Loads a realistic demo dataset into an empty system and removes it again before go-live.
 *
 * Clearing is the only path that deletes ledger rows. It is allowed solely while the system is in
 * demo mode, so real inventory history can never be removed through it.
 */
class DemoDataService
{
    public const FLAG = 'demo_data';

    /**
     * Tables emptied when demo data is cleared, children before parents.
     *
     * @var list<string>
     */
    private const TABLES = [
        'approval_records',
        'supplier_exchange_receipt_lines',
        'supplier_exchange_receipts',
        'supplier_exchange_lines',
        'supplier_exchange_claims',
        'damage_case_images',
        'damage_case_lines',
        'damage_cases',
        'return_inspection_lines',
        'return_inspections',
        'return_lines',
        'customer_returns',
        'stock_adjustment_lines',
        'stock_adjustments',
        'stock_ledger_entries',
        'stock_movement_lines',
        'stock_movements',
        'stock_balances',
        'product_variant_attribute_values',
        'product_variants',
        'product_attributes',
        'products',
        'categories',
        'suppliers',
        'notifications',
        'activity_logs',
    ];

    public function __construct(private ActivityLogger $activity) {}

    public function isLoaded(): bool
    {
        return (bool) (Setting::get(self::FLAG)['loaded'] ?? false);
    }

    /**
     * @return array{loaded: bool, loaded_at: string|null, loaded_by: string|null}
     */
    public function status(): array
    {
        $flag = Setting::get(self::FLAG) ?? [];

        return [
            'loaded' => (bool) ($flag['loaded'] ?? false),
            'loaded_at' => $flag['loaded_at'] ?? null,
            'loaded_by' => $flag['loaded_by'] ?? null,
        ];
    }

    public function hasInventoryData(): bool
    {
        return Product::query()->exists() || StockMovement::query()->exists();
    }

    public function load(User $admin): void
    {
        if ($this->isLoaded()) {
            throw ValidationException::withMessages(['demo' => 'ডেমো ডেটা আগেই যোগ করা আছে।']);
        }

        if ($this->hasInventoryData()) {
            throw ValidationException::withMessages(['demo' => 'ডেমো ডেটা শুধু খালি সিস্টেমে যোগ করা যায়। এখানে আগে থেকেই পণ্য বা স্টক লেনদেন আছে।']);
        }

        DB::transaction(function () use ($admin): void {
            app(CatalogSeeder::class)->run();

            $seeder = app(DemoSeeder::class);
            $seeder->admin = $admin;
            $seeder->run();

            Setting::put(self::FLAG, ['loaded' => true, 'loaded_at' => now()->toIso8601String(), 'loaded_by' => $admin->name]);
        });

        $this->activity->log('demo.loaded', 'ডেমো ডেটা যোগ করা হয়েছে', user: $admin);
    }

    /**
     * Removes every product, stock record, document and demo account. Keeps real users, roles,
     * attributes and warehouses.
     *
     * @return array<string, int> Rows removed per table.
     */
    public function clear(User $admin): array
    {
        if (! $this->isLoaded()) {
            throw ValidationException::withMessages(['demo' => 'ডেমো ডেটা চালু নেই, তাই মোছার কিছু নেই। আসল স্টকের ইতিহাস কখনো মোছা হয় না।']);
        }

        $removed = DB::transaction(function () use ($admin): array {
            $removed = [];

            foreach (self::TABLES as $table) {
                $removed[$table] = DB::table($table)->delete();
            }

            $demoUserIds = User::query()
                ->whereIn('email', DemoSeeder::USER_EMAILS)
                ->whereKeyNot($admin->id)
                ->pluck('id');

            DB::table('model_has_roles')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $demoUserIds)->delete();
            DB::table('model_has_permissions')->where('model_type', (new User)->getMorphClass())->whereIn('model_id', $demoUserIds)->delete();
            $removed['users'] = User::query()->whereKey($demoUserIds)->delete();

            Setting::put(self::FLAG, ['loaded' => false, 'loaded_at' => null, 'loaded_by' => null]);

            return $removed;
        });

        Storage::disk('local')->deleteDirectory('damage-cases');

        $this->activity->log('demo.cleared', 'ডেমো ডেটা মুছে ফেলা হয়েছে, এখন আসল কাজ শুরু করা যাবে', properties: ['removed' => array_filter($removed)], user: $admin);

        return $removed;
    }
}
