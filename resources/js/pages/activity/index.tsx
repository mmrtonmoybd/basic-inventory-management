import { Head } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { EmptyState } from '@/components/inventory/empty-state';
import { FilterBar, ResetFilters, SearchFilter, SelectFilter, useFilters } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { dateTime } from '@/lib/format';
import { index } from '@/routes/activity';
import type { Option, Paginated } from '@/types';

type Log = { id: number; user: string; action: string; description: string; properties: Record<string, unknown> | null; ip_address: string | null; created_at: string | null };

const columns: ColumnDef<Log>[] = [
    { accessorKey: 'created_at', header: 'কখন (ঢাকা)', cell: ({ getValue }) => dateTime(getValue<string>()) },
    { accessorKey: 'user', header: 'ব্যবহারকারী', cell: ({ getValue }) => <span className="font-medium">{getValue<string>()}</span> },
    { accessorKey: 'action', header: 'কাজ', cell: ({ getValue }) => <span className="rounded bg-muted px-1.5 py-0.5 text-xs">{getValue<string>()}</span> },
    { accessorKey: 'description', header: 'বিবরণ', meta: { className: 'whitespace-normal min-w-80' } },
    { accessorKey: 'ip_address', header: 'আইপি', cell: ({ getValue }) => <span className="text-muted-foreground">{getValue<string>() ?? '—'}</span> },
];

export default function ActivityIndex({ logs, users, filters }: { logs: Paginated<Log>; users: Option[]; filters: Record<string, string> }) {
    const { filters: current, apply, reset } = useFilters(index.url(), filters);

    return (
        <>
            <Head title="কাজের ইতিহাস" />
            <Page>
                <PageHeader title="কাজের ইতিহাস" description="কে কখন কী তৈরি, যাচাই, অনুমোদন, পাঠানো বা গ্রহণ করেছেন। এই তালিকা বদলানো বা মোছা যায় না।" icon={ListChecks} tone="slate" />
                <section className="surface overflow-hidden">
                    <FilterBar>
                        <SearchFilter value={current.search} onChange={(search) => apply({ search })} placeholder="বিবরণ বা কাজ খুঁজুন" />
                        <SelectFilter value={current.user} onChange={(user) => apply({ user })} placeholder="সব ব্যবহারকারী" options={users.map((user) => ({ value: String(user.id), label: user.name }))} />
                        <ResetFilters visible={Object.values(current).some(Boolean)} onReset={reset} />
                    </FilterBar>
                    <DataTable columns={columns} data={logs.data} empty={<EmptyState icon={ListChecks} title="কোনো কাজ পাওয়া যায়নি" />} />
                    <Pagination page={logs} className="border-t" />
                </section>
            </Page>
        </>
    );
}

ActivityIndex.layout = { breadcrumbs: [{ title: 'কাজের ইতিহাস', href: index() }] };
