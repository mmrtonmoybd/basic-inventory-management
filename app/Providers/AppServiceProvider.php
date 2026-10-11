<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\CustomerReturn;
use App\Models\DamageCase;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierExchangeClaim;
use App\Models\SupplierExchangeReceipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureAuthorization();
    }

    /**
     * Stable, readable morph types for ledger sources, approvals and activity logs.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'supplier' => Supplier::class,
            'stock_movement' => StockMovement::class,
            'customer_return' => CustomerReturn::class,
            'damage_case' => DamageCase::class,
            'supplier_exchange_claim' => SupplierExchangeClaim::class,
            'supplier_exchange_receipt' => SupplierExchangeReceipt::class,
            'stock_adjustment' => StockAdjustment::class,
        ]);
    }

    protected function configureAuthorization(): void
    {
        /*
         * Super Admin passes every permission check, except abilities that enforce segregation of
         * duties (nobody approves their own request). Those policies still run, and their
         * permission checks pass for a Super Admin through this same hook.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if (in_array($ability, ['decide', 'approve'], true)) {
                return null;
            }

            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
