import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, ArrowDownToLine, ArrowUpFromLine, Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { ConditionBar, ConditionLegend, ConditionQty } from '@/components/inventory/condition-bar';
import { DataTable, type ColumnDef } from '@/components/inventory/data-table';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { combinationKey, VariantBuilder, type Combination } from '@/components/inventory/variant-builder';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { businessDate, dateTime, onlyDigits, qty, signedQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as movementShow } from '@/routes/movements';
import { index, show, update } from '@/routes/products';
import { create as stockCreate } from '@/routes/stock';
import { archive, restore, store as storeVariants, update as updateVariant } from '@/routes/variants';
import type { Attribute, Option, Variant } from '@/types';

type Product = {
    id: number;
    name: string;
    code: string;
    unit: string;
    description: string | null;
    status: 'active' | 'archived';
    category: { id: number; name: string } | null;
    category_id: number | null;
    created_by: string | null;
    created_at: string | null;
};

type LedgerRow = { id: number; movement_id: number; reference_no: string; type: string; sku: string; variant: string; condition: string; change: number; balance_after: number; date: string; user: string | null };

type Props = { product: Product; attributes: Attribute[]; variants: Variant[]; ledger: LedgerRow[]; categories: Option[] };

export default function ProductShow({ product, attributes, variants, ledger, categories }: Props) {
    const can = useCan();
    const [editing, setEditing] = useState(false);
    const [adding, setAdding] = useState(false);
    const [editingVariant, setEditingVariant] = useState<Variant | null>(null);

    const totals = useMemo(
        () =>
            variants.reduce(
                (sum, variant) => ({
                    available: sum.available + (variant.stock?.available ?? 0),
                    inspection: sum.inspection + (variant.stock?.inspection ?? 0),
                    damaged: sum.damaged + (variant.stock?.damaged ?? 0),
                    with_supplier: sum.with_supplier + (variant.stock?.with_supplier ?? 0),
                }),
                { available: 0, inspection: 0, damaged: 0, with_supplier: 0 },
            ),
        [variants],
    );

    const valueById = useMemo(() => new Map(attributes.flatMap((attribute) => attribute.values.map((value) => [value.id, value] as const))), [attributes]);

    const columns: ColumnDef<Variant>[] = [
        {
            accessorKey: 'name',
            header: 'ভ্যারিয়েন্ট',
            cell: ({ row }) => (
                <div className={cn('flex items-center gap-2.5', row.original.status === 'archived' && 'opacity-60')}>
                    <div className="flex -space-x-1">
                        {row.original.value_ids
                            ?.map((id) => valueById.get(id))
                            .filter((value) => value?.swatch)
                            .map((value) => <span key={value!.id} className="size-3.5 rounded-full border border-black/15 ring-2 ring-card" style={{ backgroundColor: value!.swatch! }} />)}
                    </div>
                    <div>
                        <p className="font-medium">{row.original.name}</p>
                        <p className="text-xs text-muted-foreground">{row.original.sku}</p>
                    </div>
                </div>
            ),
        },
        { id: 'split', header: 'ভাগ', enableSorting: false, cell: ({ row }) => <ConditionBar split={row.original.stock!} className="w-28" /> },
        { id: 'available', accessorFn: (row) => row.stock?.available ?? 0, header: 'বিক্রয়যোগ্য', meta: { numeric: true }, cell: ({ getValue }) => <ConditionQty value={getValue<number>()} condition="available" /> },
        { id: 'inspection', accessorFn: (row) => row.stock?.inspection ?? 0, header: 'পরীক্ষাধীন', meta: { numeric: true }, cell: ({ getValue }) => <ConditionQty value={getValue<number>()} condition="inspection" /> },
        { id: 'damaged', accessorFn: (row) => row.stock?.damaged ?? 0, header: 'ক্ষতিগ্রস্ত', meta: { numeric: true }, cell: ({ getValue }) => <ConditionQty value={getValue<number>()} condition="damaged" /> },
        { id: 'with_supplier', accessorFn: (row) => row.stock?.with_supplier ?? 0, header: 'সরবরাহকারীর কাছে', meta: { numeric: true }, cell: ({ getValue }) => <ConditionQty value={getValue<number>()} condition="with_supplier" /> },
        {
            accessorKey: 'low_stock_threshold',
            header: 'সর্বনিম্ন',
            meta: { numeric: true },
            cell: ({ row }) => {
                const low = row.original.status === 'active' && row.original.low_stock_threshold > 0 && (row.original.stock?.available ?? 0) <= row.original.low_stock_threshold;

                return <span className={cn(low && 'font-semibold text-damaged')}>{row.original.low_stock_threshold ? qty(row.original.low_stock_threshold) : '—'}</span>;
            },
        },
        { accessorKey: 'status', header: 'অবস্থা', cell: ({ getValue }) => <StatusBadge status={getValue<string>()} /> },
        ...(can('products.manage')
            ? [
                  {
                      id: 'actions',
                      header: '',
                      enableSorting: false,
                      meta: { className: 'w-0' },
                      cell: ({ row }) => (
                          <div className="flex justify-end gap-1">
                              <Button variant="ghost" size="icon" className="size-8" aria-label={`${row.original.sku} সম্পাদনা`} onClick={() => setEditingVariant(row.original)}>
                                  <Pencil className="size-4" />
                              </Button>
                              {row.original.status === 'active' ? (
                                  <Button variant="ghost" size="icon" className="size-8" aria-label={`${row.original.sku} আর্কাইভ`} title="আর্কাইভ করুন (ইতিহাস থাকবে)" onClick={() => router.post(archive.url(row.original.id), {}, { preserveScroll: true })}>
                                      <Archive className="size-4" />
                                  </Button>
                              ) : (
                                  <Button variant="ghost" size="icon" className="size-8" aria-label={`${row.original.sku} আবার চালু`} title="আবার চালু করুন" onClick={() => router.post(restore.url(row.original.id), {}, { preserveScroll: true })}>
                                      <ArchiveRestore className="size-4" />
                                  </Button>
                              )}
                          </div>
                      ),
                  } satisfies ColumnDef<Variant>,
              ]
            : []),
    ];

    const ledgerColumns: ColumnDef<LedgerRow>[] = [
        { accessorKey: 'date', header: 'তারিখ', cell: ({ getValue }) => businessDate(getValue<string>()) },
        {
            accessorKey: 'reference_no',
            header: 'রেফারেন্স',
            cell: ({ row }) => (
                <Link href={movementShow.url(row.original.movement_id)} className="font-medium text-primary hover:underline">
                    {row.original.reference_no}
                </Link>
            ),
        },
        { accessorKey: 'type', header: 'লেনদেন' },
        { accessorKey: 'variant', header: 'ভ্যারিয়েন্ট' },
        { accessorKey: 'condition', header: 'অবস্থা' },
        { accessorKey: 'change', header: 'পরিবর্তন', meta: { numeric: true }, cell: ({ getValue }) => <span className={cn('font-semibold', getValue<number>() > 0 ? 'text-available' : 'text-damaged')}>{signedQty(getValue<number>())}</span> },
        { accessorKey: 'balance_after', header: 'পরের ব্যালান্স', meta: { numeric: true }, cell: ({ getValue }) => qty(getValue<number>()) },
        { accessorKey: 'user', header: 'কে করেছেন', cell: ({ getValue }) => getValue<string>() ?? 'সিস্টেম' },
    ];

    return (
        <>
            <Head title={product.name} />
            <Page>
                <PageHeader
                    title={product.name}
                    description={product.description}
                    meta={
                        <>
                            <span>{product.code}</span>
                            {product.category && <span>{product.category.name}</span>}
                            <StatusBadge status={product.status} />
                        </>
                    }
                    actions={
                        <>
                            {can('stock.in') && (
                                <Button variant="outline" asChild>
                                    <Link href={stockCreate.url('in')}>
                                        <ArrowDownToLine /> স্টক ইন
                                    </Link>
                                </Button>
                            )}
                            {can('stock.out') && (
                                <Button variant="outline" asChild>
                                    <Link href={stockCreate.url('out')}>
                                        <ArrowUpFromLine /> স্টক আউট
                                    </Link>
                                </Button>
                            )}
                            {can('products.manage') && (
                                <Button variant="outline" onClick={() => setEditing(true)}>
                                    <Pencil /> সম্পাদনা
                                </Button>
                            )}
                        </>
                    }
                />

                <section className="surface space-y-4 bg-gradient-to-br from-indigo-500/5 to-transparent p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <p className="text-sm text-muted-foreground">
                            <span className="text-3xl font-bold text-foreground">{qty(totals.available + totals.inspection + totals.damaged)}</span> পিস গুদামে আছে, {qty(variants.filter((variant) => variant.status === 'active').length)}টি চালু ভ্যারিয়েন্টে
                        </p>
                    </div>
                    <ConditionBar split={totals} size="md" />
                    <ConditionLegend split={totals} />
                </section>

                <Tabs defaultValue="variants">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <TabsList>
                            <TabsTrigger value="variants">ভ্যারিয়েন্ট ({qty(variants.length)})</TabsTrigger>
                            {attributes.length === 2 && <TabsTrigger value="matrix">স্টক ছক</TabsTrigger>}
                            <TabsTrigger value="ledger">সাম্প্রতিক খতিয়ান</TabsTrigger>
                        </TabsList>
                        {can('products.manage') && attributes.length > 0 && (
                            <Button size="sm" onClick={() => setAdding(true)}>
                                <Plus /> ভ্যারিয়েন্ট যোগ করুন
                            </Button>
                        )}
                    </div>
                    <TabsContent value="variants">
                        <section className="surface overflow-hidden">
                            <DataTable columns={columns} data={variants} />
                        </section>
                    </TabsContent>
                    {attributes.length === 2 && (
                        <TabsContent value="matrix">
                            <StockMatrix attributes={attributes} variants={variants} />
                        </TabsContent>
                    )}
                    <TabsContent value="ledger">
                        <section className="surface overflow-hidden">
                            <DataTable columns={ledgerColumns} data={ledger} empty={<p className="py-10 text-center text-sm text-muted-foreground">এই পণ্যের এখনো কোনো স্টক লেনদেন হয়নি।</p>} />
                        </section>
                    </TabsContent>
                </Tabs>

                <Panel>
                    <dl className="grid gap-4 sm:grid-cols-4">
                        <Field label="একক" value={product.unit === 'pcs' ? 'পিস' : product.unit} />
                        <Field label="তৈরি করেছেন" value={product.created_by} />
                        <Field label="তৈরির সময়" value={dateTime(product.created_at)} />
                        <Field label="বৈশিষ্ট্য" value={attributes.map((attribute) => attribute.name).join(' × ') || 'নেই'} />
                    </dl>
                </Panel>
            </Page>

            {can('products.manage') && (
                <>
                    <EditProductDialog product={product} categories={categories} open={editing} onOpenChange={setEditing} />
                    <AddVariantsDialog product={product} attributes={attributes} variants={variants} open={adding} onOpenChange={setAdding} />
                    {editingVariant && <EditVariantDialog variant={editingVariant} onClose={() => setEditingVariant(null)} />}
                </>
            )}
        </>
    );
}

