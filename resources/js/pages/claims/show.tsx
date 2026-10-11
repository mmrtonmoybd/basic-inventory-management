import { Head, Link, router } from '@inertiajs/react';
import { Check, ClipboardList, History, ListChecks, PackageCheck, Send, ShieldCheck, Truck, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { actionLabel, bnDigits, businessDate, dateTime, onlyDigits, qty, uuid } from '@/lib/format';
import { getJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { agreement, approve, closeShort, dispatch, index, receive, reject, review, show } from '@/routes/claims';
import { show as damageShow } from '@/routes/damage';
import { variants as lookupVariants } from '@/routes/lookup';
import { show as movementShow } from '@/routes/movements';
import { show as supplierShow } from '@/routes/suppliers';
import type { HistoryRecord, VariantLookup } from '@/types';

type Line = {
    id: number;
    product: string;
    product_id: number;
    sku: string;
    variant: string;
    replacement_variant_id: number | null;
    replacement: string | null;
    replacement_differs: boolean;
    claimed_quantity: number;
    approved_quantity: number;
    dispatched_quantity: number;
    received_quantity: number;
    written_off_quantity: number;
    outstanding_quantity: number;
};

type Claim = {
    id: number;
    claim_no: string;
    status: string;
    status_label: string;
    supplier: { id: number; name: string; code: string; contact_person: string | null; phone: string | null };
    damage_case: { id: number; case_no: string; reason: string };
    warehouse: string;
    supplier_message: string | null;
    supplier_reference: string | null;
    supplier_agreement_note: string | null;
    rejection_reason: string | null;
    dispatch_movement_id: number | null;
    created_by: string | null;
    created_at: string | null;
    lines: Line[];
    receipts: { id: number; receipt_no: string; received_date: string; received_by: string | null; quantity: number; movement: { id: number; reference_no: string } | null; notes: string | null }[];
    history: HistoryRecord[];
};

type Can = { review: boolean; recordAgreement: boolean; approve: boolean; reject: boolean; dispatch: boolean; receive: boolean; closeShort: boolean };

const STEPS = [
    { key: 'pending_review', label: 'জমা' },
    { key: 'reviewed', label: 'ম্যানেজার যাচাই' },
    { key: 'supplier_agreed', label: 'সরবরাহকারী রাজি' },
    { key: 'approved', label: 'চূড়ান্ত অনুমোদন' },
    { key: 'dispatched', label: 'পাঠানো হয়েছে' },
    { key: 'partially_received', label: 'গ্রহণ চলছে' },
    { key: 'closed', label: 'সম্পন্ন' },
];

export default function ClaimShow({ claim, can, today }: { claim: Claim; can: Can; today: string }) {
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const post = (url: string, data: Record<string, unknown>, onSuccess?: () => void) =>
        router.post(url, data as never, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                setErrors({});
                onSuccess?.();
            },
        });

    const totals = claim.lines.reduce(
        (sum, line) => ({
            claimed: sum.claimed + line.claimed_quantity,
            approved: sum.approved + line.approved_quantity,
            received: sum.received + line.received_quantity,
            outstanding: sum.outstanding + line.outstanding_quantity,
        }),
        { claimed: 0, approved: 0, received: 0, outstanding: 0 },
    );

    return (
        <>
            <Head title={claim.claim_no} />
            <Page className="max-w-6xl">
                <PageHeader
                    title={claim.claim_no}
                    meta={
                        <>
                            <StatusBadge status={claim.status} label={claim.status_label} />
                            <Link href={supplierShow.url(claim.supplier.id)} className="hover:underline">
                                {claim.supplier.name}
                            </Link>
                            <Link href={damageShow.url(claim.damage_case.id)} className="hover:underline">
                                কেস {claim.damage_case.case_no} থেকে
                            </Link>
                        </>
                    }
                />

                <Stepper status={claim.status} />

                {claim.status === 'rejected' && (
                    <div className="rounded-2xl border border-damaged/40 bg-damaged-soft px-5 py-4 text-sm">
                        <p className="font-semibold text-damaged">দাবিটি বাতিল হয়েছে</p>
                        <p>{claim.rejection_reason}</p>
                        <p className="mt-1 text-muted-foreground">ক্ষতিগ্রস্ত মাল ক্ষতিগ্রস্ত স্টকেই থাকবে। চাইলে আবার দাবি করতে পারেন বা স্টক সমন্বয় দিয়ে বাদ দিতে পারেন।</p>
                    </div>
                )}

                <ActionPanel claim={claim} can={can} today={today} post={post} processing={processing} errors={errors} />

                <Panel title="দাবির লাইন" icon={ListChecks} tone="violet" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>ক্ষতিগ্রস্ত ভ্যারিয়েন্ট</TableHead>
                                <TableHead>বদলি</TableHead>
                                <TableHead className="text-right">দাবি</TableHead>
                                <TableHead className="text-right">অনুমোদিত</TableHead>
                                <TableHead className="text-right">পাঠানো</TableHead>
                                <TableHead className="text-right">পাওয়া</TableHead>
                                <TableHead className="text-right">বাদ</TableHead>
                                <TableHead className="text-right">সরবরাহকারীর কাছে</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {claim.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {line.product} / {line.variant}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{line.sku}</p>
                                    </TableCell>
                                    <TableCell className={cn(line.replacement_differs ? 'font-medium text-supplier' : 'text-muted-foreground')}>{line.replacement_differs ? line.replacement : 'একই ভ্যারিয়েন্ট'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.claimed_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.approved_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.dispatched_quantity)}</TableCell>
                                    <TableCell className="text-right text-available tabular-nums">{qty(line.received_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.written_off_quantity)}</TableCell>
                                    <TableCell className="text-right font-semibold text-supplier tabular-nums">{qty(line.outstanding_quantity)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Panel title="অনুমোদনের ইতিহাস" icon={History} tone="sky" bodyClassName="p-0">
                        <ol className="relative space-y-0">
                            {claim.history.map((record, position) => (
                                <li key={record.id} className="relative flex gap-4 px-5 py-3">
                                    <div className="flex flex-col items-center">
                                        <span className={cn('mt-1 size-2.5 rounded-full', record.action === 'rejected' ? 'bg-damaged' : 'bg-primary')} />
                                        {position < claim.history.length - 1 && <span className="mt-1 w-px flex-1 bg-border" />}
                                    </div>
                                    <div className="min-w-0 flex-1 pb-1">
                                        <p className="text-sm">
                                            <span className="font-semibold">{actionLabel(record.action)}</span>, {record.user ?? 'সিস্টেম'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{dateTime(record.created_at)}</p>
                                        {record.comment && <p className="mt-1 text-sm text-muted-foreground">{record.comment}</p>}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </Panel>
                    <div className="space-y-6">
                        <Panel title="সারসংক্ষেপ" icon={ClipboardList} tone="indigo">
                            <dl className="grid grid-cols-2 gap-4">
                                <Field label="দাবি" value={`${qty(totals.claimed)} পিস`} />
                                <Field label="অনুমোদিত" value={`${qty(totals.approved)} পিস`} />
                                <Field label="পাওয়া গেছে" value={`${qty(totals.received)} পিস`} />
                                <Field label="সরবরাহকারীর কাছে" value={`${qty(totals.outstanding)} পিস`} />
                                <Field label="সরবরাহকারীর রেফারেন্স" value={claim.supplier_reference} />
                                <Field label="যোগাযোগ" value={[claim.supplier.contact_person, claim.supplier.phone].filter(Boolean).join(', ') || null} />
                            </dl>
                            {claim.supplier_message && (
                                <div className="mt-4 border-t pt-3 text-sm">
                                    <p className="text-xs text-muted-foreground">সরবরাহকারীকে বার্তা</p>
                                    <p>{claim.supplier_message}</p>
                                </div>
                            )}
                            {claim.supplier_agreement_note && (
                                <div className="mt-3 text-sm">
                                    <p className="text-xs text-muted-foreground">সরবরাহকারীর সম্মতি</p>
                                    <p>{claim.supplier_agreement_note}</p>
                                </div>
                            )}
                        </Panel>
                        {claim.receipts.length > 0 && (
                            <Panel title="গ্রহণের রসিদ" icon={PackageCheck} tone="teal" bodyClassName="p-0">
                                <ul className="divide-y text-sm">
                                    {claim.receipts.map((receipt) => (
                                        <li key={receipt.id} className="px-5 py-3">
                                            <div className="flex justify-between">
                                                <span className="font-medium">{receipt.receipt_no}</span>
                                                <span className="font-semibold text-available">+{qty(receipt.quantity)}</span>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {businessDate(receipt.received_date, 'long')}, {receipt.received_by}
                                            </p>
                                            {receipt.movement && (
                                                <Link href={movementShow.url(receipt.movement.id)} className="text-xs text-primary hover:underline">
                                                    {receipt.movement.reference_no}
                                                </Link>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}
                    </div>
                </div>
            </Page>
        </>
    );
}

function Stepper({ status }: { status: string }) {
    const rejected = status === 'rejected';
    const current = rejected ? -1 : STEPS.findIndex((step) => step.key === status);

    return (
        <ol className="surface grid grid-cols-2 gap-px overflow-hidden bg-border sm:grid-cols-4 lg:grid-cols-7">
            {STEPS.map((step, index) => {
                const done = !rejected && index < current;
                const active = !rejected && index === current;

                return (
                    <li key={step.key} className={cn('flex items-center gap-2.5 bg-card px-4 py-3', active && 'bg-accent')}>
                        <span
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold tabular-nums',
                                done && 'border-transparent bg-gradient-to-br from-emerald-500 to-teal-600 text-white',
                                active && 'animate-pulse border-transparent bg-brand text-white shadow-md',
                                !done && !active && 'text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : bnDigits(index + 1)}
                        </span>
                        <span className={cn('text-sm', active ? 'font-semibold' : 'text-muted-foreground')}>{step.label}</span>
                    </li>
                );
            })}
        </ol>
    );
}

function ActionCard({ title, description, icon: Icon, children }: { title: string; description: string; icon: typeof Check; children: ReactNode }) {
    return (
        <section className="surface border-2 border-primary/30 bg-gradient-to-br from-indigo-500/5 to-transparent p-5">
            <div className="mb-4 flex items-start gap-3">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand text-white shadow-lg shadow-indigo-500/30">
                    <Icon className="size-5" />
                </div>
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
            </div>
            {children}
        </section>
    );
}

type ActionProps = {
    claim: Claim;
    can: Can;
    today: string;
    post: (url: string, data: Record<string, unknown>, onSuccess?: () => void) => void;
    processing: boolean;
    errors: Record<string, string>;
};

function ActionPanel({ claim, can, today, post, processing, errors }: ActionProps) {
    const [comment, setComment] = useState('');
    const [rejecting, setRejecting] = useState(false);

    const rejectBox = can.reject && (
        <div className="mt-4 border-t pt-4">
            {rejecting ? (
                <div className="space-y-2">
                    <Label htmlFor="reject-reason">বাতিলের কারণ</Label>
                    <Textarea id="reject-reason" value={comment} onChange={(event) => setComment(event.target.value)} rows={2} />
                    {errors.reason && <p className="text-sm text-damaged">{errors.reason}</p>}
                    <div className="flex gap-2">
                        <Button variant="destructive" size="sm" disabled={processing || !comment.trim()} onClick={() => post(reject.url(claim.id), { reason: comment })}>
                            <X /> দাবি বাতিল করুন
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => setRejecting(false)}>
                            থাক
                        </Button>
                    </div>
                </div>
            ) : (
                <Button variant="ghost" size="sm" className="text-damaged" onClick={() => setRejecting(true)}>
                    এই দাবি বাতিল করুন
                </Button>
            )}
        </div>
    );

    if (claim.status === 'pending_review' && (can.review || can.reject)) {
        return (
            <ActionCard title="ম্যানেজারের যাচাই" description="সরবরাহকারীর সাথে যোগাযোগের আগে ক্ষতির প্রমাণ আর পরিমাণ মিলিয়ে দেখুন।" icon={ShieldCheck}>
                {can.review && (
                    <div className="space-y-3">
                        <Textarea value={comment} onChange={(event) => setComment(event.target.value)} placeholder="যাচাইয়ের মন্তব্য (ইচ্ছামতো)" rows={2} />
                        <Button disabled={processing} onClick={() => post(review.url(claim.id), { comment })}>
                            <Check /> যাচাই সম্পন্ন
                        </Button>
                    </div>
                )}
                {rejectBox}
            </ActionCard>
        );
    }

    if (claim.status === 'reviewed' && (can.recordAgreement || can.reject)) {
        return (
            <ActionCard title="সরবরাহকারীর সম্মতি লিখুন" description="সরবরাহকারী কত পিস বদলে দিতে রাজি হয়েছেন তা লিখুন। চাইলে বদলি হিসেবে অন্য সাইজ বা রঙ দেওয়া যায়।" icon={Send}>
                {can.recordAgreement && <AgreementForm claim={claim} post={post} processing={processing} errors={errors} />}
                {rejectBox}
            </ActionCard>
        );
    }

    if (claim.status === 'supplier_agreed' && (can.approve || can.reject)) {
        return (
            <ActionCard title="চূড়ান্ত অনুমোদন" description="অনুমোদন মানে মাল পাঠানোর অনুমতি। এতে স্টক বদলায় না; মাল পাঠালে তবেই স্টক বদলাবে।" icon={ShieldCheck}>
                {can.approve && (
                    <div className="space-y-3">
                        <Textarea value={comment} onChange={(event) => setComment(event.target.value)} placeholder="অনুমোদনের নোট (ইচ্ছামতো)" rows={2} />
                        <Button disabled={processing} onClick={() => post(approve.url(claim.id), { comment })}>
                            <Check /> বদলি অনুমোদন করুন
                        </Button>
                    </div>
                )}
                {rejectBox}
            </ActionCard>
        );
    }

    if (claim.status === 'approved' && can.dispatch) {
        return <DispatchForm claim={claim} today={today} post={post} processing={processing} errors={errors} />;
    }

    if (['dispatched', 'partially_received'].includes(claim.status) && (can.receive || can.closeShort)) {
        return (
            <ActionCard title="বদলি মাল গ্রহণ করুন" description="আসলে যা হাতে এসেছে তা লিখুন। পাওয়া মাল সাথে সাথে বিক্রয়যোগ্য স্টকে যোগ হবে।" icon={PackageCheck}>
                {can.receive && <ReceiveForm claim={claim} today={today} post={post} processing={processing} errors={errors} />}
                {can.closeShort && <CloseShortForm claim={claim} post={post} processing={processing} errors={errors} />}
            </ActionCard>
        );
    }

    if (claim.status !== 'closed' && claim.status !== 'rejected') {
        return <p className="rounded-2xl border border-dashed px-5 py-4 text-sm text-muted-foreground">“{STEPS.find((step) => step.key === claim.status)?.label}” ধাপটি শেষ করার অনুমতি যার আছে, তার অপেক্ষায়।</p>;
    }

    return null;
}

function AgreementForm({ claim, post, processing, errors }: Omit<ActionProps, 'can' | 'today'>) {
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');
    const [lines, setLines] = useState(claim.lines.map((line) => ({ line_id: line.id, approved_quantity: String(line.claimed_quantity), replacement_variant_id: String(line.replacement_variant_id ?? '') })));
    const [options, setOptions] = useState<Record<number, VariantLookup>>({});

    useEffect(() => {
        const productIds = [...new Set(claim.lines.map((line) => line.product_id))];
        void Promise.all(productIds.map((id) => getJson<VariantLookup>(lookupVariants.url(id)).then((lookup) => [id, lookup] as const))).then((entries) => setOptions(Object.fromEntries(entries)));
    }, [claim.lines]);

    return (
        <div className="space-y-4">
            <div className="divide-y rounded-lg border">
                {claim.lines.map((line, index) => (
                    <div key={line.id} className="grid items-center gap-3 px-4 py-3 sm:grid-cols-[1fr_8rem_16rem]">
                        <div>
                            <p className="text-sm font-medium">
                                {line.product} / {line.variant}
                            </p>
                            <p className="text-xs text-muted-foreground">{qty(line.claimed_quantity)} পিস দাবি</p>
                            {errors[`lines.${index}.approved_quantity`] && <p className="text-xs text-damaged">{errors[`lines.${index}.approved_quantity`]}</p>}
                        </div>
                        <label className="space-y-1 text-xs text-muted-foreground">
                            রাজি হওয়া পরিমাণ
                            <Input value={lines[index].approved_quantity} onChange={(event) => setLines(lines.map((item, position) => (position === index ? { ...item, approved_quantity: onlyDigits(event.target.value) } : item)))} className="h-9 text-right font-semibold" />
                        </label>
                        <label className="space-y-1 text-xs text-muted-foreground">
                            বদলি ভ্যারিয়েন্ট
                            <Select value={lines[index].replacement_variant_id} onValueChange={(value) => setLines(lines.map((item, position) => (position === index ? { ...item, replacement_variant_id: value } : item)))}>
                                <SelectTrigger className="h-9">
                                    <SelectValue placeholder="একই ভ্যারিয়েন্ট" />
                                </SelectTrigger>
                                <SelectContent>
                                    {(options[line.product_id]?.variants ?? []).map((variant) => (
                                        <SelectItem key={variant.id} value={String(variant.id)}>
                                            {variant.name} {variant.sku === line.sku && '(একই)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </label>
                    </div>
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
                <Input value={reference} onChange={(event) => setReference(event.target.value)} placeholder="সরবরাহকারীর রেফারেন্স নম্বর" />
                <Input value={note} onChange={(event) => setNote(event.target.value)} placeholder="সরবরাহকারী কী বলেছেন" />
            </div>
            {errors.lines && <p className="text-sm text-damaged">{errors.lines}</p>}
            <Button
                disabled={processing}
                onClick={() =>
                    post(agreement.url(claim.id), {
                        supplier_reference: reference,
                        note,
                        lines: lines.map((line) => ({ ...line, approved_quantity: Number(line.approved_quantity || 0), replacement_variant_id: line.replacement_variant_id ? Number(line.replacement_variant_id) : null })),
                    })
                }
            >
                <Check /> সম্মতি সেভ করুন
            </Button>
        </div>
    );
}

function DispatchForm({ claim, today, post, processing, errors }: Omit<ActionProps, 'can'>) {
    const [date, setDate] = useState(today);
    const [notes, setNotes] = useState('');
    const units = claim.lines.reduce((sum, line) => sum + line.approved_quantity, 0);

    return (
        <ActionCard title="সরবরাহকারীর কাছে পাঠান" description={`${qty(units)} পিস ক্ষতিগ্রস্ত মাল পাঠালে সেগুলো ক্ষতিগ্রস্ত স্টক থেকে “সরবরাহকারীর কাছে” চলে যাবে এবং গুদামের মজুদে আর ধরা হবে না।`} icon={Truck}>
            <div className="grid gap-3 sm:grid-cols-[12rem_1fr_auto]">
                <Input type="date" value={date} max={today} onChange={(event) => setDate(event.target.value)} aria-label="পাঠানোর তারিখ" />
                <Input value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="কুরিয়ার, চালান নম্বর (ইচ্ছামতো)" />
                <Button disabled={processing} onClick={() => post(dispatch.url(claim.id), { dispatch_date: date, notes })}>
                    <Truck /> পাঠানো নিশ্চিত করুন
                </Button>
            </div>
            {errors.status && <p className="mt-2 text-sm text-damaged">{errors.status}</p>}
            {Object.entries(errors).find(([key]) => key.startsWith('lines'))?.[1] && <p className="mt-2 text-sm text-damaged">{Object.entries(errors).find(([key]) => key.startsWith('lines'))?.[1]}</p>}
        </ActionCard>
    );
}

function ReceiveForm({ claim, today, post, processing, errors }: Omit<ActionProps, 'can'>) {
    const open = claim.lines.filter((line) => line.outstanding_quantity > 0);
    const [quantities, setQuantities] = useState<Record<number, string>>({});
    const [date, setDate] = useState(today);
    const [notes, setNotes] = useState('');
    const [key, setKey] = useState(uuid);
    const total = open.reduce((sum, line) => sum + Number(quantities[line.id] || 0), 0);

    return (
        <div className="space-y-4">
            <div className="divide-y rounded-lg border">
                {open.map((line, index) => (
                    <div key={line.id} className="flex items-center justify-between gap-3 px-4 py-2.5">
                        <div className="text-sm">
                            <p className="font-medium">{line.replacement_differs ? line.replacement : `${line.product} / ${line.variant}`}</p>
                            <p className="text-xs text-muted-foreground">এখনো সরবরাহকারীর কাছে {qty(line.outstanding_quantity)} পিস</p>
                            {errors[`lines.${index}.quantity`] && <p className="text-xs text-damaged">{errors[`lines.${index}.quantity`]}</p>}
                        </div>
                        <Input value={quantities[line.id] ?? ''} placeholder="০" onChange={(event) => setQuantities({ ...quantities, [line.id]: onlyDigits(event.target.value) })} className={cn('h-9 w-24 text-right font-semibold', Number(quantities[line.id]) > line.outstanding_quantity && 'border-damaged text-damaged')} aria-label="পাওয়া পরিমাণ" />
                    </div>
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-[12rem_1fr_auto]">
                <Input type="date" value={date} max={today} onChange={(event) => setDate(event.target.value)} aria-label="গ্রহণের তারিখ" />
                <Input value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="নোট (ইচ্ছামতো)" />
                <Button
                    disabled={processing || total === 0}
                    onClick={() =>
                        post(receive.url(claim.id), { received_date: date, notes, idempotency_key: key, lines: open.map((line) => ({ line_id: line.id, quantity: Number(quantities[line.id] || 0) })) }, () => {
                            setQuantities({});
                            setKey(uuid());
                        })
                    }
                >
                    <PackageCheck /> {total ? `${qty(total)} পিস গ্রহণ করুন` : 'গ্রহণ করুন'}
                </Button>
            </div>
            {errors.lines && <p className="text-sm text-damaged">{errors.lines}</p>}
        </div>
    );
}

function CloseShortForm({ claim, post, processing, errors }: Omit<ActionProps, 'can' | 'today'>) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const outstanding = claim.lines.reduce((sum, line) => sum + line.outstanding_quantity, 0);

    return (
        <div className="mt-4 border-t pt-4">
            {open ? (
                <div className="space-y-2">
                    <p className="text-sm text-muted-foreground">সরবরাহকারীর কাছে থাকা বাকি {qty(outstanding)} পিস হিসাব থেকে বাদ দিয়ে দাবিটি বন্ধ হবে।</p>
                    <Textarea value={reason} onChange={(event) => setReason(event.target.value)} placeholder="এই মাল কেন আর ফেরত আসবে না?" rows={2} />
                    {errors.reason && <p className="text-sm text-damaged">{errors.reason}</p>}
                    <div className="flex gap-2">
                        <Button variant="destructive" size="sm" disabled={processing || !reason.trim()} onClick={() => post(closeShort.url(claim.id), { reason })}>
                            বাদ দিয়ে বন্ধ করুন
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => setOpen(false)}>
                            থাক
                        </Button>
                    </div>
                </div>
            ) : (
                <Button variant="ghost" size="sm" onClick={() => setOpen(true)}>
                    ঘাটতিসহ বন্ধ করুন
                </Button>
            )}
        </div>
    );
}

ClaimShow.layout = (props: { claim: Claim }) => ({
    breadcrumbs: [
        { title: 'বদলির দাবি', href: index() },
        { title: props.claim.claim_no, href: show(props.claim.id) },
    ],
});
