import { Head, Link, router } from '@inertiajs/react';
import { Plus, Truck } from 'lucide-react';
import { useState } from 'react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { qty } from '@/lib/format';
import { index, show } from '@/routes/suppliers';
import type { Paginated } from '@/types';
import { SupplierDialog, type SupplierRecord } from '@/components/inventory/supplier-dialog';

type SupplierRow = SupplierRecord & { pending_claims_count: number; open_claims_count: number; outstanding_quantity: number };

const columns: ColumnDef<SupplierRow>[] = [
    {
        accessorKey: 'name',
        header: 'সরবরাহকারী',
        cell: ({ row }) => (
            <Link href={show.url(row.original.id)} className="group block">
                <span className="font-semibold group-hover:underline">{row.original.name}</span>
                <span className="block text-xs text-muted-foreground">{row.original.code}</span>
            </Link>
        ),
    },
    { accessorKey: 'contact_person', header: 'যোগাযোগ', cell: ({ row }) => <span>{[row.original.contact_person, row.original.phone].filter(Boolean).join(', ') || '—'}</span> },
    { accessorKey: 'pending_claims_count', header: 'অনুমোদনের অপেক্ষায়', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'open_claims_count', header: 'চলমান দাবি', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
    { accessorKey: 'outstanding_quantity', header: 'তাদের কাছে মাল', meta: { numeric: true }, cell: ({ getValue }) => <span className="font-semibold text-supplier">{qty(Number(getValue()))}</span> },
    { accessorKey: 'is_active', header: 'অবস্থা', cell: ({ getValue }) => <StatusBadge status={getValue<boolean>() ? 'active' : 'inactive'} /> },
];

export default function SuppliersIndex({ suppliers, filters }: { suppliers: Paginated<SupplierRow>; filters: Record<string, string> }) {
    const can = useCan();
    const [creating, setCreating] = useState(false);
    const { filters: current, apply, reset } = useFilters(index.url(), filters);

    return (
        <>
            <Head title="সরবরাহকারী" />
            <Page>
                <PageHeader
                    title="সরবরাহকারী"
                    description="যাদের কাছ থেকে মাল কেনেন এবং ক্ষতিগ্রস্ত মাল বদলে নেন।"
                    icon={Truck}
                    tone="violet"
                    actions={
                        can('suppliers.manage') && (
                            <Button onClick={() => setCreating(true)}>
                                <Plus /> নতুন সরবরাহকারী
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="নাম, কোড বা যোগাযোগ" />
                        <SelectFilter
                            value={current.status}
                            onChange={(status) => apply({ status })}
                            placeholder="যেকোনো অবস্থা"
                            options={[
                                { value: 'active', label: 'চালু' },
                                { value: 'inactive', label: 'বন্ধ' },
                            ]}
                        />
                        <ResetFilters visible={Object.values(current).some(Boolean)} onReset={reset} />
                    </FilterBar>
                    <DataTable columns={columns} data={suppliers.data} onRowClick={(row) => router.visit(show.url(row.id))} empty={<EmptyState icon={Truck} title="কোনো সরবরাহকারী পাওয়া যায়নি" />} />
                    <Pagination page={suppliers} className="border-t" />
                </section>
            </Page>
            {creating && <SupplierDialog open={creating} onOpenChange={setCreating} />}
        </>
    );
}

SuppliersIndex.layout = { breadcrumbs: [{ title: 'সরবরাহকারী', href: index() }] };
