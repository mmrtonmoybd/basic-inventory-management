import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, FileText, Grid3x3, ListChecks, Ruler, Shirt, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { combinationKey, combinationLabel, previewSku, VariantBuilder, type Combination } from '@/components/inventory/variant-builder';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { onlyDigits, qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, index, store } from '@/routes/products';
import type { Attribute, Option } from '@/types';

type VariantRow = { sku: string; low_stock_threshold: string };

export default function ProductCreate({ attributes, categories }: { attributes: Attribute[]; categories: Option[] }) {
    const [axisIds, setAxisIds] = useState<number[]>([]);
    const [combinations, setCombinations] = useState<Combination[]>([]);
    const [rows, setRows] = useState<Record<string, VariantRow>>({});
    const [defaultThreshold, setDefaultThreshold] = useState('10');

    const form = useForm({ name: '', code: '', category_id: '', unit: 'pcs', description: '' });
    const axes = axisIds.map((id) => attributes.find((attribute) => attribute.id === id)!).filter(Boolean);

    const changeAxes = (next: number[]) => {
        setAxisIds(next);
        setCombinations([]);
        setRows({});
    };

    const row = (key: string): VariantRow => rows[key] ?? { sku: '', low_stock_threshold: '' };
    const setRow = (key: string, patch: Partial<VariantRow>) => setRows((current) => ({ ...current, [key]: { ...row(key), ...patch } }));

    const variantList = axes.length === 0 ? [{ key: 'default', combination: [] as number[] }] : combinations.map((combination) => ({ key: combinationKey(combination), combination }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            category_id: data.category_id || null,
            attribute_ids: axisIds,
            variants: variantList.map(({ key, combination }) => ({
                value_ids: combination,
                sku: row(key).sku || null,
                low_stock_threshold: Number(row(key).low_stock_threshold || defaultThreshold || 0),
            })),
        }));
        form.post(store.url());
    };

    const variantErrors = Object.entries(form.errors as Record<string, string>).filter(([key]) => key.startsWith('variants'));

    return (
        <>
            <Head title="নতুন পণ্য" />
            <Page className="max-w-5xl">
                <PageHeader title="নতুন পণ্য" description="পণ্যের তথ্য লিখুন, সাইজ/রঙের মতো বৈশিষ্ট্য বাছুন, তারপর শুধু যে কম্বিনেশনগুলো আসলে আছে সেগুলোতে টিক দিন।" icon={Shirt} tone="indigo" />

                <form onSubmit={submit} className="space-y-6">
                    <Panel title="পণ্যের তথ্য" icon={FileText} tone="indigo">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="name">পণ্যের নাম</Label>
                                <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="যেমন: প্রিমিয়াম পোলো টি-শার্ট" autoFocus />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="code">পণ্যের কোড</Label>
                                <Input id="code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ''))} placeholder="POLO" />
                                <p className="text-xs text-muted-foreground">প্রতিটি SKU এই কোড দিয়ে শুরু হবে। শুধু ইংরেজি অক্ষর ও সংখ্যা।</p>
                                <InputError message={form.errors.code} />
                            </div>
                            <div className="space-y-2">
                                <Label>ক্যাটাগরি</Label>
                                <Select value={form.data.category_id || 'none'} onValueChange={(value) => form.setData('category_id', value === 'none' ? '' : value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">কোনো ক্যাটাগরি নয়</SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="description">বিবরণ</Label>
                                <Textarea id="description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} rows={2} />
                            </div>
                        </div>
                    </Panel>

                    <Panel title="বৈশিষ্ট্য বাছুন" description="সাইজ-রঙ না থাকলে কিছুই বাছবেন না। যে ক্রমে বাছবেন, ভ্যারিয়েন্টের নাম সেভাবে লেখা হবে (যেমন: সাইজ / রং)।" icon={Ruler} tone="violet">
                        <div className="flex flex-wrap gap-2">
                            {attributes.map((attribute) => {
                                const on = axisIds.includes(attribute.id);

                                return (
                                    <button
                                        key={attribute.id}
                                        type="button"
                                        aria-pressed={on}
                                        onClick={() => changeAxes(on ? axisIds.filter((id) => id !== attribute.id) : [...axisIds, attribute.id])}
                                        className={cn('rounded-full border px-4 py-1.5 text-sm font-semibold transition-all duration-200', on ? 'scale-105 border-transparent bg-brand text-white shadow-md' : 'hover:border-primary/40 hover:bg-accent')}
                                    >
                                        {attribute.name}
                                        <span className={cn('ml-1.5 text-xs', on ? 'opacity-80' : 'text-muted-foreground')}>{qty(attribute.values.length)}</span>
                                    </button>
                                );
                            })}
                        </div>
                        {axes.length > 1 && (
                            <ol className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                                {axes.map((axis, position) => (
                                    <li key={axis.id} className="flex items-center gap-1 rounded-md border bg-muted/40 py-1 pr-1 pl-3">
                                        <span className="font-medium">{axis.name}</span>
                                        <Button type="button" variant="ghost" size="icon" className="size-6" disabled={position === 0} aria-label={`${axis.name} আগে নিন`} onClick={() => changeAxes(axisIds.map((id, index) => (index === position - 1 ? axisIds[position] : index === position ? axisIds[position - 1] : id)))}>
                                            <ArrowUp className="size-3.5" />
                                        </Button>
                                        <Button type="button" variant="ghost" size="icon" className="size-6" disabled={position === axes.length - 1} aria-label={`${axis.name} পরে নিন`} onClick={() => changeAxes(axisIds.map((id, index) => (index === position + 1 ? axisIds[position] : index === position ? axisIds[position + 1] : id)))}>
                                            <ArrowDown className="size-3.5" />
                                        </Button>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </Panel>

                    {axes.length > 0 && (
                        <Panel
                            title="কম্বিনেশন বাছুন"
                            description={axes.length === 2 ? `যে ${axes[0].name} আর ${axes[1].name} এর মিল আছে শুধু সেগুলোতে টিক দিন। সারি বা কলামের নামে ক্লিক করলে পুরো লাইন একসাথে বাছাই হবে।` : 'যে মানগুলো আছে সেগুলো বাছুন।'}
                            icon={Grid3x3}
                            tone="teal"
                            actions={<span className="rounded-full bg-accent px-3 py-1 text-sm font-bold text-primary">{qty(combinations.length)}টি বাছাই করা</span>}
                        >
                            <VariantBuilder attributes={axes} value={combinations} onChange={setCombinations} />
                        </Panel>
                    )}

                    <Panel
                        title="ভ্যারিয়েন্ট মিলিয়ে দেখুন"
                        description="SKU নিজে থেকেই তৈরি হবে; চাইলে নিজে লিখতে পারেন। বিক্রয়যোগ্য স্টক সর্বনিম্ন সীমায় নামলে সতর্কবার্তা আসবে।"
                        icon={ListChecks}
                        tone="amber"
                        actions={
                            <label className="flex items-center gap-2 text-sm">
                                সবার সর্বনিম্ন সীমা
                                <Input value={defaultThreshold} onChange={(event) => setDefaultThreshold(onlyDigits(event.target.value))} className="h-8 w-20 text-right" inputMode="numeric" />
                            </label>
                        }
                        bodyClassName="p-0"
                    >
                        {variantList.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-muted-foreground">উপরে অন্তত একটি কম্বিনেশন বাছুন।</p>
                        ) : (
                            <div className="divide-y">
                                {variantList.map(({ key, combination }) => (
                                    <div key={key} className="grid items-center gap-3 px-5 py-2.5 sm:grid-cols-[1fr_14rem_8rem_2rem]">
                                        <span className="text-sm font-medium">{axes.length === 0 ? 'সাধারণ (কোনো সাইজ-রঙ নেই)' : combinationLabel(axes, combination)}</span>
                                        <Input
                                            value={row(key).sku}
                                            onChange={(event) => setRow(key, { sku: event.target.value.toUpperCase() })}
                                            placeholder={axes.length === 0 ? form.data.code || 'কোড' : previewSku(form.data.code, axes, combination)}
                                            aria-label="SKU"
                                            className="h-8"
                                        />
                                        <Input value={row(key).low_stock_threshold} onChange={(event) => setRow(key, { low_stock_threshold: onlyDigits(event.target.value) })} placeholder={defaultThreshold} aria-label="সর্বনিম্ন সীমা" className="h-8 text-right" inputMode="numeric" />
                                        {axes.length > 0 ? (
                                            <Button type="button" variant="ghost" size="icon" className="size-8" aria-label="সরান" onClick={() => setCombinations(combinations.filter((item) => combinationKey(item) !== key))}>
                                                <X className="size-4" />
                                            </Button>
                                        ) : (
                                            <span />
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                        {variantErrors.length > 0 && (
                            <ul className="space-y-1 border-t px-5 py-3">
                                {variantErrors.map(([key, message]) => (
                                    <li key={key} className="text-sm text-damaged">
                                        {message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>

                    <div className="flex items-center justify-end gap-3">
                        <Button variant="ghost" asChild>
                            <Link href={index.url()}>বাতিল</Link>
                        </Button>
                        <Button type="submit" disabled={form.processing || variantList.length === 0 || !form.data.name || !form.data.code}>
                            {form.processing ? 'তৈরি হচ্ছে…' : `${qty(variantList.length)}টি ভ্যারিয়েন্টসহ পণ্য তৈরি করুন`}
                        </Button>
                    </div>
                </form>
            </Page>
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        { title: 'পণ্য', href: index() },
        { title: 'নতুন পণ্য', href: create() },
    ],
};
