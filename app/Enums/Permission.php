<?php

namespace App\Enums;

enum Permission: string
{
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case CatalogManage = 'catalog.manage';
    case StockIn = 'stock.in';
    case StockOut = 'stock.out';
    case StockOpening = 'stock.opening';
    case StockImport = 'stock.import';
    case MovementsView = 'movements.view';
    case ReturnsCreate = 'returns.create';
    case ReturnsInspect = 'returns.inspect';
    case DamageCreate = 'damage.create';
    case ClaimsView = 'claims.view';
    case ClaimsReview = 'claims.review';
    case ClaimsSupplierAgreement = 'claims.supplier_agreement';
    case ClaimsApprove = 'claims.approve';
    case ClaimsDispatch = 'claims.dispatch';
    case ClaimsReceive = 'claims.receive';
    case SuppliersView = 'suppliers.view';
    case SuppliersManage = 'suppliers.manage';
    case AdjustmentsCreate = 'adjustments.create';
    case AdjustmentsApprove = 'adjustments.approve';
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';
    case LedgerReconcile = 'ledger.reconcile';
    case ActivityView = 'activity.view';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::ProductsView => 'পণ্য ও স্টক দেখা',
            self::ProductsManage => 'পণ্য ও ভ্যারিয়েন্ট তৈরি ও সম্পাদনা',
            self::CatalogManage => 'সাইজ/রং, ক্যাটাগরি ও গুদাম পরিচালনা',
            self::StockIn => 'স্টক ইন করা',
            self::StockOut => 'স্টক আউট করা',
            self::StockOpening => 'শুরুর মজুদ যোগ করা',
            self::StockImport => 'CSV থেকে একসাথে আমদানি',
            self::MovementsView => 'স্টক লেনদেন ও খতিয়ান দেখা',
            self::ReturnsCreate => 'কাস্টমার রিটার্ন লেখা',
            self::ReturnsInspect => 'ফেরত মাল পরীক্ষা করা',
            self::DamageCreate => 'ক্ষতিগ্রস্ত মাল ও বদলির অনুরোধ লেখা',
            self::ClaimsView => 'বদলির দাবি দেখা',
            self::ClaimsReview => 'বদলির দাবি যাচাই (ম্যানেজার)',
            self::ClaimsSupplierAgreement => 'সরবরাহকারীর সম্মতি লেখা',
            self::ClaimsApprove => 'বদলির দাবি চূড়ান্ত অনুমোদন/বাতিল',
            self::ClaimsDispatch => 'ক্ষতিগ্রস্ত মাল সরবরাহকারীর কাছে পাঠানো',
            self::ClaimsReceive => 'বদলি মাল গ্রহণ',
            self::SuppliersView => 'সরবরাহকারী দেখা',
            self::SuppliersManage => 'সরবরাহকারী তৈরি ও সম্পাদনা',
            self::AdjustmentsCreate => 'স্টক সমন্বয়ের অনুরোধ',
            self::AdjustmentsApprove => 'স্টক সমন্বয় অনুমোদন',
            self::ReportsView => 'রিপোর্ট ও ড্যাশবোর্ড বিশ্লেষণ দেখা',
            self::ReportsExport => 'রিপোর্ট ডাউনলোড',
            self::LedgerReconcile => 'হিসাব মিলানো চালানো',
            self::ActivityView => 'কাজের ইতিহাস দেখা',
            self::UsersManage => 'ব্যবহারকারী ও রোল পরিচালনা',
        };
    }

    public function group(): string
    {
        return match (str($this->value)->before('.')->toString()) {
            'products', 'catalog' => 'পণ্য',
            'stock', 'movements' => 'স্টক',
            'returns' => 'রিটার্ন',
            'damage', 'claims' => 'ক্ষতি ও বদলি',
            'suppliers' => 'সরবরাহকারী',
            'adjustments' => 'স্টক সমন্বয়',
            'reports', 'ledger', 'activity' => 'রিপোর্ট ও নিরীক্ষা',
            default => 'প্রশাসন',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
