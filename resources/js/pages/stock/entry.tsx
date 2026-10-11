import { Head, router } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Boxes, FileText, PackagePlus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { MultiProductEntry, useMultiProductEntry } from '@/components/inventory/multi-product-entry';
import { Page, PageHeader, Panel, type Tone } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { qty, uuid } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, store } from '@/routes/stock';
import type { Option } from '@/types';

type Mode = 'in' | 'out' | 'opening';

const COPY: Record<Mode, { title: string; description: string; submit: string; icon: typeof ArrowDownToLine; tone: Tone }> = {
    in: { title: 'স্টক ইন (মাল গ্রহণ)', description: 'নতুন মাল এলে এখানে লিখুন। যে সাইজ-রঙ যত পিস এসেছে, প্রতিটির ঘরে পরিমাণ দিন। মাল সরাসরি বিক্রয়যোগ্য স্টকে যোগ হবে।', submit: 'স্টক ইন সেভ করুন', icon: ArrowDownToLine, tone: 'teal' },
    out: { title: 'স্টক আউট (মাল বের)', description: 'বিক্রি বা পাঠানোর জন্য মাল বের করলে এখানে লিখুন। শুধু বিক্রয়যোগ্য স্টক থেকে বের করা যায়; ক্ষতিগ্রস্ত বা পরীক্ষাধীন মাল বের হবে না।', submit: 'স্টক আউট সেভ করুন', icon: ArrowUpFromLine, tone: 'indigo' },
    opening: { title: 'শুরুর মজুদ', description: 'সিস্টেম চালুর দিনে গুদামে যা মাল আছে তা এখানে লিখুন। রিপোর্টে এটি সাধারণ স্টক ইন থেকে আলাদা দেখাবে।', submit: 'শুরুর মজুদ সেভ করুন', icon: Boxes, tone: 'violet' },
};

type Props = { mode: Mode; title: string; warehouses: Option[]; suppliers: Option[]; today: string };

export default function StockEntry({ mode, warehouses, suppliers, today }: Props) {
    const [warehouseId, setWarehouseId] = useState<number>((warehouses.find((warehouse) => warehouse.is_default) ?? warehouses[0])?.id);
    const [details, setDetails] = useState({ business_date: today, supplier_id: '', reference: '', notes: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [idempotencyKey] = useState(uuid);
    const entry = useMultiProductEntry(warehouseId);
    const copy = COPY[mode];

    const overdrawn = mode === 'out' && entry.products.some((item) => item.variants.some((variant) => Number(entry.quantities[variant.id]) > (variant.stock?.available ?? 0)));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (processing) {
            return;
        }

        router.post(
            store.url(mode),
            {
                ...details,
                supplier_id: details.supplier_id || null,
                warehouse_id: warehouseId,
                idempotency_key: idempotencyKey,
                lines: entry.lines,
            },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
            },
        );
    };

    return (
        <>
            <Head title={copy.title} />
            <Page className="max-w-6xl pb-28">
                <PageHeader title={copy.title} description={copy.description} icon={copy.icon} tone={copy.tone} />

                <form id="stock-entry" onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Panel title="পণ্য ও পরিমাণ" icon={PackagePlus} tone={copy.tone} className="order-2 lg:order-1">
                        <MultiProductEntry entry={entry} mode={mode === 'out' ? 'out' : 'in'} errors={errors} stockLabel="মজুদ" />
                    </Panel>

                    <Panel title="অন্যান্য তথ্য" icon={FileText} tone="slate" className="order-1 h-fit lg:order-2 lg:sticky lg:top-20">
                        <div className="space-y-4">
                            {warehouses.length > 1 && (
                                <div className="space-y-2">
                                    <Label>গুদাম</Label>
                                    <Select value={String(warehouseId)} onValueChange={(value) => setWarehouseId(Number(value))}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {warehouses.map((warehouse) => (
                                                <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                                                    {warehouse.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="business_date">তারিখ</Label>
                                <Input id="business_date" type="date" value={details.business_date} max={today} onChange={(event) => setDetails({ ...details, business_date: event.target.value })} />
                                <InputError message={errors.business_date} />
                            </div>
                            {mode !== 'out' && (
                                <div className="space-y-2">
                                    <Label>সরবরাহকারী</Label>
                                    <Select value={details.supplier_id || 'none'} onValueChange={(value) => setDetails({ ...details, supplier_id: value === 'none' ? '' : value })}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">সরবরাহকারী নেই</SelectItem>
                                            {suppliers.map((supplier) => (
                                                <SelectItem key={supplier.id} value={String(supplier.id)}>
                                                    {supplier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            <div className="space-y-2">
                                <Label htmlFor="reference">{mode === 'out' ? 'অর্ডার বা চালান নম্বর' : 'ক্রয় অর্ডার বা চালান নম্বর'}</Label>
                                <Input id="reference" value={details.reference} onChange={(event) => setDetails({ ...details, reference: event.target.value })} placeholder="ইচ্ছামতো" />
                                <InputError message={errors.reference} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="notes">নোট</Label>
                                <Textarea id="notes" value={details.notes} onChange={(event) => setDetails({ ...details, notes: event.target.value })} rows={3} placeholder="ইচ্ছামতো" />
                            </div>
                        </div>
                    </Panel>
                </form>
            </Page>

            <div className="sticky bottom-0 z-20 border-t bg-background/85 shadow-[0_-8px_24px_-12px_rgb(67_56_202/0.25)] backdrop-blur-xl">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-8">
                    <p className="text-sm text-muted-foreground">
                        <span className={cn('mr-1 text-2xl font-bold transition-all', mode === 'out' ? 'text-stock-out' : 'text-available')}>
                            {mode === 'out' ? '−' : '+'}
                            {qty(entry.total)}
                        </span>
                        পিস, {qty(entry.lines.length)}টি ভ্যারিয়েন্টে
                        {overdrawn && <span className="ml-3 font-semibold text-damaged">কিছু পরিমাণ মজুদের চেয়ে বেশি দেওয়া হয়েছে।</span>}
                    </p>
                    <Button type="submit" form="stock-entry" size="lg" disabled={processing || entry.total === 0 || overdrawn}>
                        <copy.icon />
                        {processing ? 'সেভ হচ্ছে…' : copy.submit}
                    </Button>
                </div>
            </div>
        </>
    );
}

StockEntry.layout = (props: Props) => ({ breadcrumbs: [{ title: COPY[props.mode].title, href: create(props.mode) }] });
