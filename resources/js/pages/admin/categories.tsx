import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Tags, Trash2 } from 'lucide-react';
import { qty } from '@/lib/format';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { destroy, index, store, update } from '@/routes/admin/categories';

type Category = { id: number; name: string; parent_id: number | null; parent: { id: number; name: string } | null; description: string | null; products_count: number };

export default function Categories({ categories }: { categories: Category[] }) {
    const [editing, setEditing] = useState<Category | null>(null);
    const [error, setError] = useState<string | null>(null);
    const form = useForm({ name: '', parent_id: '', description: '' });

    const startEdit = (category: Category) => {
        setEditing(category);
        form.setData({ name: category.name, parent_id: category.parent_id ? String(category.parent_id) : '', description: category.description ?? '' });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, parent_id: data.parent_id || null }));
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
            <Head title="ক্যাটাগরি" />
            <Page className="max-w-4xl">
                <PageHeader title="ক্যাটাগরি" description="পণ্যগুলো দলে ভাগ করুন, যাতে খোঁজা আর রিপোর্ট দেখা সহজ হয়।" icon={Tags} tone="amber" />
                <Panel title={editing ? `${editing.name} সম্পাদনা` : 'নতুন ক্যাটাগরি'} icon={Tags} tone="amber">
                    <form onSubmit={submit} className="flex flex-wrap items-start gap-3">
                        <div>
                            <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="ক্যাটাগরির নাম" className="w-56" aria-label="নাম" />
                            <InputError message={form.errors.name} />
                        </div>
                        <Select value={form.data.parent_id || 'none'} onValueChange={(value) => form.setData('parent_id', value === 'none' ? '' : value)}>
                            <SelectTrigger className="w-48" aria-label="মূল ক্যাটাগরি">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">মূল ক্যাটাগরি নেই</SelectItem>
                                {categories
                                    .filter((category) => category.id !== editing?.id)
                                    .map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        <Button type="submit" disabled={form.processing || !form.data.name}>
                            {editing ? 'সেভ করুন' : 'ক্যাটাগরি যোগ করুন'}
                        </Button>
                        {editing && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setEditing(null);
                                    form.reset();
                                }}
                            >
                                বাতিল
                            </Button>
                        )}
                    </form>
                </Panel>
                {error && <p className="text-sm text-damaged">{error}</p>}
                <section className="surface overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>নাম</TableHead>
                                <TableHead>মূল ক্যাটাগরি</TableHead>
                                <TableHead className="text-right">পণ্য</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell className="font-medium">{category.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{category.parent?.name ?? '—'}</TableCell>
                                    <TableCell className="text-right tabular-nums">{qty(category.products_count)}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" className="size-8" aria-label={`${category.name} সম্পাদনা`} onClick={() => startEdit(category)}>
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                disabled={category.products_count > 0}
                                                aria-label={`${category.name} মুছুন`}
                                                onClick={() => router.delete(destroy.url(category.id), { preserveScroll: true, onError: (errors) => setError(Object.values(errors)[0]) })}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
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

Categories.layout = { breadcrumbs: [{ title: 'ক্যাটাগরি', href: index() }] };
