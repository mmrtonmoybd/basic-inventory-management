import { Head, router, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Fragment, useState } from 'react';
import { qty, roleLabel } from '@/lib/format';
import InputError from '@/components/input-error';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { index, store, update } from '@/routes/admin/roles';

type Role = { id: number; name: string; users_count: number; is_super_admin: boolean; permissions: string[] };
type Permission = { name: string; label: string; group: string };

export default function Roles({ roles, permissions }: { roles: Role[]; permissions: Permission[] }) {
    const [matrix, setMatrix] = useState<Record<number, string[]>>(Object.fromEntries(roles.map((role) => [role.id, role.permissions])));
    const [dirty, setDirty] = useState<number[]>([]);
    const form = useForm({ name: '' });
    const groups = [...new Set(permissions.map((permission) => permission.group))];

    const toggle = (role: Role, permission: string) => {
        setMatrix((current) => {
            const granted = current[role.id] ?? role.permissions;

            return { ...current, [role.id]: granted.includes(permission) ? granted.filter((item) => item !== permission) : [...granted, permission] };
        });
        setDirty((current) => [...new Set([...current, role.id])]);
    };

    const save = () => {
        const pending = [...dirty];
        const next = () => {
            const roleId = pending.shift();

            if (roleId === undefined) {
                setDirty([]);

                return;
            }

            router.put(update.url(roleId), { permissions: matrix[roleId] }, { preserveScroll: true, preserveState: true, onSuccess: next });
        };
        next();
    };

    return (
        <>
            <Head title="রোল ও অনুমতি" />
            <Page>
                <PageHeader
                    title="রোল ও অনুমতি"
                    description="কোন রোল কী করতে পারবে তাতে টিক দিন। সুপার অ্যাডমিনের সব অনুমতি সবসময় থাকে। পরিবর্তন সাথে সাথে কার্যকর হয়।"
                    icon={ShieldCheck}
                    tone="indigo"
                    actions={
                        <Button onClick={save} disabled={dirty.length === 0}>
                            {dirty.length > 0 ? `${qty(dirty.length)}টি রোল সেভ করুন` : 'সেভ করুন'}
                        </Button>
                    }
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(), { preserveScroll: true, onSuccess: () => form.reset() });
                    }}
                    className="flex items-start gap-2"
                >
                    <div>
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="নতুন রোলের নাম" className="w-56" aria-label="রোলের নাম" />
                        <InputError message={form.errors.name} />
                    </div>
                    <Button type="submit" variant="outline" disabled={!form.data.name || form.processing}>
                        রোল যোগ করুন
                    </Button>
                </form>
                <div className="surface overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/40">
                                <th className="sticky left-0 z-10 min-w-72 bg-muted/40 px-4 py-3 text-left text-xs font-semibold text-muted-foreground">অনুমতি</th>
                                {roles.map((role) => (
                                    <th key={role.id} className="min-w-28 px-2 py-3 text-center text-xs font-semibold">
                                        {roleLabel(role.name)}
                                        <span className="block font-normal text-muted-foreground">
                                            {qty(role.users_count)} জন
                                        </span>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map((group) => (
                                <Fragment key={group}>
                                    <tr className="border-b bg-muted/20">
                                        <th colSpan={roles.length + 1} className="sticky left-0 px-4 py-2 text-left text-xs font-semibold text-muted-foreground">
                                            {group}
                                        </th>
                                    </tr>
                                    {permissions
                                        .filter((permission) => permission.group === group)
                                        .map((permission) => (
                                            <tr key={permission.name} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="sticky left-0 z-10 bg-card px-4 py-2.5">{permission.label}</td>
                                                {roles.map((role) => (
                                                    <td key={role.id} className="px-2 py-2.5 text-center">
                                                        <Checkbox
                                                            checked={role.is_super_admin || (matrix[role.id] ?? role.permissions).includes(permission.name)}
                                                            disabled={role.is_super_admin}
                                                            onCheckedChange={() => toggle(role, permission.name)}
                                                            aria-label={`${roleLabel(role.name)}: ${permission.label}`}
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Page>
        </>
    );
}

Roles.layout = { breadcrumbs: [{ title: 'রোল ও অনুমতি', href: index() }] };
