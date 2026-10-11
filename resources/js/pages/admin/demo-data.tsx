import { Head, router } from '@inertiajs/react';
import { DatabaseZap, FlaskConical, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Field, Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dateTime, qty } from '@/lib/format';
import { destroy, index, store } from '@/routes/admin/demo-data';

type Props = {
    status: { loaded: boolean; loaded_at: string | null; loaded_by: string | null };
    canLoad: boolean;
    counts: { products: number; variants: number; movements: number; suppliers: number };
    demoUsers: string[];
    confirmation: string;
};

export default function DemoData({ status, canLoad, counts, demoUsers, confirmation }: Props) {
    const [typed, setTyped] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const options = { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors, onSuccess: () => setTyped('') };

    return (
        <>
            <Head title="ডেমো ডেটা" />
            <Page className="max-w-3xl">
                <PageHeader title="ডেমো ডেটা" description="প্রশিক্ষণ ও পরীক্ষার জন্য নমুনা পণ্য, স্টক আর কাগজপত্র যোগ করুন। আসল কাজ শুরুর আগে সব ডেমো ডেটা এক ক্লিকে মুছে ফেলুন।" />

                <Panel>
                    <div className="flex items-start gap-4">
                        <div className={status.loaded ? 'rounded-lg bg-inspection-soft p-2.5 text-inspection-ink' : 'rounded-lg bg-available-soft p-2.5 text-available'}>
                            <FlaskConical className="size-5" />
                        </div>
                        <div className="space-y-1">
                            <p className="font-semibold">{status.loaded ? 'এখন ডেমো ডেটা চালু আছে' : 'ডেমো ডেটা নেই'}</p>
                            <p className="text-sm text-muted-foreground">
                                {status.loaded
                                    ? `${status.loaded_by ?? 'অ্যাডমিন'} ${dateTime(status.loaded_at)} সময়ে ডেমো ডেটা যোগ করেছেন। এই অবস্থায় যা কিছু যোগ করবেন, ডেমো মোছার সময় তাও মুছে যাবে।`
                                    : 'সিস্টেমে যা আছে সবই আসল ডেটা। আসল স্টকের ইতিহাস কখনো মোছা যায় না।'}
                            </p>
                        </div>
                    </div>
                    <dl className="mt-5 grid grid-cols-2 gap-4 border-t pt-4 sm:grid-cols-4">
                        <Field label="পণ্য" value={qty(counts.products)} />
                        <Field label="ভ্যারিয়েন্ট" value={qty(counts.variants)} />
                        <Field label="স্টক লেনদেন" value={qty(counts.movements)} />
                        <Field label="সরবরাহকারী" value={qty(counts.suppliers)} />
                    </dl>
                </Panel>

                {!status.loaded && (
                    <Panel title="ডেমো ডেটা যোগ করুন" description="শুধু খালি সিস্টেমে যোগ করা যায়, যাতে আসল ডেটার সাথে মিশে না যায়।">
                        <ul className="mb-4 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                            <li>৬টি পণ্য: সাইজ ও রং অনুযায়ী আলাদা ভ্যারিয়েন্টসহ</li>
                            <li>গত ৩০ দিনের স্টক ইন ও স্টক আউট</li>
                            <li>কাস্টমার রিটার্ন, ক্ষতিগ্রস্ত মাল আর সরবরাহকারীর কাছে বদলির দাবি</li>
                            <li>প্রতিটি দায়িত্বের জন্য একজন করে ডেমো কর্মী (পাসওয়ার্ড: password)</li>
                        </ul>
                        {errors.demo && <p className="mb-3 text-sm text-damaged">{errors.demo}</p>}
                        <Button disabled={!canLoad || processing} onClick={() => router.post(store.url(), {}, options)}>
                            <DatabaseZap /> ডেমো ডেটা যোগ করুন
                        </Button>
                        {!canLoad && <p className="mt-2 text-sm text-muted-foreground">সিস্টেমে আগে থেকেই পণ্য বা স্টক লেনদেন আছে, তাই ডেমো যোগ করা যাবে না।</p>}
                    </Panel>
                )}

                {status.loaded && (
                    <Panel title="ডেমো ডেটা মুছে ফেলুন" description="আসল কাজ শুরুর আগে এটি করুন। একবার মুছলে আর ফেরত আনা যাবে না।" className="border-damaged/40">
                        <div className="space-y-4">
                            <div className="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <p className="mb-1 font-semibold text-damaged">যা মুছে যাবে</p>
                                    <ul className="list-disc space-y-0.5 pl-5 text-muted-foreground">
                                        <li>সব পণ্য, ভ্যারিয়েন্ট ও ক্যাটাগরি</li>
                                        <li>সব স্টক লেনদেন ও লেজার</li>
                                        <li>রিটার্ন, ক্ষতি, বদলির দাবি, স্টক সমন্বয়</li>
                                        <li>সরবরাহকারী, নোটিফিকেশন ও কাজের ইতিহাস</li>
                                        <li>ডেমো কর্মীদের অ্যাকাউন্ট</li>
                                    </ul>
                                </div>
                                <div>
                                    <p className="mb-1 font-semibold text-available">যা থাকবে</p>
                                    <ul className="list-disc space-y-0.5 pl-5 text-muted-foreground">
                                        <li>আপনার অ্যাকাউন্ট ও অন্য আসল ব্যবহারকারী</li>
                                        <li>রোল ও অনুমতি</li>
                                        <li>সাইজ, রং, কাপড়ের তালিকা</li>
                                        <li>গুদাম</li>
                                    </ul>
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">ডেমো কর্মী: {demoUsers.join(', ')}</p>
                            <div className="space-y-2">
                                <label htmlFor="confirmation" className="text-sm">
                                    নিশ্চিত করতে নিচের ঘরে লিখুন: <span className="font-bold">{confirmation}</span>
                                </label>
                                <Input id="confirmation" value={typed} onChange={(event) => setTyped(event.target.value)} className="max-w-xs" autoComplete="off" />
                                {errors.confirmation && <p className="text-sm text-damaged">{errors.confirmation}</p>}
                                {errors.demo && <p className="text-sm text-damaged">{errors.demo}</p>}
                            </div>
                            <Button variant="destructive" disabled={typed.trim() !== confirmation || processing} onClick={() => router.delete(destroy.url(), { ...options, data: { confirmation: typed.trim() } })}>
                                <Trash2 /> সব ডেমো ডেটা মুছে ফেলুন
                            </Button>
                        </div>
                    </Panel>
                )}
            </Page>
        </>
    );
}

DemoData.layout = { breadcrumbs: [{ title: 'ডেমো ডেটা', href: index() }] };
