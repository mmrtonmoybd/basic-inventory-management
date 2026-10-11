import { Head, Link, router } from '@inertiajs/react';
import { Camera, PackageX, Repeat } from 'lucide-react';
import { useState } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { businessDate, dateTime, onlyDigits, qty } from '@/lib/format';
import { show as claimShow } from '@/routes/claims';
import { index, show } from '@/routes/damage';
import caseClaims from '@/routes/damage/claims';
import { show as movementShow } from '@/routes/movements';
import { show as returnShow } from '@/routes/returns';
import type { Option } from '@/types';

type DamageCase = {
    id: number;
    case_no: string;
    damage_date: string;
    source: string;
    reason: string;
    internal_note: string | null;
    supplier_message: string | null;
    supplier_id: number | null;
    supplier: string | null;
    status: string;
    status_label: string;
    reported_by: string | null;
    created_at: string | null;
    stock_movement_id: number | null;
    customer_return: { id: number; return_no: string } | null;
    lines: { id: number; product: string; sku: string; variant: string; quantity: number; claimed_quantity: number; unclaimed_quantity: number }[];
    images: { id: number; url: string; name: string }[];
    claims: { id: number; claim_no: string; supplier: string; status: string; status_label: string }[];
};

export default function DamageShow({ damageCase, suppliers }: { damageCase: DamageCase; suppliers: Option[] }) {
    const can = useCan();
    const unclaimed = damageCase.lines.reduce((sum, line) => sum + line.unclaimed_quantity, 0);
    const [supplierId, setSupplierId] = useState(damageCase.supplier_id ? String(damageCase.supplier_id) : '');
    const [message, setMessage] = useState(damageCase.supplier_message ?? '');
    const [quantities, setQuantities] = useState<Record<number, string>>(Object.fromEntries(damageCase.lines.map((line) => [line.id, String(line.unclaimed_quantity)])));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submitClaim = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(
            caseClaims.store.url(damageCase.id),
            { supplier_id: supplierId, supplier_message: message, lines: damageCase.lines.map((line) => ({ damage_case_line_id: line.id, quantity: Number(quantities[line.id] || 0) })) },
            { onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors },
        );
    };

    return (
        <>
            <Head title={damageCase.case_no} />
            <Page className="max-w-6xl">
                <PageHeader title={damageCase.case_no} description={damageCase.reason} meta={<StatusBadge status={damageCase.status} label={damageCase.status_label} />} />

                <Panel>
                    <dl className="grid gap-5 sm:grid-cols-3 lg:grid-cols-5">
                        <Field label="কবে পাওয়া গেছে" value={businessDate(damageCase.damage_date, 'long')} />
                        <Field
                            label="কোথায় পাওয়া"
                            value={
                                damageCase.customer_return ? (
                                    <Link href={returnShow.url(damageCase.customer_return.id)} className="text-primary hover:underline">
                                        রিটার্ন {damageCase.customer_return.return_no}
                                    </Link>
                                ) : (
                                    'গুদাম'
                                )
                            }
                        />
                        <Field label="সরবরাহকারী" value={damageCase.supplier} />
                        <Field label="কে জানিয়েছেন" value={`${damageCase.reported_by ?? 'সিস্টেম'}, ${dateTime(damageCase.created_at)}`} />
                        <Field
                            label="স্টক লেনদেন"
                            value={
                                damageCase.stock_movement_id ? (
                                    <Link href={movementShow.url(damageCase.stock_movement_id)} className="text-primary hover:underline">
                                        লেনদেন দেখুন
                                    </Link>
                                ) : null
                            }
                        />
                    </dl>
                    {(damageCase.internal_note || damageCase.supplier_message) && (
                        <div className="mt-5 grid gap-4 border-t pt-4 text-sm sm:grid-cols-2">
                            {damageCase.internal_note && (
                                <div>
                                    <p className="text-xs text-muted-foreground">অভ্যন্তরীণ নোট</p>
                                    <p>{damageCase.internal_note}</p>
                                </div>
                            )}
                            {damageCase.supplier_message && (
                                <div>
                                    <p className="text-xs text-muted-foreground">সরবরাহকারীকে বার্তা</p>
                                    <p>{damageCase.supplier_message}</p>
                                </div>
                            )}
                        </div>
                    )}
                </Panel>

                <Panel title="ক্ষতিগ্রস্ত ভ্যারিয়েন্ট" icon={PackageX} tone="rose" bodyClassName="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>পণ্য</TableHead>
                                <TableHead>ভ্যারিয়েন্ট</TableHead>
                                <TableHead className="text-right">ক্ষতিগ্রস্ত</TableHead>
                                <TableHead className="text-right">দাবি করা</TableHead>
                                <TableHead className="text-right">দাবি হয়নি</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {damageCase.lines.map((line) => (
                                <TableRow key={line.id}>
                                    <TableCell className="font-medium">{line.product}</TableCell>
                                    <TableCell>
                                        {line.variant} <span className="text-xs text-muted-foreground">{line.sku}</span>
                                    </TableCell>
                                    <TableCell className="text-right font-semibold text-damaged tabular-nums">{qty(line.quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.claimed_quantity)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(line.unclaimed_quantity)}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Panel>

                {unclaimed > 0 && can('damage.create') && (
                    <Panel title="সরবরাহকারীর কাছে বদলি চান" description="অনুরোধটি ম্যানেজারের কাছে যাচাইয়ের জন্য যাবে। অনুমোদনে স্টক বদলায় না; মাল সরবরাহকারীর কাছে পাঠালে তবেই স্টক বদলাবে।" icon={Repeat} tone="violet">
                        <form onSubmit={submitClaim} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>সরবরাহকারী</Label>
                                    <Select value={supplierId || undefined} onValueChange={setSupplierId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="সরবরাহকারী বাছুন" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {suppliers.map((supplier) => (
                                                <SelectItem key={supplier.id} value={String(supplier.id)}>
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.supplier_id && <p className="text-sm text-damaged">{errors.supplier_id}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="message">সরবরাহকারীকে বার্তা</Label>
                                    <Textarea id="message" value={message} onChange={(event) => setMessage(event.target.value)} rows={2} />
                                </div>
                            </div>
                            <div className="divide-y rounded-lg border">
                                {damageCase.lines
                                    .filter((line) => line.unclaimed_quantity > 0)
                                    .map((line) => (
                                        <div key={line.id} className="flex items-center justify-between gap-3 px-4 py-2.5">
                                            <span className="text-sm">
                                                {line.product} / {line.variant} <span className="text-muted-foreground">({qty(line.unclaimed_quantity)} পিস দাবি করা যাবে)</span>
                                            </span>
                                            <Input value={quantities[line.id] ?? ''} onChange={(event) => setQuantities({ ...quantities, [line.id]: onlyDigits(event.target.value) })} className="h-8 w-24 text-right" aria-label={`${line.variant} এর দাবির পরিমাণ`} />
                                        </div>
                                    ))}
                            </div>
                            {(errors.lines || Object.entries(errors).find(([key]) => key.startsWith('lines.'))?.[1]) && <p className="text-sm text-damaged">{errors.lines ?? Object.entries(errors).find(([key]) => key.startsWith('lines.'))?.[1]}</p>}
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing || !supplierId}>
                                    <Repeat /> বদলির অনুরোধ পাঠান
                                </Button>
                            </div>
                        </form>
                    </Panel>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="বদলির দাবি" icon={Repeat} tone="violet" bodyClassName="p-0">
                        {damageCase.claims.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-muted-foreground">এখনো বদলি চাওয়া হয়নি।</p>
                        ) : (
                            <ul className="divide-y">
                                {damageCase.claims.map((claim) => (
                                    <li key={claim.id} className="flex items-center justify-between px-5 py-3 text-sm">
                                        <Link href={claimShow.url(claim.id)} className="font-medium text-primary hover:underline">
                                            {claim.claim_no}
                                        </Link>
                                        <span className="flex items-center gap-3 text-muted-foreground">
                                            {claim.supplier}
                                            <StatusBadge status={claim.status} label={claim.status_label} />
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                    <Panel title="প্রমাণের ছবি" icon={Camera} tone="sky">
                        {damageCase.images.length === 0 ? (
                            <p className="py-4 text-center text-sm text-muted-foreground">কোনো ছবি দেওয়া হয়নি।</p>
                        ) : (
                            <div className="grid grid-cols-3 gap-3">
                                {damageCase.images.map((image) => (
                                    <a key={image.id} href={image.url} target="_blank" rel="noreferrer" className="aspect-square overflow-hidden rounded-lg border">
                                        <img src={image.url} alt={image.name} className="size-full object-cover" />
                                    </a>
                                ))}
                            </div>
                        )}
                    </Panel>
                </div>
            </Page>
        </>
    );
}

DamageShow.layout = (props: { damageCase: DamageCase }) => ({
    breadcrumbs: [
        { title: 'ক্ষতিগ্রস্ত মাল', href: index() },
        { title: props.damageCase.case_no, href: show(props.damageCase.id) },
    ],
});
