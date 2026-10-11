import { Head, router } from '@inertiajs/react';
import { CheckCircle2, ClipboardCheck, RefreshCw, TriangleAlert } from 'lucide-react';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { dateTime, qty, signedQty } from '@/lib/format';
import { index } from '@/routes/reconciliation';

type Issue = { variant_id: number; sku: string; product: string; variant: string; warehouse_id: number; condition: string; balance: number; ledger: number; difference: number };

export default function Reconciliation({ summary, issues, checkedAt }: { summary: { balances: number; ledger_entries: number; movements: number }; issues: Issue[]; checkedAt: string }) {
    const clean = issues.length === 0;

    return (
        <>
            <Head title="হিসাব মিলানো" />
            <Page className="max-w-5xl">
                <PageHeader
                    title="হিসাব মিলানো"
                    description="খতিয়ানের প্রতিটি এন্ট্রি থেকে স্টক আবার হিসাব করে, তারপর সংরক্ষিত স্টকের সাথে মিলিয়ে দেখে।"
                    icon={ClipboardCheck}
                    tone="teal"
                    actions={
                        <Button variant="outline" onClick={() => router.reload()}>
                            <RefreshCw /> আবার মিলিয়ে দেখুন
                        </Button>
                    }
                />
                <section className={clean ? 'flex items-start gap-4 rounded-2xl border border-available/40 bg-available-soft p-5 shadow-card' : 'flex items-start gap-4 rounded-2xl border border-damaged/40 bg-damaged-soft p-5 shadow-card'}>
                    {clean ? <CheckCircle2 className="size-6 shrink-0 text-available" /> : <TriangleAlert className="size-6 shrink-0 text-damaged" />}
                    <div>
                        <p className="font-semibold">{clean ? 'সব স্টক খতিয়ানের সাথে মিলেছে' : `${qty(issues.length)}টি জায়গায় খতিয়ানের সাথে অমিল পাওয়া গেছে`}</p>
                        <p className="text-sm text-muted-foreground">
                            {qty(summary.movements)}টি লেনদেনের {qty(summary.ledger_entries)}টি খতিয়ান এন্ট্রির সাথে {qty(summary.balances)}টি ভ্যারিয়েন্টের স্টক মিলিয়ে দেখা হয়েছে ({dateTime(checkedAt)})।
                        </p>
                        {!clean && <p className="mt-1 text-sm">স্টক শুধু সিস্টেমই বদলায়, তাই অমিল মানে কেউ সিস্টেমের বাইরে থেকে ডেটা বদলেছে। আর লেনদেন করার আগে বিষয়টি খতিয়ে দেখুন।</p>}
                    </div>
                </section>
                {!clean && (
                    <Panel title="অমিলের তালিকা" icon={TriangleAlert} tone="rose" bodyClassName="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>পণ্য</TableHead>
                                    <TableHead>ভ্যারিয়েন্ট</TableHead>
                                    <TableHead>অবস্থা</TableHead>
                                    <TableHead className="text-right">সংরক্ষিত স্টক</TableHead>
                                    <TableHead className="text-right">খতিয়ানের মোট</TableHead>
                                    <TableHead className="text-right">পার্থক্য</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {issues.map((issue) => (
                                    <TableRow key={`${issue.variant_id}-${issue.warehouse_id}-${issue.condition}`}>
                                        <TableCell className="font-medium">{issue.product}</TableCell>
                                        <TableCell>
                                            {issue.variant} <span className="text-xs text-muted-foreground">{issue.sku}</span>
                                        </TableCell>
                                        <TableCell>{issue.condition}</TableCell>
                                        <TableCell className="text-right tabular-nums">{qty(issue.balance)}</TableCell>
                                        <TableCell className="text-right tabular-nums">{qty(issue.ledger)}</TableCell>
                                        <TableCell className="text-right font-semibold text-damaged tabular-nums">{signedQty(issue.difference)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Panel>
                )}
            </Page>
        </>
    );
}

Reconciliation.layout = { breadcrumbs: [{ title: 'হিসাব মিলানো', href: index() }] };
