import { Head, Link, router } from '@inertiajs/react';
import { Plus, Scale } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { businessDate, qty, signedQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, index, show } from '@/routes/adjustments';
import type { Paginated } from '@/types';

type Row = { id: number; adjustment_no: string; count_date: string; reason: string; status: string; lines_count: number; net_difference: number; requested_by: string | null; decided_by: string | null };

const columns: ColumnDef<Row>[] = [
    { accessorKey: 'count_date', header: 'গণনার তারিখ', cell: ({ getValue }) => businessDate(getValue<string>(), 'long') },
    {
        accessorKey: 'adjustment_no',
        header: 'সমন্বয় নং',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="font-semibold text-primary hover:underline">
                {row.original.adjustment_no}
            </Link>
        ),
    },
    { accessorKey: 'reason', header: 'কারণ' },
    { accessorKey: 'lines_count', header: 'লাইন', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'net_difference', header: 'মোট পরিবর্তন', meta: { numeric: true }, cell: ({ getValue }) => <span className={cn('font-semibold', getValue<number>() < 0 ? 'text-damaged' : 'text-available')}>{signedQty(getValue<number>())}</span> },
    { accessorKey: 'status', header: 'অবস্থা', cell: ({ getValue }) => <StatusBadge status={getValue<string>()} /> },
    { accessorKey: 'requested_by', header: 'কে চেয়েছেন' },
    { accessorKey: 'decided_by', header: 'কে সিদ্ধান্ত দিয়েছেন', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
];

export default function AdjustmentsIndex({ adjustments, filters }: { adjustments: Paginated<Row>; filters: Record<string, string> }) {
    const can = useCan();
    const { filters: current, apply } = useFilters(index.url(), filters);

    return (
        <>
            <Head title="স্টক সমন্বয়" />
            <Page>
                <PageHeader
                    title="স্টক সমন্বয়"
                    description="হাতে গুনে সিস্টেমের সাথে না মিললে এখানে ঠিক করুন। খতিয়ানে যাওয়ার আগে অন্য একজনকে অবশ্যই অনুমোদন দিতে হবে।"
                    icon={Scale}
                    tone="sky"
                    actions={
                        can('adjustments.create') && (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus /> নতুন গণনা
                                </Link>
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SelectFilter
                            value={current.status}
                            onChange={(status) => apply({ status })}
                            placeholder="যেকোনো অবস্থা"
                            options={[
                                { value: 'pending', label: 'অনুমোদনের অপেক্ষায়' },
                                { value: 'approved', label: 'অনুমোদিত' },
                                { value: 'rejected', label: 'বাতিল' },
                            ]}
                        />
                    </FilterBar>
                    <DataTable columns={columns} data={adjustments.data} onRowClick={(row) => router.visit(show.url(row.id))} empty={<EmptyState icon={Scale} title="কোনো স্টক সমন্বয় নেই" description="তাকে থাকা মাল আর সিস্টেমের হিসাব না মিললে নতুন গণনা লিখুন।" />} />
                    <Pagination page={adjustments} className="border-t" />
                </section>
            </Page>
        </>
    );
}

AdjustmentsIndex.layout = { breadcrumbs: [{ title: 'স্টক সমন্বয়', href: index() }] };
