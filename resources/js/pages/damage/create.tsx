import { Head, router } from '@inertiajs/react';
import { Camera, FileText, ImagePlus, PackageX, Repeat, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { MultiProductEntry, useMultiProductEntry } from '@/components/inventory/multi-product-entry';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { qty, uuid } from '@/lib/format';
import { create, index, store } from '@/routes/damage';
import type { Option } from '@/types';

export default function DamageCreate({ warehouses, suppliers, today }: { warehouses: Option[]; suppliers: Option[]; today: string }) {
    const warehouseId = (warehouses.find((warehouse) => warehouse.is_default) ?? warehouses[0])?.id;
    const [details, setDetails] = useState({ damage_date: today, reason: '', internal_note: '', supplier_message: '', supplier_id: '', request_exchange: true });
    const [images, setImages] = useState<File[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [idempotencyKey] = useState(uuid);
    const entry = useMultiProductEntry(warehouseId);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(
            store.url(),
            {
                ...details,
                request_exchange: details.request_exchange && Boolean(details.supplier_id),
                supplier_id: details.supplier_id || null,
                warehouse_id: warehouseId,
                idempotency_key: idempotencyKey,
                lines: entry.lines,
                images,
            },
            { forceFormData: images.length > 0, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors },
        );
    };

    const overdrawn = entry.products.some((item) => item.variants.some((variant) => Number(entry.quantities[variant.id]) > (variant.stock?.available ?? 0)));

    return (
        <>
            <Head title="ক্ষতিগ্রস্ত মাল লিখুন" />
            <Page className="max-w-6xl pb-28">
                <PageHeader title="ক্ষতিগ্রস্ত মাল লিখুন" description="প্রতিটি ক্ষতিগ্রস্ত মালের ঠিক সাইজ ও রং বেছে নিন। মালগুলো সাথে সাথে বিক্রয়যোগ্য স্টক থেকে ক্ষতিগ্রস্ত স্টকে চলে যাবে।" icon={PackageX} tone="rose" />
                <form id="damage-form" onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="order-2 space-y-6 lg:order-1">
                        <Panel title="ক্ষতিগ্রস্ত মাল" icon={PackageX} tone="rose">
                            <MultiProductEntry entry={entry} mode="out" errors={errors} stockLabel="বিক্রয়যোগ্য" />
                        </Panel>
                        <Panel title="প্রমাণের ছবি" description="ইচ্ছামতো। সর্বোচ্চ ৬টি ছবি, প্রতিটি ৫ মেগাবাইট পর্যন্ত (JPG, PNG, WebP)।" icon={Camera} tone="sky">
                            <div className="flex flex-wrap gap-3">
                                {images.map((image, index) => (
                                    <div key={index} className="relative size-24 overflow-hidden rounded-lg border">
                                        <img src={URL.createObjectURL(image)} alt={image.name} className="size-full object-cover" />
                                        <button type="button" onClick={() => setImages(images.filter((_, position) => position !== index))} className="absolute top-1 right-1 rounded-full bg-black/60 p-0.5 text-white" aria-label={`${image.name} সরান`}>
                                            <X className="size-3.5" />
                                        </button>
                                    </div>
                                ))}
                                {images.length < 6 && (
                                    <label className="flex size-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-xs text-muted-foreground hover:bg-muted">
                                        <ImagePlus className="size-5" />
                                        ছবি যোগ করুন
                                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={(event) => setImages([...images, ...Array.from(event.target.files ?? [])].slice(0, 6))} />
                                    </label>
                                )}
                            </div>
                            {Object.entries(errors)
                                .filter(([key]) => key.startsWith('images'))
                                .map(([key, message]) => (
                                    <p key={key} className="mt-2 text-sm text-damaged">
                                        {message}
                                    </p>
                                ))}
                        </Panel>
                    </div>
                    <div className="order-1 space-y-6 lg:order-2">
                        <Panel title="ক্ষতির তথ্য" icon={FileText} tone="slate" className="h-fit">
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="damage_date">কবে পাওয়া গেছে</Label>
                                    <Input id="damage_date" type="date" value={details.damage_date} max={today} onChange={(event) => setDetails({ ...details, damage_date: event.target.value })} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="reason">ক্ষতির কারণ</Label>
                                    <Input id="reason" value={details.reason} onChange={(event) => setDetails({ ...details, reason: event.target.value })} placeholder="সেলাই ছেঁড়া, রং উঠে যাওয়া, দাগ…" />
                                    <InputError message={errors.reason} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="internal_note">অভ্যন্তরীণ নোট</Label>
                                    <Textarea id="internal_note" value={details.internal_note} onChange={(event) => setDetails({ ...details, internal_note: event.target.value })} rows={2} />
                                </div>
                            </div>
                        </Panel>
                        <Panel title="সরবরাহকারীর কাছে বদলি" icon={Repeat} tone="violet" className="h-fit">
                            <div className="space-y-4">
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
                                    <InputError message={errors.supplier_id} />
                                </div>
                                {details.supplier_id && (
                                    <>
                                        <label className="flex items-start justify-between gap-3 text-sm">
                                            <span>
                                                <span className="font-medium">বদলির অনুরোধ করুন</span>
                                                <span className="block text-xs text-muted-foreground">একটি দাবি তৈরি হবে, যা ম্যানেজার যাচাই করবেন।</span>
                                            </span>
                                            <Switch checked={details.request_exchange} onCheckedChange={(checked) => setDetails({ ...details, request_exchange: checked })} />
                                        </label>
                                        <div className="space-y-2">
                                            <Label htmlFor="supplier_message">সরবরাহকারীকে বার্তা</Label>
                                            <Textarea id="supplier_message" value={details.supplier_message} onChange={(event) => setDetails({ ...details, supplier_message: event.target.value })} rows={3} />
                                        </div>
                                    </>
                                )}
                            </div>
                        </Panel>
                    </div>
                </form>
            </Page>
            <div className="sticky bottom-0 z-20 border-t bg-background/85 shadow-[0_-8px_24px_-12px_rgb(225_29_72/0.2)] backdrop-blur-xl">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 md:px-8">
                    <p className="text-sm text-muted-foreground">
                        <span className="mr-1 text-2xl font-bold text-damaged">{qty(entry.total)}</span> পিস ক্ষতিগ্রস্ত স্টকে যাবে
                        {overdrawn && <span className="ml-3 font-semibold text-damaged">কিছু পরিমাণ বিক্রয়যোগ্য স্টকের চেয়ে বেশি।</span>}
                    </p>
                    <Button type="submit" form="damage-form" size="lg" variant="destructive" disabled={processing || entry.total === 0 || overdrawn || !details.reason}>
                        <PackageX /> {processing ? 'সেভ হচ্ছে…' : 'ক্ষতিগ্রস্ত মাল সেভ করুন'}
                    </Button>
                </div>
            </div>
        </>
    );
}

DamageCreate.layout = {
    breadcrumbs: [
        { title: 'ক্ষতিগ্রস্ত মাল', href: index() },
        { title: 'নতুন এন্ট্রি', href: create() },
    ],
};
