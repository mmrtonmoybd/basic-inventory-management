import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, UserCog } from 'lucide-react';
import { roleLabel } from '@/lib/format';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { index, store, update } from '@/routes/admin/users';

type UserRow = { id: number; name: string; email: string; is_active: boolean; roles: string[]; created_at: string | null };

function UserDialog({ user, roles, onClose }: { user: UserRow | null; roles: string[]; onClose: () => void }) {
    const form = useForm({ name: user?.name ?? '', email: user?.email ?? '', password: '', is_active: user?.is_active ?? true, roles: user?.roles ?? [] });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        user ? form.put(update.url(user.id), { preserveScroll: true, onSuccess: onClose }) : form.post(store.url(), { preserveScroll: true, onSuccess: onClose });
                    }}
                    className="space-y-4"
                >
                    <DialogHeader>
                        <DialogTitle>{user ? `${user.name} সম্পাদনা` : 'নতুন ব্যবহারকারী'}</DialogTitle>
                        <DialogDescription>রোল ঠিক করে এই ব্যক্তি কী দেখতে ও করতে পারবেন। অনুমতি সার্ভারে যাচাই করা হয়।</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="user-name">নাম</Label>
                            <Input id="user-name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-email">ইমেইল</Label>
                            <Input id="user-email" type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} />
                            <InputError message={form.errors.email} />
                        </div>
                    </div>
                    {!user && (
                        <div className="space-y-2">
                            <Label htmlFor="user-password">পাসওয়ার্ড</Label>
                            <Input id="user-password" type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} autoComplete="new-password" />
                            <InputError message={form.errors.password} />
                        </div>
                    )}
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium">রোল</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {roles.map((role) => (
                                <label key={role} className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                    <Checkbox checked={form.data.roles.includes(role)} onCheckedChange={(checked) => form.setData('roles', checked ? [...form.data.roles, role] : form.data.roles.filter((item) => item !== role))} />
                                    {roleLabel(role)}
                                </label>
                            ))}
                        </div>
                        <InputError message={form.errors.roles} />
                    </fieldset>
                    {user && (
                        <label className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm">
                            চালু আছে (বন্ধ করলে সাথে সাথে লগ আউট হবে, আর লগইন করতে পারবেন না)
                            <Switch checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', checked)} />
                        </label>
                    )}
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
                            বাতিল
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {user ? 'সেভ করুন' : 'ব্যবহারকারী যোগ করুন'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Users({ users, roles }: { users: UserRow[]; roles: string[] }) {
    const [dialog, setDialog] = useState<{ user: UserRow | null } | null>(null);

    return (
        <>
            <Head title="ব্যবহারকারী" />
            <Page className="max-w-5xl">
                <PageHeader
                    title="ব্যবহারকারী"
                    description="যারা লগইন করতে পারেন এবং তাদের রোল।"
                    icon={UserCog}
                    tone="indigo"
                    actions={
                        <Button onClick={() => setDialog({ user: null })}>
                            <Plus /> নতুন ব্যবহারকারী
                        </Button>
                    }
                />
                <section className="surface overflow-hidden">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>ব্যবহারকারী</TableHead>
                                <TableHead>রোল</TableHead>
                                <TableHead>অবস্থা</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <p className="font-medium">{user.name}</p>
                                        <p className="text-xs text-muted-foreground">{user.email}</p>
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length === 0 && <span className="text-sm text-muted-foreground">কোনো রোল নেই</span>}
                                            {user.roles.map((role) => (
                                                <span key={role} className="rounded-full bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground">
                                                    {roleLabel(role)}
                                                </span>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="icon" className="size-8" aria-label={`${user.name} সম্পাদনা`} onClick={() => setDialog({ user })}>
                                            <Pencil className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </Page>
            {dialog && <UserDialog user={dialog.user} roles={roles} onClose={() => setDialog(null)} />}
        </>
    );
}

Users.layout = { breadcrumbs: [{ title: 'ব্যবহারকারী', href: index() }] };
