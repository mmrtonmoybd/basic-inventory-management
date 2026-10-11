import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, History, Link2, PackageOpen } from 'lucide-react';
import { useState } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { businessDate, dateTime, onlyDigits, qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as damageShow } from '@/routes/damage';
import { show as movementShow } from '@/routes/movements';
import { index, inspect, show } from '@/routes/returns';

type Line = { id: number; product: string; sku: string; variant: string; quantity: number; good_quantity: number; damaged_quantity: number; pending_quantity: number; reason: string | null };

type CustomerReturn = {
    id: number;
    return_no: string;
    return_date: string;
    customer_name: string | null;
    reference: string | null;
    reason: string | null;
    notes: string | null;
    status: string;
    status_label: string;
    warehouse: string;
    created_by: string | null;
    created_at: string | null;
    lines: Line[];
    inspections: { id: number; inspected_by: string | null; created_at: string | null; notes: string | null; movement: { id: number; reference_no: string } | null; lines: { sku: string; variant: string; good_quantity: number; damaged_quantity: number }[] }[];
    movements: { id: number; reference_no: string; type: string; business_date: string }[];
    damage_cases: { id: number; case_no: string; status: string }[];
};

export default function ReturnShow({ customerReturn }: { customerReturn: CustomerReturn }) {
    const can = useCan();
    const pending = customerReturn.lines.reduce((sum, line) => sum + line.pending_quantity, 0);
    const [results, setResults] = useState<Record<number, { good: string; damaged: string }>>({});
    const [notes, setNotes] = useState('');
    const [damageReason, setDamageReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const lines = customerReturn.lines.filter((line) => line.pending_quantity > 0);
    const value = (id: number) => results[id] ?? { good: '', damaged: '' };
    const totalDamaged = lines.reduce((sum, line) => sum + Number(value(line.id).damaged || 0), 0);
    const totalEntered = lines.reduce((sum, line) => sum + Number(value(line.id).good || 0) + Number(value(line.id).damaged || 0), 0);
    const over = lines.some((line) => Number(value(line.id).good || 0) + Number(value(line.id).damaged || 0) > line.pending_quantity);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const payload = lines.map((line) => ({ return_line_id: line.id, good_quantity: Number(value(line.id).good || 0), damaged_quantity: Number(value(line.id).damaged || 0) }));
        router.post(
            inspect.url(customerReturn.id),
            { notes, damage_reason: damageReason, lines: payload },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: () => {
                    setResults({});
                    setNotes('');
                    setErrors({});
                },
            },
        );
    };

    const passAll = () => setResults(Object.fromEntries(lines.map((line) => [line.id, { good: String(line.pending_quantity), damaged: '0' }])));

    return (
        <>
            <Head title={customerReturn.return_no} />
            <Page className="max-w-6xl">
                <PageHeader title={customerReturn.return_no} meta={<StatusBadge status={customerReturn.status} label={customerReturn.status_label} />} />

                <Panel>
                    <dl className="grid gap-5 sm:grid-cols-3 lg:grid-cols-6">
                        <Field label="রিটার্নের তারিখ" value={businessDate(customerReturn.return_date, 'long')} />
                        <Field label="কাস্টমার" value={customerReturn.customer_name} />
                        <Field label="অর্ডার নম্বর" value={customerReturn.reference} />
                        <Field label="কারণ" value={customerReturn.reason} />
                        <Field label="গুদাম" value={customerReturn.warehouse} />
                        <Field label="কে লিখেছেন" value={`${customerReturn.created_by ?? 'সিস্টেম'}, ${dateTime(customerReturn.created_at)}`} />
                    </dl>
                    {customerReturn.notes && <p className="mt-4 border-t pt-4 text-sm text-muted-foreground">{customerReturn.notes}</p>}
                </Panel>

                <Panel title="ফেরত আসা ভ্যারিয়েন্ট" icon={PackageOpen} tone="amber" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>পণ্য</TableHead>
                                <TableHead>ভ্যারিয়েন্ট</TableHead>
                                <TableHead>কারণ</TableHead>
                                <TableHead className="text-right">ফেরত</TableHead>
                                <TableHead className="text-right">ভালো</TableHead>
                                <TableHead className="text-right">ক্ষতিগ্রস্ত</TableHead>
                                <TableHead className="text-right">পরীক্ষা বাকি</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customerReturn.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell className="font-medium">{line.product}</TableCell>
                                    <TableCell>
                                        {line.variant} <span className="text-xs text-muted-foreground">{line.sku}</span>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">{line.reason ?? '—'}</TableCell>
                                    <TableCell className="text-right font-semibold tabular-nums">{qty(line.quantity)}</TableCell>
                                    <TableCell className="text-right text-available tabular-nums">{qty(line.good_quantity)}</TableCell>
                                    <TableCell className="text-right text-damaged tabular-nums">{qty(line.damaged_quantity)}</TableCell>
                                    <TableCell className="text-right font-semibold text-inspection-ink tabular-nums">{qty(line.pending_quantity)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>

                {pending > 0 && can('returns.inspect') && (
                    <Panel
                        title="পরীক্ষার ফলাফল লিখুন"
                        description="প্রতিটি লাইনে কত পিস ভালো (বিক্রয়যোগ্য স্টকে যাবে) আর কত পিস খারাপ (ক্ষতিগ্রস্ত স্টকে যাবে) লিখুন। চাইলে এখন কিছু আর পরে বাকিগুলো পরীক্ষা করতে পারেন।"
                        icon={ClipboardCheck}
                        tone="teal"
                        actions={
                            <Button type="button" variant="outline" size="sm" onClick={passAll}>
                                সব ভালো হিসেবে ধরুন
                            </Button>
                        }
                    >
                        <form onSubmit={submit} className="space-y-5">
                            <div className="divide-y rounded-lg border">
                                {lines.map((line, index) => {
                                    const entered = Number(value(line.id).good || 0) + Number(value(line.id).damaged || 0);
                                    const error = errors[`lines.${index}.good_quantity`] ?? errors[`lines.${index}.damaged_quantity`];

                                    return (
                                        <div key={line.id} className="grid items-center gap-3 px-4 py-3 sm:grid-cols-[1fr_7rem_7rem_6rem]">
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {line.product} / {line.variant}
                                                </p>
                                                <p className="text-xs text-muted-foreground">{qty(line.pending_quantity)} পিস পরীক্ষা বাকি</p>
                                                {error && <p className="text-xs text-damaged">{error}</p>}
                                            </div>
                                            <label className="space-y-1 text-xs text-muted-foreground">
                                                ভালো
                                                <Input inputMode="numeric" value={value(line.id).good} onChange={(event) => setResults({ ...results, [line.id]: { ...value(line.id), good: onlyDigits(event.target.value) } })} className="h-9 text-right font-semibold" placeholder="০" />
                                            </label>
                                            <label className="space-y-1 text-xs text-muted-foreground">
                                                ক্ষতিগ্রস্ত
                                                <Input inputMode="numeric" value={value(line.id).damaged} onChange={(event) => setResults({ ...results, [line.id]: { ...value(line.id), damaged: onlyDigits(event.target.value) } })} className="h-9 text-right font-semibold" placeholder="০" />
                                            </label>
                                            <p className={cn('text-right text-sm tabular-nums', entered > line.pending_quantity ? 'font-semibold text-damaged' : 'text-muted-foreground')}>
                                                {qty(entered)} / {qty(line.pending_quantity)}
                                            </p>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {totalDamaged > 0 && (
                                    <div className="space-y-2">
                                        <Label htmlFor="damage_reason">ক্ষতির কারণ</Label>
                                        <Input id="damage_reason" value={damageReason} onChange={(event) => setDamageReason(event.target.value)} placeholder="সেলাই ছেঁড়া, দাগ…" />
                                        <p className="text-xs text-muted-foreground">ক্ষতিগ্রস্ত মালের জন্য একটি ক্ষতির কেস খুলবে, যা দিয়ে সরবরাহকারীর কাছে বদলি চাইতে পারবেন।</p>
                                    </div>
                                )}
                                <div className="space-y-2">
                                    <Label htmlFor="inspection_notes">নোট</Label>
                                    <Textarea id="inspection_notes" value={notes} onChange={(event) => setNotes(event.target.value)} rows={2} />
                                </div>
                            </div>
                            {errors.lines && <p className="text-sm text-damaged">{errors.lines}</p>}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing || totalEntered === 0 || over}>
                                    <ClipboardCheck /> {processing ? 'সেভ হচ্ছে…' : `${qty(totalEntered)} পিসের পরীক্ষা সেভ করুন`}
                                </Button>
                            </div>
                        </form>
                    </Panel>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="পরীক্ষার ইতিহাস" icon={History} tone="sky" bodyClassName="p-0">
                        {customerReturn.inspections.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-muted-foreground">এখনো পরীক্ষা হয়নি।</p>
                        ) : (
                            <ul className="divide-y">
                                {customerReturn.inspections.map((inspection) => (
                                    <li key={inspection.id} className="space-y-1.5 px-5 py-3">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">{inspection.inspected_by}</span>
                                            <span className="text-muted-foreground">{dateTime(inspection.created_at)}</span>
                                        </div>
                                        <ul className="text-sm text-muted-foreground">
                                            {inspection.lines.map((line, index) => (
                                                <li key={index}>
                                                    {line.variant}: <span className="text-available">{qty(line.good_quantity)} ভালো</span>, <span className="text-damaged">{qty(line.damaged_quantity)} ক্ষতিগ্রস্ত</span>
                                                </li>
                                            ))}
                                        </ul>
                                        {inspection.movement && (
                                            <Link href={movementShow.url(inspection.movement.id)} className="text-xs font-medium text-primary hover:underline">
                                                {inspection.movement.reference_no}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    <Panel title="সংযুক্ত রেকর্ড" icon={Link2} tone="slate" bodyClassName="p-0">
                        <ul className="divide-y text-sm">
                            {customerReturn.movements.map((movement) => (
                                <li key={movement.id} className="flex justify-between px-5 py-3">
                                    <Link href={movementShow.url(movement.id)} className="font-medium text-primary hover:underline">
                                        {movement.reference_no}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        {movement.type}, {businessDate(movement.business_date)}
                                    </span>
                                </li>
                            ))}
                            {customerReturn.damage_cases.map((damageCase) => (
                                <li key={damageCase.id} className="flex justify-between px-5 py-3">
                                    <Link href={damageShow.url(damageCase.id)} className="font-medium text-primary hover:underline">
                                        {damageCase.case_no}
                                    </Link>
                                    <span className="text-muted-foreground">ক্ষতির কেস, {damageCase.status}</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>
            </Page>
        </>
    );
}

ReturnShow.layout = (props: { customerReturn: CustomerReturn }) => ({
    breadcrumbs: [
        { title: 'কাস্টমার রিটার্ন', href: index() },
        { title: props.customerReturn.return_no, href: show(props.customerReturn.id) },
    ],
});
