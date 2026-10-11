import { Head, router } from '@inertiajs/react';
import { FileText, PackageOpen, Undo2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { MultiProductEntry, useMultiProductEntry } from '@/components/inventory/multi-product-entry';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { qty, uuid } from '@/lib/format';
import { create, index, store } from '@/routes/returns';
import type { Option } from '@/types';

export default function ReturnCreate({ warehouses, today }: { warehouses: Option[]; today: string }) {
    const warehouseId = (warehouses.find((warehouse) => warehouse.is_default) ?? warehouses[0])?.id;
    const [details, setDetails] = useState({ return_date: today, customer_name: '', reference: '', reason: '', notes: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [idempotencyKey] = useState(uuid);
    const entry = useMultiProductEntry(warehouseId);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(
            store.url(),
            { ...details, warehouse_id: warehouseId, idempotency_key: idempotencyKey, lines: entry.lines },
            { onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors },
        );
    };

    const field = (key: keyof typeof details, label: string, placeholder?: string) => (
        <div className="space-y-2">
            <Label htmlFor={key}>{label}</Label>
            <Input id={key} value={details[key]} onChange={(event) => setDetails({ ...details, [key]: event.target.value })} placeholder={placeholder} />
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <>
            <Head title="নতুন রিটার্ন" />
            <Page className="max-w-6xl pb-28">
                <PageHeader title="কাস্টমার রিটার্ন লিখুন" description="যে সাইজ-রঙের মাল ফেরত এসেছে ঠিক সেগুলো লিখুন। মাল প্রথমে পরীক্ষাধীন স্টকে যাবে; পরীক্ষায় পাস না করা পর্যন্ত বিক্রি করা যাবে না।" icon={Undo2} tone="amber" />
                <form id="return-form" onSubmit={submit} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Panel title="ফেরত আসা মাল" icon={PackageOpen} tone="amber" className="order-2 lg:order-1">
                        <MultiProductEntry entry={entry} mode="in" errors={errors} stockKey="inspection" stockLabel="পরীক্ষাধীন" />
                    </Panel>
                    <Panel title="রিটার্নের তথ্য" icon={FileText} tone="slate" className="order-1 h-fit lg:order-2 lg:sticky lg:top-20">
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="return_date">রিটার্নের তারিখ</Label>
                                <Input id="return_date" type="date" value={details.return_date} max={today} onChange={(event) => setDetails({ ...details, return_date: event.target.value })} />
                                <InputError message={errors.return_date} />
                            </div>
                            {field('customer_name', 'কাস্টমারের নাম', 'ইচ্ছামতো')}
                            {field('reference', 'অর্ডার নম্বর', 'ইচ্ছামতো')}
                            {field('reason', 'ফেরতের কারণ', 'ভুল সাইজ, মত পরিবর্তন…')}
                            <div className="space-y-2">
                                <Label htmlFor="notes">নোট</Label>
                                <Textarea id="notes" value={details.notes} onChange={(event) => setDetails({ ...details, notes: event.target.value })} rows={3} />
                            </div>
                        </div>
                    </Panel>
                </form>
            </Page>
            <div className="sticky bottom-0 z-20 border-t bg-background/85 shadow-[0_-8px_24px_-12px_rgb(67_56_202/0.25)] backdrop-blur-xl">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 md:px-8">
                    <p className="text-sm text-muted-foreground">
                        <span className="mr-1 text-2xl font-bold text-inspection-ink">{qty(entry.total)}</span> পিস পরীক্ষাধীন স্টকে যাবে
                    </p>
                    <Button type="submit" form="return-form" size="lg" disabled={processing || entry.total === 0}>
                        <Undo2 /> {processing ? 'সেভ হচ্ছে…' : 'রিটার্ন সেভ করুন'}
                    </Button>
                </div>
            </div>
        </>
    );
}

ReturnCreate.layout = {
    breadcrumbs: [
        { title: 'কাস্টমার রিটার্ন', href: index() },
        { title: 'নতুন রিটার্ন', href: create() },
    ],
};
