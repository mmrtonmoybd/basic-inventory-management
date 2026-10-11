import { Head, router } from '@inertiajs/react';
import { BarChart3, FileDown, FileSpreadsheet, FileText } from 'lucide-react';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { FilterBar, SelectFilter } from '@/components/inventory/filter-bar';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TableCell, TableRow } from '@/components/ui/table';
import { businessDate, qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { exportMethod, index, show } from '@/routes/reports';
import type { Option, SelectOption } from '@/types';

type Column = { key: string; label: string; type: 'text' | 'number' | 'date' };
type Row = Record<string, string | number | null>;

type Report = {
    key: string;
    title: string;
    description: string;
    filters: Record<string, string | number | null> & { from: string; to: string };
    columns: Column[];
    rows: Row[];
    totals: Record<string, number> | null;
};

type Props = {
    report: Report & { filters: Report['filters'] };
    options: { products: Option[]; suppliers: Option[]; warehouses: Option[]; users: Option[]; types: SelectOption[] };
    canExport: boolean;
};

function iso(date: Date) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Dhaka' }).format(date);
}

export default function ReportShow({ report, options, canExport }: Props) {
    const query = Object.fromEntries(Object.entries(report.filters).filter(([, value]) => value !== null && value !== '').map(([key, value]) => [key, String(value)]));

    const apply = (patch: Record<string, string | undefined>) => {
        const next = Object.fromEntries(Object.entries({ ...query, ...patch }).filter(([, value]) => value !== undefined && value !== ''));
        router.get(show.url(report.key), next, { preserveScroll: true, preserveState: true, replace: true });
    };

    const today = new Date();
    const presets = [
        { label: 'আজ', from: iso(today), to: iso(today) },
        { label: 'গত ৭ দিন', from: iso(new Date(today.getTime() - 6 * 86400000)), to: iso(today) },
        { label: 'এই মাস', from: `${iso(today).slice(0, 8)}01`, to: iso(today) },
        {
            label: 'গত মাস',
            from: iso(new Date(today.getFullYear(), today.getMonth() - 1, 1)),
            to: iso(new Date(today.getFullYear(), today.getMonth(), 0)),
        },
    ];

    const columns: ColumnDef<Row>[] = report.columns.map((column) => ({
        id: column.key,
        accessorFn: (row: Row) => row[column.key],
        header: column.label,
        meta: { numeric: column.type === 'number' },
        cell: ({ getValue }) => {
            const value = getValue<string | number | null>();

            if (column.type === 'number') {
                const number = Number(value ?? 0);

                return <span className={cn(number === 0 && 'text-muted-foreground/60', ['closing', 'available'].includes(column.key) && number !== 0 && 'font-semibold')}>{qty(number)}</span>;
            }

            if (column.type === 'date') {
                return businessDate(String(value));
            }

            return value ?? <span className="text-muted-foreground">—</span>;
        },
    }));

    const exportUrl = (format: string) => exportMethod.url({ report: report.key, format }, { query });
    const usesSupplier = ['damage', 'supplier-exchange'].includes(report.key);
    const usesUser = report.key === 'user-activity';
    const usesType = report.key === 'movement-history';
    const usesProduct = !usesUser;

    return (
        <>
            <Head title={report.title} />
            <Page>
                <PageHeader
                    title={report.title}
                    description={`${report.description} সময়কাল: ${businessDate(report.filters.from, 'long')} থেকে ${businessDate(report.filters.to, 'long')}।`}
                    icon={BarChart3}
                    tone="sky"
                    actions={
                        canExport && (
                            <>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportUrl('csv')}>
                                        <FileDown /> CSV
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportUrl('xlsx')}>
                                        <FileSpreadsheet /> এক্সেল
                                    </a>
                                </Button>
                                <Button variant="outline" size="sm" asChild>
                                    <a href={exportUrl('pdf')}>
                                        <FileText /> PDF
                                    </a>
                                </Button>
                            </>
                        )
                    }
                />

                <section className="surface overflow-hidden">
                    <FilterBar className="gap-3">
                        <div className="flex flex-wrap items-center gap-1 rounded-xl bg-muted p-1">
                            {presets.map((preset) => {
                                const active = report.filters.from === preset.from && report.filters.to === preset.to;

                                return (
                                    <button key={preset.label} type="button" onClick={() => apply({ from: preset.from, to: preset.to })} className={cn('rounded-lg px-3 py-1 text-xs font-semibold text-muted-foreground transition-all', active && 'bg-brand text-white shadow-md')}>
                                        {preset.label}
                                    </button>
                                );
                            })}
                        </div>
                        <div className="flex items-center gap-2 text-sm">
                            <Input type="date" value={report.filters.from} onChange={(event) => apply({ from: event.target.value })} className="h-9 w-40" aria-label="শুরুর তারিখ" />
                            <span className="text-muted-foreground">থেকে</span>
                            <Input type="date" value={report.filters.to} onChange={(event) => apply({ to: event.target.value })} className="h-9 w-40" aria-label="শেষ তারিখ" />
                        </div>
                        {usesProduct && <SelectFilter value={query.product_id} onChange={(value) => apply({ product_id: value })} placeholder="সব পণ্য" options={options.products.map((product) => ({ value: String(product.id), label: product.name }))} />}
                        {usesSupplier && <SelectFilter value={query.supplier_id} onChange={(value) => apply({ supplier_id: value })} placeholder="সব সরবরাহকারী" options={options.suppliers.map((supplier) => ({ value: String(supplier.id), label: supplier.name }))} />}
                        {usesUser && <SelectFilter value={query.user_id} onChange={(value) => apply({ user_id: value })} placeholder="সব ব্যবহারকারী" options={options.users.map((user) => ({ value: String(user.id), label: user.name }))} />}
                        {usesType && <SelectFilter value={query.type} onChange={(value) => apply({ type: value })} placeholder="সব ধরনের লেনদেন" options={options.types} />}
                        {options.warehouses.length > 1 && !usesUser && <SelectFilter value={query.warehouse_id} onChange={(value) => apply({ warehouse_id: value })} placeholder="সব গুদাম" options={options.warehouses.map((warehouse) => ({ value: String(warehouse.id), label: warehouse.name }))} />}
                    </FilterBar>
                    <DataTable
                        columns={columns}
                        data={report.rows}
                        empty={<p className="py-12 text-center text-sm text-muted-foreground">এই সময়ে কোনো তথ্য নেই। তারিখের সীমা বাড়িয়ে দেখুন।</p>}
                        footer={
                            report.totals && report.rows.length > 0 ? (
                                <TableRow>
                                    {report.columns.map((column, position) => (
                                        <TableCell key={column.key} className={cn(column.type === 'number' && 'text-right tabular-nums')}>
                                            {position === 0 ? 'মোট' : column.key in (report.totals ?? {}) ? qty(report.totals![column.key]) : ''}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ) : undefined
                        }
                    />
                    <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                        মোট {qty(report.rows.length)}টি লাইন। গুদামের মজুদ = বিক্রয়যোগ্য + পরীক্ষাধীন + ক্ষতিগ্রস্ত; সরবরাহকারীর কাছে থাকা মাল আলাদা দেখানো হয়। এক অবস্থা থেকে আরেক অবস্থায় যাওয়াকে ইন বা আউট হিসেবে ধরা হয় না, তাই কিছুই দুইবার গোনা হয় না।
                    </p>
                </section>
            </Page>
        </>
    );
}

ReportShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'রিপোর্ট', href: index() },
        { title: props.report.title, href: show(props.report.key) },
    ],
});
