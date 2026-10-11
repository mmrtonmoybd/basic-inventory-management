import { Head, Link, router } from '@inertiajs/react';
import { Plus, Undo2 } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { businessDate, qty } from '@/lib/format';
import { create, index, show } from '@/routes/returns';
import type { Paginated, SelectOption } from '@/types';

type ReturnRow = {
    id: number;
    return_no: string;
    return_date: string;
    customer_name: string | null;
    reference: string | null;
    status: string;
    status_label: string;
    total_quantity: number;
    good_quantity: number;
    damaged_quantity: number;
    created_by: string | null;
};

const columns: ColumnDef<ReturnRow>[] = [
    { accessorKey: 'return_date', header: 'তারিখ', cell: ({ getValue }) => businessDate(getValue<string>(), 'long') },
    {
        accessorKey: 'return_no',
        header: 'রিটার্ন নং',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="font-semibold text-primary hover:underline">
                {row.original.return_no}
            </Link>
        ),
    },
    { accessorKey: 'customer_name', header: 'কাস্টমার', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
    { accessorKey: 'reference', header: 'অর্ডার নং', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
    { accessorKey: 'total_quantity', header: 'ফেরত', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold">{qty(getValue<number>())}</span> },
    { accessorKey: 'good_quantity', header: 'ভালো', meta: { numeric: true }, cell: ({ getValue }) => <span className="text-available">{qty(getValue<number>())}</span> },
    { accessorKey: 'damaged_quantity', header: 'ক্ষতিগ্রস্ত', meta: { numeric: true }, cell: ({ getValue }) => <span className="text-damaged">{qty(getValue<number>())}</span> },
    { id: 'pending', accessorFn: (row) => row.total_quantity - row.good_quantity - row.damaged_quantity, header: 'পরীক্ষা বাকি', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold text-inspection-ink">{qty(getValue<number>())}</span> },
    { accessorKey: 'status', header: 'অবস্থা', cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} /> },
];

export default function ReturnsIndex({ returns, filters, statuses }: { returns: Paginated<ReturnRow>; filters: Record<string, string>; statuses: SelectOption[] }) {
    const can = useCan();
    const { filters: current, apply, reset } = useFilters(index.url(), filters);
    const filtered = Object.values(current).some(Boolean);

    return (
        <>
            <Head title="কাস্টমার রিটার্ন" />
            <Page>
                <PageHeader
                    title="কাস্টমার রিটার্ন"
                    description="ফেরত আসা মাল প্রথমে পরীক্ষাধীন স্টকে থাকে। পরীক্ষার পর ভালো মাল বিক্রয়যোগ্য স্টকে আর খারাপ মাল ক্ষতিগ্রস্ত স্টকে যায়।"
                    icon={Undo2}
                    tone="amber"
                    actions={
                        can('returns.create') && (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus /> নতুন রিটার্ন লিখুন
                                </Link>
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="রিটার্ন নং, অর্ডার বা কাস্টমার" />
                        <SelectFilter value={current.status} onChange={(status) => apply({ status })} placeholder="যেকোনো অবস্থা" options={statuses} />
                        <ResetFilters visible={filtered} onReset={reset} />
                    </FilterBar>
                    <DataTable columns={columns} data={returns.data} onRowClick={(row) => router.visit(show.url(row.id))} empty={<EmptyState icon={Undo2} title={filtered ? 'এই ফিল্টারে কোনো রিটার্ন নেই' : 'এখনো কোনো কাস্টমার রিটার্ন নেই'} />} />
                    <Pagination page={returns} className="border-t" />
                </section>
            </Page>
        </>
    );
}

ReturnsIndex.layout = { breadcrumbs: [{ title: 'কাস্টমার রিটার্ন', href: index() }] };
