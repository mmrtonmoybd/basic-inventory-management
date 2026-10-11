import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus, Shirt } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { qty } from '@/lib/format';
import { create, index, show } from '@/routes/products';
import type { Option, Paginated } from '@/types';

type ProductRow = {
    id: number;
    name: string;
    code: string;
    status: 'active' | 'archived';
    category: { id: number; name: string } | null;
    variants_count: number;
    active_variants_count: number;
    available_total: number;
    physical_total: number;
    low_stock_count: number;
};

const columns: ColumnDef<ProductRow>[] = [
    {
        accessorKey: 'name',
        header: 'পণ্য',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="group block min-w-0">
                <span className="block truncate font-semibold group-hover:underline">{row.original.name}</span>
                <span className="text-xs text-muted-foreground">
                    {row.original.code}
                    {row.original.category && `, ${row.original.category.name}`}
                </span>
            </Link>
        ),
    },
    {
        accessorKey: 'active_variants_count',
        header: 'ভ্যারিয়েন্ট',
        meta: { numeric: true },
        cell: ({ row }) => (
            <span>
                {qty(row.original.active_variants_count)}
                {row.original.variants_count > row.original.active_variants_count && <span className="text-muted-foreground"> / {qty(row.original.variants_count)}</span>}
            </span>
        ),
    },
    {
        accessorKey: 'available_total',
        header: 'বিক্রয়যোগ্য',
        meta: { numeric: true },
        cell: ({ getValue }) => <span className="font-semibold text-available">{qty(getValue<number>())}</span>,
    },
    {
        accessorKey: 'physical_total',
        header: 'গুদামে মোট',
        meta: { numeric: true },
        cell: ({ getValue }) => qty(getValue<number>()),
    },
    {
        accessorKey: 'low_stock_count',
        header: 'স্টক কম',
        meta: { numeric: true },
        cell: ({ getValue }) =>
            getValue<number>() > 0 ? (
                <span className="inline-flex items-center gap-1 font-medium text-damaged">
                    <AlertTriangle className="size-3.5" />
                    {qty(getValue<number>())}টি
                </span>
            ) : (
                <span className="text-muted-foreground">নেই</span>
            ),
    },
    {
        accessorKey: 'status',
        header: 'অবস্থা',
        cell: ({ getValue }) => <StatusBadge status={getValue<string>()} />,
    },
];

export default function ProductsIndex({ products, categories, filters }: { products: Paginated<ProductRow>; categories: Option[]; filters: Record<string, string> }) {
    const can = useCan();
    const { filters: current, apply, reset } = useFilters(index.url(), filters);

    return (
        <>
            <Head title="পণ্য" />
            <Page>
                <PageHeader
                    title="পণ্যের তালিকা"
                    description="সব পণ্য আর তাদের এখনকার স্টক। কোনো পণ্যে ক্লিক করলে প্রতিটি সাইজ-রঙের স্টক ও ইতিহাস দেখবেন।"
                    icon={Shirt}
                    tone="indigo"
                    actions={
                        can('products.manage') && (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus /> নতুন পণ্য
                                </Link>
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="নাম, কোড বা SKU" />
                        <SelectFilter value={current.category} onChange={(category) => apply({ category })} placeholder="সব ক্যাটাগরি" options={categories.map((category) => ({ value: String(category.id), label: category.name }))} />
                        <SelectFilter
                            value={current.status}
                            onChange={(status) => apply({ status })}
                            placeholder="যেকোনো অবস্থা"
                            options={[
                                { value: 'active', label: 'চালু' },
                                { value: 'archived', label: 'আর্কাইভ' },
                            ]}
                        />
                        <ResetFilters visible={Object.values(current).some(Boolean)} onReset={reset} />
                    </FilterBar>
                    <DataTable
                        columns={columns}
                        data={products.data}
                        onRowClick={(row) => router.visit(show.url(row.id))}
                        empty={
                            <EmptyState
                                icon={Shirt}
                                title={Object.values(current).some(Boolean) ? 'এই ফিল্টারে কোনো পণ্য নেই' : 'এখনো কোনো পণ্য নেই'}
                                description={Object.values(current).some(Boolean) ? 'অন্যভাবে খুঁজুন বা ফিল্টার মুছে দিন।' : 'প্রথম পণ্য তৈরি করুন আর কোন সাইজ-রঙগুলো আছে তা বেছে নিন।'}
                                action={
                                    can('products.manage') && !Object.values(current).some(Boolean) ? (
                                        <Button asChild size="sm">
                                            <Link href={create.url()}>নতুন পণ্য</Link>
                                        </Button>
                                    ) : undefined
                                }
                            />
                        }
                    />
                    <Pagination page={products} className="border-t" />
                </section>
            </Page>
        </>
    );
}

ProductsIndex.layout = { breadcrumbs: [{ title: 'পণ্য', href: index() }] };
