import { Head, router } from '@inertiajs/react';
import { Calculator, FileText, Scale, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { ProductPicker } from '@/components/inventory/product-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { onlyDigits, qty, signedQty, uuid } from '@/lib/format';
import { getJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { create, index, store } from '@/routes/adjustments';
import { variants as lookupVariants } from '@/routes/lookup';
import type { Option, VariantLookup } from '@/types';

type CountCondition = 'available' | 'inspection' | 'damaged';

export default function AdjustmentCreate({ warehouses, today }: { warehouses: Option[]; today: string }) {
    const warehouseId = (warehouses.find((warehouse) => warehouse.is_default) ?? warehouses[0])?.id;
    const [products, setProducts] = useState<VariantLookup[]>([]);
    const [condition, setCondition] = useState<CountCondition>('available');
    const [counts, setCounts] = useState<Record<number, string>>({});
    const [details, setDetails] = useState({ count_date: today, reason: '', notes: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [key] = useState(uuid);

    const variants = products.flatMap((product) => product.variants.map((variant) => ({ ...variant, product: product.product.name })));
    const lines = variants.filter((variant) => counts[variant.id] !== undefined && counts[variant.id] !== '');
    const differences = lines.map((variant) => Number(counts[variant.id]) - (variant.stock?.[condition] ?? 0));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(
            store.url(),
            { ...details, warehouse_id: warehouseId, idempotency_key: key, lines: lines.map((variant) => ({ variant_id: variant.id, condition, counted_quantity: Number(counts[variant.id]) })) },
            { onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors },
        );
    };

    return (
        <>
            <Head title="নতুন গণনা" />
            <Page className="max-w-6xl">
                <PageHeader title="নতুন স্টক গণনা" description="হাতে গুনে যা পেয়েছেন তা লিখুন। শুধু যেগুলো সিস্টেমের সাথে মেলে না সেগুলোই সমন্বয়ে যাবে, আর অন্য কেউ অনুমোদন না দেওয়া পর্যন্ত স্টক বদলাবে না।" icon={Scale} tone="sky" />
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Panel
                        title="গুনে পাওয়া পরিমাণ"
                        icon={Calculator}
                        tone="sky"
                        className="order-2 lg:order-1"
                        actions={
                            <Select value={condition} onValueChange={(value) => { setCondition(value as CountCondition); setCounts({}); }}>
                                <SelectTrigger className="h-8 w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="available">বিক্রয়যোগ্য স্টক</SelectItem>
                                    <SelectItem value="inspection">পরীক্ষাধীন স্টক</SelectItem>
                                    <SelectItem value="damaged">ক্ষতিগ্রস্ত স্টক</SelectItem>
                                </SelectContent>
                            </Select>
                        }
                    >
                        <div className="space-y-4">
                            <ProductPicker
                                excludeIds={products.map((product) => product.product.id)}
                                onSelect={async (product) => {
                                    const lookup = await getJson<VariantLookup>(lookupVariants.url(product.id, { query: { warehouse_id: warehouseId } }));
                                    setProducts((current) => [...current, lookup]);
                                }}
                            />
                            {products.map((product) => (
                                <section key={product.product.id} className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-semibold">{product.product.name}</h3>
                                        <Button type="button" variant="ghost" size="icon" aria-label="পণ্য সরান" onClick={() => setProducts(products.filter((item) => item.product.id !== product.product.id))}>
                                            <X className="size-4" />
                                        </Button>
                                    </div>
                                    <div className="divide-y rounded-lg border">
                                        <div className="grid grid-cols-[1fr_5rem_7rem_5rem] gap-3 px-4 py-2 text-xs font-semibold text-muted-foreground">
                                            <span>ভ্যারিয়েন্ট</span>
                                            <span className="text-right">সিস্টেমে</span>
                                            <span className="text-right">গুনে পাওয়া</span>
                                            <span className="text-right">পার্থক্য</span>
                                        </div>
                                        {product.variants.map((variant) => {
                                            const system = variant.stock?.[condition] ?? 0;
                                            const counted = counts[variant.id];
                                            const difference = counted === undefined || counted === '' ? null : Number(counted) - system;

                                            return (
                                                <div key={variant.id} className="grid grid-cols-[1fr_5rem_7rem_5rem] items-center gap-3 px-4 py-2">
                                                    <span className="text-sm">
                                                        {variant.name} <span className="text-xs text-muted-foreground">{variant.sku}</span>
                                                    </span>
                                                    <span className="text-right text-sm tabular-nums">{qty(system)}</span>
                                                    <Input inputMode="numeric" value={counted ?? ''} placeholder="—" onChange={(event) => setCounts({ ...counts, [variant.id]: onlyDigits(event.target.value) })} className="h-8 text-right font-semibold" aria-label={`${variant.name} গুনে পাওয়া`} />
                                                    <span className={cn('text-right text-sm font-semibold tabular-nums', difference === null ? 'text-muted-foreground' : difference < 0 ? 'text-damaged' : difference > 0 ? 'text-available' : 'text-muted-foreground')}>
                                                        {difference === null ? '' : signedQty(difference)}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </section>
                            ))}
                            {errors.lines && <p className="text-sm text-damaged">{errors.lines}</p>}
                        </div>
                    </Panel>
                    <Panel title="গণনার তথ্য" icon={FileText} tone="slate" className="order-1 h-fit lg:order-2 lg:sticky lg:top-20">
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="count_date">গণনার তারিখ</Label>
                                <Input id="count_date" type="date" value={details.count_date} max={today} onChange={(event) => setDetails({ ...details, count_date: event.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="reason">কারণ</Label>
                                <Input id="reason" value={details.reason} onChange={(event) => setDetails({ ...details, reason: event.target.value })} placeholder="মাসিক গণনা" />
                                <InputError message={errors.reason} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="notes">নোট</Label>
                                <Textarea id="notes" value={details.notes} onChange={(event) => setDetails({ ...details, notes: event.target.value })} rows={3} />
                            </div>
                            <div className="rounded-lg bg-muted/60 p-3 text-sm">
                                <p>
                                    {qty(differences.filter((difference) => difference !== 0).length)}টি পার্থক্য, মোট{' '}
                                    <span className="font-semibold">{signedQty(differences.reduce((sum, difference) => sum + difference, 0))}</span>
                                </p>
                            </div>
                            <Button type="submit" className="w-full" disabled={processing || !details.reason || differences.every((difference) => difference === 0)}>
                                <Scale /> অনুমোদনের জন্য পাঠান
                            </Button>
                        </div>
                    </Panel>
                </form>
            </Page>
        </>
    );
}

AdjustmentCreate.layout = {
    breadcrumbs: [
        { title: 'স্টক সমন্বয়', href: index() },
        { title: 'নতুন গণনা', href: create() },
    ],
};
