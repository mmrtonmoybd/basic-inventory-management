import { Head, useForm } from '@inertiajs/react';
import { Pencil, Warehouse } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { qty } from '@/lib/format';
import { index, store, update } from '@/routes/admin/warehouses';

type Warehouse = { id: number; name: string; code: string; address: string | null; is_default: boolean; is_active: boolean; physical: number; available: number };

export default function Warehouses({ warehouses }: { warehouses: Warehouse[] }) {
    const [editing, setEditing] = useState<Warehouse | null>(null);
    const form = useForm({ name: '', code: '', address: '', is_default: false, is_active: true });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditing(null);
            },
        };
        editing ? form.put(update.url(editing.id), options) : form.post(store.url(), options);
    };

    return (
        <>
            <Head title="গুদাম" />
            <Page className="max-w-4xl">
                <PageHeader title="গুদাম" description="প্রতিটি গুদামের স্টক আলাদা হিসাব হয়। একটিই চালু গুদাম থাকলে ফর্মে সেটিই নিজে থেকে বাছাই হয়।" icon={Warehouse} tone="teal" />
                <Panel title={editing ? `${editing.name} সম্পাদনা` : 'নতুন গুদাম'} icon={Warehouse} tone="teal">
                    <form onSubmit={submit} className="space-y-3">
                        <div className="grid gap-3 sm:grid-cols-[1fr_8rem_1fr]">
                            <div>
                                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="নাম" aria-label="নাম" />
                                <InputError message={form.errors.name} />
                            </div>
                            <div>
                                <Input value={form.data.code} onChange={(event) => form.setData('code', event.target.value.toUpperCase())} placeholder="কোড" aria-label="কোড" />
                                <InputError message={form.errors.code} />
                            </div>
                            <Input value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} placeholder="ঠিকানা" aria-label="ঠিকানা" />
                        </div>
                        <div className="flex flex-wrap items-center gap-5">
                            <label className="flex items-center gap-2 text-sm">
                                <Switch checked={form.data.is_default} onCheckedChange={(checked) => form.setData('is_default', checked)} /> প্রধান গুদাম
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Switch checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} /> চালু
                            </label>
                            <Button type="submit" disabled={form.processing || !form.data.name || !form.data.code}>
                                {editing ? 'সেভ করুন' : 'গুদাম যোগ করুন'}
                            </Button>
                        </div>
                    </form>
                </Panel>
                <section className="surface overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>গুদাম</TableHead>
                                <TableHead>অবস্থা</TableHead>
                                <TableHead className="text-right">বিক্রয়যোগ্য</TableHead>
                                <TableHead className="text-right">মোট মজুদ</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {warehouses.map((warehouse) => (
                                <TableRow key={warehouse.id}>
                                    <TableCell>
                                        <p className="font-medium">
                                            {warehouse.name} {warehouse.is_default && <span className="ml-1 rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground">প্রধান</span>}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{[warehouse.code, warehouse.address].filter(Boolean).join(', ')}</p>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={warehouse.is_active ? 'active' : 'inactive'} />
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(warehouse.available)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(warehouse.physical)}</TableCell>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8"
                                            aria-label={`${warehouse.name} সম্পাদনা`}
                                            onClick={() => {
                                                setEditing(warehouse);
                                                form.setData({ name: warehouse.name, code: warehouse.code, address: warehouse.address ?? '', is_default: warehouse.is_default, is_active: warehouse.is_active });
                                            }}
                                        >
                                            <Pencil className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </Page>
        </>
    );
}

Warehouses.layout = { breadcrumbs: [{ title: 'গুদাম', href: index() }] };