function StockMatrix({ attributes, variants }: { attributes: Attribute[]; variants: Variant[] }) {
    const [rowAttribute, columnAttribute] = attributes;
    const byKey = new Map(variants.map((variant) => [combinationKey(variant.value_ids ?? []), variant]));
    const rows = rowAttribute.values.filter((row) => columnAttribute.values.some((column) => byKey.has(combinationKey([row.id, column.id]))));
    const columns = columnAttribute.values.filter((column) => rowAttribute.values.some((row) => byKey.has(combinationKey([row.id, column.id]))));

    return (
        <div className="surface overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b bg-muted/40">
                        <th className="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">বিক্রয়যোগ্য স্টক</th>
                        {columns.map((column) => (
                            <th key={column.id} className="px-4 py-2.5 text-left text-xs font-semibold">
                                <span className="inline-flex items-center gap-1.5">
                                    {column.swatch && <span className="size-3 rounded-full border border-black/15" style={{ backgroundColor: column.swatch }} />}
                                    {column.value}
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-b last:border-0">
                            <th className="px-4 py-3 text-left font-semibold">{row.value}</th>
                            {columns.map((column) => {
                                const variant = byKey.get(combinationKey([row.id, column.id]));

                                if (!variant) {
                                    return (
                                        <td key={column.id} className="px-4 py-3 text-muted-foreground/40">
                                            —
                                        </td>
                                    );
                                }

                                const low = variant.low_stock_threshold > 0 && (variant.stock?.available ?? 0) <= variant.low_stock_threshold;

                                return (
                                    <td key={column.id} className={cn('px-4 py-3', variant.status === 'archived' && 'opacity-50')}>
                                        <p className={cn('text-base font-bold tabular-nums', low && 'text-damaged')}>{qty(variant.stock?.available)}</p>
                                        <ConditionBar split={variant.stock!} className="mt-1 w-20" />
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function EditProductDialog({ product, categories, open, onOpenChange }: { product: Product; categories: Option[]; open: boolean; onOpenChange: (open: boolean) => void }) {
    const form = useForm({
        name: product.name,
        code: product.code,
        category_id: product.category_id ? String(product.category_id) : '',
        unit: product.unit,
        description: product.description ?? '',
        status: product.status,
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({ ...data, category_id: data.category_id || null }));
                        form.put(update.url(product.id), { preserveScroll: true, onSuccess: () => onOpenChange(false) });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>পণ্য সম্পাদনা</DialogTitle>
                        <DialogDescription>শুধু পণ্যের তথ্য বদলাবে, স্টকের ইতিহাস ঠিক থাকবে।</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="edit-name">নাম</Label>
                        <Input id="edit-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-2">
                            <Label htmlFor="edit-code">কোড</Label>
                            <Input id="edit-code" value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} />
                            <InputError message={form.errors.code} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-unit">একক</Label>
                            <Input id="edit-unit" value={form.data.unit} onChange={(event) => form.setData('unit', event.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
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
                        <div className="space-y-2">
                            <Label>অবস্থা</Label>
                            <Select value={form.data.status} onValueChange={(value) => form.setData('status', value as 'active' | 'archived')}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">চালু</SelectItem>
                                    <SelectItem value="archived">আর্কাইভ</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="edit-description">বিবরণ</Label>
                        <Textarea id="edit-description" value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            বাতিল
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            সেভ করুন
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddVariantsDialog({ product, attributes, variants, open, onOpenChange }: { product: Product; attributes: Attribute[]; variants: Variant[]; open: boolean; onOpenChange: (open: boolean) => void }) {
    const [combinations, setCombinations] = useState<Combination[]>([]);
    const [threshold, setThreshold] = useState('10');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const existingKeys = variants.map((variant) => combinationKey(variant.value_ids ?? []));

    const submit = () => {
        router.post(
            storeVariants.url(product.id),
            { variants: combinations.map((combination) => ({ value_ids: combination, low_stock_threshold: Number(threshold || 0) })) },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: () => {
                    setCombinations([]);
                    setErrors({});
                    onOpenChange(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{product.name} এ ভ্যারিয়েন্ট যোগ করুন</DialogTitle>
                    <DialogDescription>যে কম্বিনেশন আগে থেকে আছে সেগুলো বাছা যাবে না। নতুন ভ্যারিয়েন্টের স্টক শূন্য থেকে শুরু হবে।</DialogDescription>
                </DialogHeader>
                <div className="max-h-[60vh] overflow-y-auto">
                    <VariantBuilder attributes={attributes} value={combinations} onChange={setCombinations} existingKeys={existingKeys} />
                </div>
                {Object.values(errors).length > 0 && <p className="text-sm text-damaged">{Object.values(errors)[0]}</p>}
                <DialogFooter className="items-center gap-3 sm:justify-between">
                    <label className="flex items-center gap-2 text-sm">
                        সর্বনিম্ন সীমা
                        <Input value={threshold} onChange={(event) => setThreshold(onlyDigits(event.target.value))} className="h-8 w-20 text-right" />
                    </label>
                    <Button onClick={submit} disabled={processing || combinations.length === 0}>
                        {qty(combinations.length)}টি ভ্যারিয়েন্ট যোগ করুন
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function EditVariantDialog({ variant, onClose }: { variant: Variant; onClose: () => void }) {
    const form = useForm({ sku: variant.sku, barcode: variant.barcode ?? '', low_stock_threshold: String(variant.low_stock_threshold) });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateVariant.url(variant.id), { preserveScroll: true, onSuccess: onClose });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>{variant.name} সম্পাদনা</DialogTitle>
                        <DialogDescription>সাইজ-রঙের কম্বিনেশন বদলানো যায় না। দরকার হলে এটি আর্কাইভ করে নতুন একটি যোগ করুন।</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="variant-sku">SKU</Label>
                        <Input id="variant-sku" value={form.data.sku} onChange={(event) => form.setData('sku', event.target.value.toUpperCase())} />
                        <InputError message={form.errors.sku} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="variant-barcode">বারকোড (ইচ্ছামতো)</Label>
                        <Input id="variant-barcode" value={form.data.barcode} onChange={(event) => form.setData('barcode', event.target.value)} />
                        <InputError message={form.errors.barcode} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="variant-threshold">সর্বনিম্ন স্টক সীমা</Label>
                        <Input id="variant-threshold" value={form.data.low_stock_threshold} onChange={(event) => form.setData('low_stock_threshold', onlyDigits(event.target.value))} inputMode="numeric" />
                        <InputError message={form.errors.low_stock_threshold} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            বাতিল
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            সেভ করুন
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ProductShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'পণ্য', href: index() },
        { title: props.product.name, href: show(props.product.id) },
    ],
});
