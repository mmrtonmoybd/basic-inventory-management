import { Head, Link } from '@inertiajs/react';
import { ArrowDownToLine, BadgeCheck, Clock, PackageCheck, Pencil, Repeat, Truck } from 'lucide-react';
import { StatCard } from '@/components/inventory/stat-card';
import { useState } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { StatusBadge } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { businessDate, dateTime, qty } from '@/lib/format';
import { show as claimShow } from '@/routes/claims';
import { show as movementShow } from '@/routes/movements';
import { index, show } from '@/routes/suppliers';
import { SupplierDialog, type SupplierRecord } from '@/components/inventory/supplier-dialog';

type Stats = { pending_claims: number; approved_claims: number; claimed: number; approved: number; received: number; outstanding: number; written_off: number; delivered: number };

type Props = {
    supplier: SupplierRecord;
    stats: Stats;
    claims: { id: number; claim_no: string; status: string; status_label: string; claimed_quantity: number; received_quantity: number; created_at: string | null }[];
    deliveries: { id: number; reference_no: string; business_date: string; total_quantity: number; external_reference: string | null }[];
};

export default function SupplierShow({ supplier, stats, claims, deliveries }: Props) {
    const can = useCan();
    const [editing, setEditing] = useState(false);

    const figures = [
        { label: 'অনুমোদনের অপেক্ষায় দাবি', value: stats.pending_claims, icon: Clock, tone: 'amber' as const },
        { label: 'অনুমোদিত দাবি', value: stats.approved_claims, icon: BadgeCheck, tone: 'teal' as const },
        { label: 'মোট দাবি করা পিস', value: stats.claimed, icon: Repeat, tone: 'violet' as const },
        { label: 'বদলি পাওয়া পিস', value: stats.received, icon: PackageCheck, tone: 'sky' as const },
        { label: 'এখনো তাদের কাছে', value: stats.outstanding, icon: Truck, tone: 'rose' as const },
        { label: 'সরবরাহ করা পিস (স্টক ইন)', value: stats.delivered, icon: ArrowDownToLine, tone: 'indigo' as const },
    ];

    return (
        <>
            <Head title={supplier.name} />
            <Page className="max-w-6xl">
                <PageHeader
                    title={supplier.name}
                    meta={
                        <>
                            <span>{supplier.code}</span>
                            <StatusBadge status={supplier.is_active ? 'active' : 'inactive'} />
                        </>
                    }
                    actions={
                        can('suppliers.manage') && (
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> সম্পাদনা
                            </Button>
                        )
                    }
                />

                <div className="stagger grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-6">
                    {figures.map((figure) => (
                        <StatCard key={figure.label} label={figure.label} value={figure.value} icon={figure.icon} tone={figure.tone} />
                    ))}
                </div>

                <Panel>
                    <dl className="grid gap-5 sm:grid-cols-4">
                        <Field label="যোগাযোগের ব্যক্তি" value={supplier.contact_person} />
                        <Field label="ফোন" value={supplier.phone} />
                        <Field label="ইমেইল" value={supplier.email} />
                        <Field label="ঠিকানা" value={supplier.address} />
                    </dl>
                    {supplier.notes && <p className="mt-4 border-t pt-4 text-sm text-muted-foreground">{supplier.notes}</p>}
                </Panel>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel title="বদলির ইতিহাস" icon={Repeat} tone="violet" bodyClassName="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>দাবি</TableHead>
                                    <TableHead>অবস্থা</TableHead>
                                    <TableHead className="text-right">দাবি করা</TableHead>
                                    <TableHead className="text-right">পাওয়া</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {claims.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                                            এই সরবরাহকারীর সাথে কোনো বদলির দাবি নেই।
                                        </TableCell>
                                    </TableRow>
                                )}
                                {claims.map((claim) => (
                                    <TableRow key={claim.id}>
                                        <TableCell>
                                            <Link href={claimShow.url(claim.id)} className="font-medium text-primary hover:underline">
                                                {claim.claim_no}
                                            </Link>
                                            <p className="text-xs text-muted-foreground">{dateTime(claim.created_at)}</p>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={claim.status} label={claim.status_label} />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{qty(claim.claimed_quantity)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{qty(claim.received_quantity)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>
                    <Panel title="সাম্প্রতিক সরবরাহ" icon={ArrowDownToLine} tone="teal" bodyClassName="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>তারিখ</TableHead>
                                    <TableHead>রেফারেন্স</TableHead>
                                    <TableHead className="text-right">পিস</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {deliveries.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="py-8 text-center text-muted-foreground">
                                            এই সরবরাহকারীর কোনো স্টক ইন নেই।
                                        </TableCell>
                                    </TableRow>
                                )}
                                {deliveries.map((delivery) => (
                                    <TableRow key={delivery.id}>
                                        <TableCell>{businessDate(delivery.business_date, 'long')}</TableCell>
                                        <TableCell>
                                            <Link href={movementShow.url(delivery.id)} className="font-medium text-primary hover:underline">
                                                {delivery.reference_no}
                                            </Link>
                                            {delivery.external_reference && <span className="ml-2 text-xs text-muted-foreground">{delivery.external_reference}</span>}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold tabular-nums">{qty(delivery.total_quantity)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>
                </div>
            </Page>
            {editing && <SupplierDialog supplier={supplier} open={editing} onOpenChange={setEditing} />}
        </>
    );
}

SupplierShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'সরবরাহকারী', href: index() },
        { title: props.supplier.name, href: show(props.supplier.id) },
    ],
});
