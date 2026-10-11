import { Head, router, useForm } from '@inertiajs/react';
import { Palette, Plus, Ruler, Trash2 } from 'lucide-react';
import { qty } from '@/lib/format';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { index, store } from '@/routes/admin/attributes';
import values from '@/routes/admin/attributes/values';

type Value = { id: number; value: string; code: string | null; swatch: string | null; sort_order: number; variants_count: number };
type Attribute = { id: number; name: string; is_color: boolean; sort_order: number; products_count: number; values: Value[] };

function AddValue({ attribute }: { attribute: Attribute }) {
    const form = useForm({ value: '', code: '', swatch: attribute.is_color ? '#888888' : '' });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(values.store.url(attribute.id), { preserveScroll: true, onSuccess: () => form.reset('value', 'code') });
            }}
            className="flex flex-wrap items-start gap-2"
        >
            <div>
                <Input value={form.data.value} onChange={(event) => form.setData('value', event.target.value)} placeholder={`নতুন ${attribute.name}`} className="h-8 w-40" aria-label="মান" />
                <InputError message={form.errors.value} />
            </div>
            <Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} placeholder="SKU কোড" className="h-8 w-28" aria-label="SKU কোড" />
            {attribute.is_color && <input type="color" value={form.data.swatch} onChange={(event) => form.setData('swatch', event.target.value)} className="h-8 w-10 cursor-pointer rounded border" aria-label="রং বাছুন" />}
            <Button type="submit" size="sm" disabled={form.processing || !form.data.value}>
                <Plus /> যোগ করুন
            </Button>
        </form>
    );
}

export default function Attributes({ attributes }: { attributes: Attribute[] }) {
    const form = useForm({ name: '', is_color: false });
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title="সাইজ, রং ইত্যাদি" />
            <Page className="max-w-5xl">
                <PageHeader title="সাইজ, রং ও অন্যান্য বৈশিষ্ট্য" description="পণ্য যেসব দিক দিয়ে আলাদা হয়: সাইজ, রং, কাপড় ইত্যাদি। কোনো ভ্যারিয়েন্টে ব্যবহার হচ্ছে এমন মান মোছা যায় না। SKU কোড ইংরেজিতে দিন (যেমন RED), যা SKU তে ব্যবহার হবে।" icon={Ruler} tone="violet" />

                <Panel title="নতুন বৈশিষ্ট্য" icon={Plus} tone="violet">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
                        }}
                        className="flex flex-wrap items-start gap-3"
                    >
                        <div>
                            <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="যেমন: ফিট, হাতা, উপাদান" className="w-64" aria-label="বৈশিষ্ট্যের নাম" />
                            <InputError message={form.errors.name} />
                        </div>
                        <label className="flex h-9 items-center gap-2 text-sm">
                            <Switch checked={form.data.is_color} onCheckedChange={(checked) => form.setData('is_color', checked)} />
                            মানগুলো রং
                        </label>
                        <Button type="submit" disabled={form.processing || !form.data.name}>
                            বৈশিষ্ট্য তৈরি করুন
                        </Button>
                    </form>
                </Panel>

                {error && <p className="text-sm text-damaged">{error}</p>}

                {attributes.map((attribute) => (
                    <Panel key={attribute.id} title={attribute.name} description={`${qty(attribute.values.length)}টি মান, ${qty(attribute.products_count)}টি পণ্যে ব্যবহার হচ্ছে`} icon={attribute.is_color ? Palette : Ruler} tone={attribute.is_color ? 'rose' : 'indigo'}>
                        <div className="space-y-4">
                            <ul className="flex flex-wrap gap-2">
                                {attribute.values.map((value) => (
                                    <li key={value.id} className="group flex items-center gap-2 rounded-full border bg-card py-1 pr-1 pl-3 text-sm shadow-xs transition-all hover:-translate-y-0.5 hover:shadow-md">
                                        {value.swatch && <span className="size-3.5 rounded-full border border-black/15" style={{ backgroundColor: value.swatch }} />}
                                        <span className="font-medium">{value.value}</span>
                                        {value.code && <span className="text-xs text-muted-foreground">{value.code}</span>}
                                        <span className="text-xs text-muted-foreground tabular-nums" title="এই মান যতগুলো ভ্যারিয়েন্টে আছে">
                                            {qty(value.variants_count)}
                                        </span>
                                        <button
                                            type="button"
                                            disabled={value.variants_count > 0}
                                            title={value.variants_count > 0 ? 'ভ্যারিয়েন্টে ব্যবহার হচ্ছে' : `${value.value} মুছুন`}
                                            aria-label={`${value.value} মুছুন`}
                                            onClick={() => router.delete(values.destroy.url(value.id), { preserveScroll: true, onError: (errors) => setError(Object.values(errors)[0]) })}
                                            className="rounded-full p-1 text-muted-foreground hover:bg-damaged-soft hover:text-damaged disabled:pointer-events-none disabled:opacity-30"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <AddValue attribute={attribute} />
                        </div>
                    </Panel>
                ))}
            </Page>
        </>
    );
}

Attributes.layout = { breadcrumbs: [{ title: 'সাইজ, রং ইত্যাদি', href: index() }] };
