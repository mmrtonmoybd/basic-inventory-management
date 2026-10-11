import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, ListChecks } from 'lucide-react';
import { conditionByLabel } from '@/components/inventory/conditions';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { MovementType } from '@/components/inventory/status-badge';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { bnDigits, businessDate, dateTime, qty, signedQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/movements';
import { show as productShow } from '@/routes/products';

type Movement = {
    id: number;
    reference_no: string;
    type: string;
    type_label: string;
    business_date: string;
    external_reference: string | null;
    total_quantity: number;
    supplier: string | null;
    warehouse: string | null;
    created_by: string | null;
    created_at: string | null;
    notes: string | null;
    source: { label: string; url: string } | null;
    lines: { id: number; product: string; product_id: number; sku: string; variant: string; from: string | null; to: string | null; quantity: number; note: string | null }[];
    ledger: { id: number; line_id: number; condition: string; change: number; balance_after: number }[];
};

function ConditionChip({ label }: { label: string | null }) {
    if (!label) {
        return <span className="text-xs text-muted-foreground">বাইরে</span>;
    }

    const meta = conditionByLabel(label);

    return <span className={cn('inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', meta?.soft, meta?.text)}>{label}</span>;
}

export default function MovementShow({ movement }: { movement: Movement }) {
    return (
        <>
            <Head title={movement.reference_no} />
            <Page className="max-w-6xl">
                <PageHeader title={movement.reference_no} meta={<MovementType type={movement.type} label={movement.type_label} />} />

                <Panel>
                    <dl className="grid gap-5 sm:grid-cols-3 lg:grid-cols-6">
                        <Field label="তারিখ" value={businessDate(movement.business_date, 'long')} />
                        <Field label="মোট" value={`${qty(movement.total_quantity)} পিস`} />
                        <Field label="গুদাম" value={movement.warehouse} />
                        <Field label="সরবরাহকারী" value={movement.supplier} />
                        <Field label="বাইরের রেফারেন্স" value={movement.external_reference} />
                        <Field label="কে করেছেন" value={`${movement.created_by ?? 'সিস্টেম'}, ${dateTime(movement.created_at)}`} />
                    </dl>
                    {(movement.notes || movement.source) && (
                        <div className="mt-5 flex flex-wrap gap-6 border-t pt-4 text-sm">
                            {movement.source && (
                                <p>
                                    <span className="text-muted-foreground">মূল কাগজ: </span>
                                    <Link href={movement.source.url} className="font-medium text-primary hover:underline">
                                        {movement.source.label}
                                    </Link>
                                </p>
                            )}
                            {movement.notes && <p className="text-muted-foreground">{movement.notes}</p>}
                        </div>
                    )}
                </Panel>

                <Panel title="পণ্যের লাইন" icon={ListChecks} tone="indigo" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>পণ্য</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>ভ্যারিয়েন্ট</TableHead>
                                <TableHead>অবস্থার পরিবর্তন</TableHead>
                                <TableHead className="text-right">পরিমাণ</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movement.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell>
                                        <Link href={productShow.url(line.product_id)} className="font-medium hover:underline">
                                            {line.product}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{line.sku}</TableCell>
                                    <TableCell>{line.variant}</TableCell>
                                    <TableCell>
                                        <span className="inline-flex items-center gap-2">
                                            <ConditionChip label={line.from} />
                                            <ArrowRight className="size-3.5 text-muted-foreground" />
                                            <ConditionChip label={line.to} />
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">{qty(line.quantity)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <TableRow>
                                <TableCell colSpan={4}>মোট</TableCell>
                                <TableCell className="text-right tabular-nums">{qty(movement.total_quantity)}</TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </Panel>

                <Panel title="খতিয়ানের এন্ট্রি" description="প্রতিটি অবস্থার জন্য একটি করে স্থায়ী এন্ট্রি, সাথে লেনদেনের ঠিক পরের ব্যালান্স।" icon={BookOpen} tone="sky" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>এন্ট্রি</TableHead>
                                <TableHead>ভ্যারিয়েন্ট</TableHead>
                                <TableHead>অবস্থা</TableHead>
                                <TableHead className="text-right">পরিবর্তন</TableHead>
                                <TableHead className="text-right">পরের ব্যালান্স</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {movement.ledger.map((entry) => {
                                const line = movement.lines.find((item) => item.id === entry.line_id);

                                return (
                                    <TableRow key={entry.id}>
                                        <TableCell className="text-muted-foreground tabular-nums">#{bnDigits(entry.id)}</TableCell>
                                        <TableCell>{line ? `${line.sku}, ${line.variant}` : ''}</TableCell>
                                        <TableCell>
                                            <ConditionChip label={entry.condition} />
                                        </TableCell>
                                        <TableCell className={cn('text-right font-semibold tabular-nums', entry.change > 0 ? 'text-available' : 'text-damaged')}>{signedQty(entry.change)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{qty(entry.balance_after)}</TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </Panel>
            </Page>
        </>
    );
}

MovementShow.layout = (props: { movement: Movement }) => ({
    breadcrumbs: [
        { title: 'স্টক লেনদেন', href: index() },
        { title: props.movement.reference_no, href: show(props.movement.id) },
    ],
});
