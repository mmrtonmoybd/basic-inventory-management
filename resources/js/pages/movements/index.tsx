import { Head, Link, router } from '@inertiajs/react';
import { History } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { MovementType } from '@/components/inventory/status-badge';
import { Input } from '@/components/ui/input';
import { businessDate, qty } from '@/lib/format';
import { index, show } from '@/routes/movements';
import type { Paginated, SelectOption } from '@/types';

type MovementRow = {
    id: number;
    reference_no: string;
    type: string;
    type_label: string;
    business_date: string;
    external_reference: string | null;
    total_quantity: number;
    lines_count: number;
    supplier: string | null;
    created_by: string | null;
};

const columns: ColumnDef<MovementRow>[] = [
    { accessorKey: 'business_date', header: 'তারিখ', cell: ({ getValue }) => businessDate(getValue<string>(), 'long') },
    {
        accessorKey: 'reference_no',
        header: 'রেফারেন্স',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="font-semibold text-primary hover:underline">
                {row.original.reference_no}
            </Link>
        ),
    },
    { accessorKey: 'type_label', header: 'ধরন', cell: ({ row }) => <MovementType type={row.original.type} label={row.original.type_label} /> },
    { accessorKey: 'external_reference', header: 'বাইরের রেফারেন্স', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
    { accessorKey: 'supplier', header: 'সরবরাহকারী', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
    { accessorKey: 'lines_count', header: 'লাইন', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'total_quantity', header: 'পিস', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold">{qty(getValue<number>())}</span> },
    { accessorKey: 'created_by', header: 'কে করেছেন', cell: ({ getValue }) => getValue<string>() ?? 'সিস্টেম' },
];

export default function MovementsIndex({ movements, filters, types }: { movements: Paginated<MovementRow>; filters: Record<string, string>; types: SelectOption[] }) {
    const { filters: current, apply, reset } = useFilters(index.url(), filters);
    const filtered = Object.values(current).some(Boolean);

    return (
        <>
            <Head title="স্টক লেনদেন" />
            <Page>
                <PageHeader title="স্টক লেনদেন" description="খতিয়ানে যত লেনদেন হয়েছে, নতুনগুলো আগে। কোনো লেনদেন মোছা বা বদলানো যায় না; ভুল হলে নতুন এন্ট্রি দিয়ে ঠিক করতে হয়।" icon={History} tone="sky" />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="রেফারেন্স বা SKU" />
                        <SelectFilter value={current.type} onChange={(type) => apply({ type })} placeholder="সব ধরন" options={types} />
                        <div className="flex items-center gap-2 text-sm">
                            <Input type="date" value={current.from ?? ''} onChange={(event) => apply({ from: event.target.value })} className="w-40" aria-label="শুরুর তারিখ" />
                            <span className="text-muted-foreground">থেকে</span>
                            <Input type="date" value={current.to ?? ''} onChange={(event) => apply({ to: event.target.value })} className="w-40" aria-label="শেষ তারিখ" />
                        </div>
                        <ResetFilters visible={filtered} onReset={reset} />
                    </FilterBar>
                    <DataTable
                        columns={columns}
                        data={movements.data}
                        onRowClick={(row) => router.visit(show.url(row.id))}
                        empty={<EmptyState icon={History} title={filtered ? 'এই ফিল্টারে কোনো লেনদেন নেই' : 'এখনো কোনো লেনদেন নেই'} description={filtered ? 'তারিখের সীমা বাড়ান বা ফিল্টার মুছুন।' : 'স্টক ইন করলে খতিয়ান শুরু হবে।'} />}
                    />
                    <Pagination page={movements} className="border-t" />
                </section>
            </Page>
        </>
    );
}

MovementsIndex.layout = { breadcrumbs: [{ title: 'স্টক লেনদেন', href: index() }] };
