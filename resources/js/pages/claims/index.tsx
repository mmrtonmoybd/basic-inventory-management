import { Head, Link, router } from '@inertiajs/react';
import { Repeat } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { dateTime, qty } from '@/lib/format';
import { index, show } from '@/routes/claims';
import type { Option, Paginated, SelectOption } from '@/types';

type ClaimRow = {
    id: number;
    claim_no: string;
    supplier: string;
    status: string;
    status_label: string;
    claimed_quantity: number;
    approved_quantity: number;
    received_quantity: number;
    outstanding_quantity: number;
    created_by: string | null;
    created_at: string | null;
};

const columns: ColumnDef<ClaimRow>[] = [
    {
        accessorKey: 'claim_no',
        header: 'দাবি নং',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="font-semibold text-primary hover:underline">
                {row.original.claim_no}
            </Link>
        ),
    },
    { accessorKey: 'supplier', header: 'সরবরাহকারী' },
    { accessorKey: 'status', header: 'অবস্থা', cell: ({ row }) => <StatusBadge status={row.original.status} label={row.original.status_label} /> },
    { accessorKey: 'claimed_quantity', header: 'দাবি', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'approved_quantity', header: 'অনুমোদিত', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'received_quantity', header: 'পাওয়া গেছে', meta: { numeric: true }, cell: ({ getValue }) => <span className="text-available">{qty(getValue<number>())}</span> },
    { accessorKey: 'outstanding_quantity', header: 'সরবরাহকারীর কাছে', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold text-supplier">{qty(getValue<number>())}</span> },
    { accessorKey: 'created_by', header: 'কে করেছেন' },
    { accessorKey: 'created_at', header: 'কখন', cell: ({ getValue }) => dateTime(getValue<string>()) },
];

export default function ClaimsIndex({ claims, filters, suppliers, statuses }: { claims: Paginated<ClaimRow>; filters: Record<string, string>; suppliers: Option[]; statuses: SelectOption[] }) {
    const { filters: current, apply, reset } = useFilters(index.url(), filters);
    const filtered = Object.values(current).some(Boolean);

    return (
        <>
            <Head title="বদলির দাবি" />
            <Page>
                <PageHeader title="সরবরাহকারীর কাছে বদলির দাবি" description="ক্ষতিগ্রস্ত মাল বদলে নেওয়ার প্রতিটি দাবি: যাচাই থেকে শুরু করে বদলি মাল হাতে পাওয়া পর্যন্ত।" icon={Repeat} tone="violet" />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="দাবি নম্বর" />
                        <SelectFilter value={current.status} onChange={(status) => apply({ status })} placeholder="যেকোনো অবস্থা" options={[{ value: 'open', label: 'সব চলমান দাবি' }, ...statuses]} />
                        <SelectFilter value={current.supplier} onChange={(supplier) => apply({ supplier })} placeholder="সব সরবরাহকারী" options={suppliers.map((supplier) => ({ value: String(supplier.id), label: supplier.name }))} />
                        <ResetFilters visible={filtered} onReset={reset} />
                    </FilterBar>
                    <DataTable columns={columns} data={claims.data} onRowClick={(row) => router.visit(show.url(row.id))} empty={<EmptyState icon={Repeat} title={filtered ? 'এই ফিল্টারে কোনো দাবি নেই' : 'এখনো কোনো বদলির দাবি নেই'} description="ক্ষতিগ্রস্ত মালের কেস থেকে দাবি তৈরি করা যায়।" />} />
                    <Pagination page={claims} className="border-t" />
                </section>
            </Page>
        </>
    );
}

ClaimsIndex.layout = { breadcrumbs: [{ title: 'বদলির দাবি', href: index() }] };
