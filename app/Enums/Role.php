<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case InventoryManager = 'Inventory Manager';
    case StockOperator = 'Stock Operator';
    case ReturnQcOperator = 'Return/QC Operator';
    case SupplierCoordinator = 'Supplier Coordinator';
    case ExchangeApprover = 'Exchange Approver';
    case ReportViewer = 'Report Viewer';

    /**
     * Bengali display name. The stored role name stays stable for code and the database.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'সুপার অ্যাডমিন',
            self::InventoryManager => 'ইনভেন্টরি ম্যানেজার',
            self::StockOperator => 'স্টক অপারেটর',
            self::ReturnQcOperator => 'রিটার্ন/কিউসি অপারেটর',
            self::SupplierCoordinator => 'সরবরাহকারী সমন্বয়ক',
            self::ExchangeApprover => 'বদলি অনুমোদনকারী',
            self::ReportViewer => 'রিপোর্ট দর্শক',
        };
    }

    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }

    /**
     * Default permissions granted when roles are seeded. Super Admin bypasses all checks.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::cases(),
            self::InventoryManager => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => ! in_array($permission, [Permission::UsersManage, Permission::ClaimsApprove], true),
            )),
            self::StockOperator => [
                Permission::ProductsView, Permission::StockIn, Permission::StockOut, Permission::MovementsView,
                Permission::DamageCreate, Permission::ClaimsView, Permission::ClaimsDispatch, Permission::ClaimsReceive,
                Permission::SuppliersView, Permission::AdjustmentsCreate,
            ],
            self::ReturnQcOperator => [
                Permission::ProductsView, Permission::MovementsView, Permission::ReturnsCreate,
                Permission::ReturnsInspect, Permission::DamageCreate, Permission::ClaimsView, Permission::SuppliersView,
            ],
            self::SupplierCoordinator => [
                Permission::ProductsView, Permission::MovementsView, Permission::DamageCreate, Permission::ClaimsView,
                Permission::ClaimsSupplierAgreement, Permission::ClaimsDispatch, Permission::ClaimsReceive,
                Permission::SuppliersView, Permission::SuppliersManage,
            ],
            self::ExchangeApprover => [
                Permission::ProductsView, Permission::MovementsView, Permission::ClaimsView, Permission::ClaimsApprove,
                Permission::SuppliersView, Permission::ReportsView,
            ],
            self::ReportViewer => [
                Permission::ProductsView, Permission::MovementsView, Permission::ClaimsView, Permission::SuppliersView,
                Permission::ReportsView, Permission::ReportsExport,
            ],
        };
    }
}
