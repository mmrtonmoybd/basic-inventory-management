import { Head, Link, router } from '@inertiajs/react';
import { Calculator, Check, History, X } from 'lucide-react';
import { useState } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { actionLabel, businessDate, dateTime, qty, signedQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { approve, index, reject, show } from '@/routes/adjustments';
import { show as movementShow } from '@/routes/movements';
import type { HistoryRecord } from '@/types';

type Adjustment = {
    id: number;
    adjustment_no: string;
    count_date: string;
    reason: string;
    notes: string | null;
    status: string;
    decision_note: string | null;
    stock_movement_id: number | null;
    warehouse: string;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    created_at: string | null;
    lines: { id: number; product: string; sku: string; variant: string; condition: string; system_quantity: number; counted_quantity: number; difference: number }[];
    history: HistoryRecord[];
};

export default function AdjustmentShow({ adjustment, canDecide, isOwnRequest }: { adjustment: Adjustment; canDecide: boolean; isOwnRequest: boolean }) {
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const decide = (url: string) =>
        router.post(url, { note }, { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors });

    return (
        <>
            <Head title={adjustment.adjustment_no} />
            <Page className="max-w-5xl">
                <PageHeader title={adjustment.adjustment_no} description={adjustment.reason} meta={<StatusBadge status={adjustment.status} />} />

                {adjustment.status === 'pending' && canDecide && (
                    <section className="surface space-y-3 border-2 border-primary/30 bg-gradient-to-br from-indigo-500/5 to-transparent p-5">
                        <div>
                            <h2 className="font-semibold">এই গণনা অনুমোদন করবেন?</h2>
                            <p className="text-sm text-muted-foreground">অনুমোদন দিলে প্রতিটি পার্থক্য খতিয়ানে যোগ হবে। স্টক কখনো শূন্যের নিচে যাবে না।</p>
                        </div>
                        <Textarea value={note} onChange={(event) => setNote(event.target.value)} placeholder="সিদ্ধান্তের নোট (বাতিল করতে অবশ্যই লিখুন)" rows={2} />
                        {Object.values(errors)[0] && <p className="text-sm text-damaged">{Object.values(errors)[0]}</p>}
                        <div className="flex gap-2">
                            <Button disabled={processing} onClick={() => decide(approve.url(adjustment.id))}>
                                <Check /> অনুমোদন দিন
                            </Button>
                            <Button variant="outline" disabled={processing || !note.trim()} onClick={() => decide(reject.url(adjustment.id))}>
                                <X /> বাতিল করুন
                            </Button>
                        </div>
                    </section>
                )}

                {isOwnRequest && <p className="rounded-2xl border border-dashed px-5 py-4 text-sm text-muted-foreground">এই সমন্বয়ের অনুরোধ আপনি করেছেন, তাই অনুমোদনের অধিকার আছে এমন অন্য কেউ সিদ্ধান্ত দেবেন।</p>}

                <Panel>
                    <dl className="grid gap-5 sm:grid-cols-3 lg:grid-cols-5">
                        <Field label="গণনার তারিখ" value={businessDate(adjustment.count_date, 'long')} />
                        <Field label="গুদাম" value={adjustment.warehouse} />
                        <Field label="কে চেয়েছেন" value={`${adjustment.requested_by}, ${dateTime(adjustment.created_at)}`} />
                        <Field label="কে সিদ্ধান্ত দিয়েছেন" value={adjustment.decided_by ? `${adjustment.decided_by}, ${dateTime(adjustment.decided_at)}` : null} />
                        <Field
                            label="খতিয়ান এন্ট্রি"
                            value={
                                adjustment.stock_movement_id ? (
                                    <Link href={movementShow.url(adjustment.stock_movement_id)} className="text-primary hover:underline">
                                        লেনদেন দেখুন
                                    </Link>
                                ) : null
                            }
                        />
                    </dl>
                    {adjustment.notes && <p className="mt-4 border-t pt-4 text-sm text-muted-foreground">{adjustment.notes}</p>}
                    {adjustment.decision_note && <p className="mt-2 text-sm">সিদ্ধান্তের নোট: {adjustment.decision_note}</p>}
                </Panel>

                <Panel title="গণনার পার্থক্য" icon={Calculator} tone="sky" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>পণ্য</TableHead>
                                <TableHead>ভ্যারিয়েন্ট</TableHead>
                                <TableHead>অবস্থা</TableHead>
                                <TableHead className="text-right">গণনার সময় সিস্টেমে</TableHead>
                                <TableHead className="text-right">গুনে পাওয়া</TableHead>
                                <TableHead className="text-right">পার্থক্য</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {adjustment.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell className="font-medium">{line.product}</TableCell>
                                    <TableCell>
                                        {line.variant} <span className="text-xs text-muted-foreground">{line.sku}</span>
                                    </TableCell>
                                    <TableCell>{line.condition}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.system_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.counted_quantity)}</TableCell>
                                    <TableCell className={cn('text-right font-semibold tabular-nums', line.difference < 0 ? 'text-damaged' : 'text-available')}>{signedQty(line.difference)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>

                <Panel title="ইতিহাস" icon={History} tone="slate" bodyClassName="p-0">
                    <ul className="divide-y text-sm">
                        {adjustment.history.map((record) => (
                            <li key={record.id} className="px-5 py-3">
                                <span className="font-semibold">{actionLabel(record.action)}</span>, {record.user} <span className="text-muted-foreground">{dateTime(record.created_at)}</span>
                                {record.comment && <p className="text-muted-foreground">{record.comment}</p>}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </Page>
        </>
    );
}

AdjustmentShow.layout = (props: { adjustment: Adjustment }) => ({
    breadcrumbs: [
        { title: 'স্টক সমন্বয়', href: index() },
        { title: props.adjustment.adjustment_no, href: show(props.adjustment.id) },
    ],
});
