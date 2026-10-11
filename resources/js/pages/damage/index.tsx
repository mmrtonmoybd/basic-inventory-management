import { Head, Link, router } from '@inertiajs/react';
import { PackageX, Plus } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { businessDate, qty } from '@/lib/format';
import { create, index, show } from '@/routes/damage';
import type { Paginated, SelectOption } from '@/types';

type CaseRow = {
    id: number;
    case_no: string;
    damage_date: string;
    source: string;
    reason: string;
    supplier: string | null;
    status: string;
    status_label: string;
    total_quantity: number;
    claimed_quantity: number;
    reported_by: string | null;
};

const columns: ColumnDef<CaseRow>[] = [
    { accessorKey: 'damage_date', header: 'তারিখ', cell: ({ getValue }) => businessDate(getValue<string>(), 'long') },
    {
        accessorKey: 'case_no',
        header: 'কেস নং',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="font-semibold text-primary hover:underline">
                {row.original.case_no}
            </Link>
        ),
    },
    { accessorKey: 'source', header: 'কোথায় পাওয়া', cell: ({ getValue }) => (getValue<string>() === 'customer_return' ? 'কাস্টমার রিটার্ন' : 'গুদাম') },
    { accessorKey: 'reason', header: 'কারণ', cell: ({ getValue }) => <span className="block max-w-64 truncate">{getValue<string>()}</span> },
    { accessorKey: 'supplier', header: 'সরবরাহকারী', cell: ({ getValue }) => getValue<string>() ?? <span className="text-muted-foreground">—</span> },
    { accessorKey: 'total_quantity', header: 'ক্ষতিগ্রস্ত', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold text-damaged">{qty(getValue<number>())}</span> },
    { accessorKey: 'claimed_quantity', header: 'দাবি করা', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'status', header: 'অবস্থা', cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} /> },
    { accessorKey: 'reported_by', header: 'কে জানিয়েছেন' },
];

export default function DamageIndex({ cases, filters, statuses }: { cases: Paginated<CaseRow>; filters: Record<string, string>; statuses: SelectOption[] }) {
    const can = useCan();
    const { filters: current, apply, reset } = useFilters(index.url(), filters);
    const filtered = Object.values(current).some(Boolean);

    return (
        <>
            <Head title="ক্ষতিগ্রস্ত মাল" />
            <Page>
                <PageHeader
                    title="ক্ষতিগ্রস্ত মাল"
                    description="ক্ষতিগ্রস্ত মাল লেখার সাথে সাথে বিক্রয়যোগ্য স্টক থেকে সরে যায়। খোলা কেসের জন্য সরবরাহকারীর কাছে বদলি চাইতে পারবেন।"
                    icon={PackageX}
                    tone="rose"
                    actions={
                        can('damage.create') && (
                            <Button asChild>
                                <Link href={create.url()}>
                                    <Plus /> ক্ষতিগ্রস্ত মাল লিখুন
                                </Link>
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="কেস নং বা কারণ" />
                        <SelectFilter value={current.status} onChange={(status) => apply({ status })} placeholder="যেকোনো অবস্থা" options={statuses} />
                        <SelectFilter
                            value={current.source}
                            onChange={(source) => apply({ source })}
                            placeholder="যেকোনো জায়গা"
                            options={[
                                { value: 'warehouse', label: 'গুদাম' },
                                { value: 'customer_return', label: 'কাস্টমার রিটার্ন' },
                            ]}
                        />
                        <ResetFilters visible={filtered} onReset={reset} />
                    </FilterBar>
                    <DataTable columns={columns} data={cases.data} onRowClick={(row) => router.visit(show.url(row.id))} empty={<EmptyState icon={PackageX} title={filtered ? 'এই ফিল্টারে কোনো কেস নেই' : 'এখনো কোনো ক্ষতিগ্রস্ত মাল লেখা হয়নি'} />} />
                    <Pagination page={cases} className="border-t" />
                </section>
            </Page>
        </>
    );
}

DamageIndex.layout = { breadcrumbs: [{ title: 'ক্ষতিগ্রস্ত মাল', href: index() }] };
