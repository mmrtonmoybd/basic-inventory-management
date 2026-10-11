import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileDown, FileUp, Info, TriangleAlert, Upload } from 'lucide-react';
import { useState } from 'react';
import { Page, PageHeader, Panel } from '@/components/inventory/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { qty } from '@/lib/format';
import { create, store, template } from '@/routes/import';
import type { Option } from '@/types';

type Result = { valid: boolean; committed: boolean; errors: { row: number; message: string }[]; summary: { rows: number; new_products: number; new_variants: number; opening_units: number } };

export default function ImportPage({ warehouses, header }: { warehouses: Option[]; header: string[] }) {
    const result = usePage().flash?.importResult as Result | undefined;
    const [file, setFile] = useState<File | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const warehouseId = (warehouses.find((warehouse) => warehouse.is_default) ?? warehouses[0])?.id;

    const send = (commit: boolean) => {
        if (!file) {
            return;
        }

        router.post(store.url(), { file, warehouse_id: warehouseId, commit }, { forceFormData: true, preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false), onError: setErrors, onSuccess: () => commit && setFile(null) });
    };

    return (
        <>
            <Head title="CSV আমদানি" />
            <Page className="max-w-4xl">
                <PageHeader
                    title="CSV ফাইল থেকে একসাথে আমদানি"
                    description="একবারে অনেক পণ্য, ভ্যারিয়েন্ট আর শুরুর মজুদ যোগ করুন। আগে পুরো ফাইল যাচাই হবে; একটি লাইনেও ভুল থাকলে কিছুই সেভ হবে না।"
                    icon={FileUp}
                    tone="teal"
                    actions={
                        <Button variant="outline" asChild>
                            <a href={template.url()}>
                                <FileDown /> নমুনা ফাইল নামান
                            </a>
                        </Button>
                    }
                />
                <Panel title="ফাইলের নিয়ম" icon={Info} tone="sky">
                    <p className="text-sm text-muted-foreground">এই কলামগুলো লাগবে (যেকোনো ক্রমে):</p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {header.map((column) => (
                            <code key={column} className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                {column}
                            </code>
                        ))}
                    </div>
                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li>
                            বৈশিষ্ট্য লিখুন এভাবে: <code className="text-foreground">সাইজ:L|রং:লাল</code>। মানগুলো আগে থেকেই সেটআপ &gt; সাইজ, রং পাতায় থাকতে হবে।
                        </li>
                        <li>সাইজ-রঙ ছাড়া পণ্যের জন্য attributes ঘর খালি রাখুন (শুধু একটি লাইন)।</li>
                        <li>SKU খালি রাখলে নিজে থেকে তৈরি হবে। আগে থেকে আছে এমন পণ্যের কোড দিলে সেই পণ্যে নতুন ভ্যারিয়েন্ট যোগ হবে।</li>
                    </ul>
                </Panel>
                <Panel title="ফাইল আপলোড" icon={Upload} tone="indigo">
                    <div className="space-y-4">
                        <Label htmlFor="file" className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed px-6 py-10 text-center hover:bg-muted/50">
                            <FileUp className="size-6 text-muted-foreground" />
                            <span className="font-medium">{file ? file.name : 'একটি CSV ফাইল বাছুন'}</span>
                            <span className="text-xs text-muted-foreground">সর্বোচ্চ ৫ মেগাবাইট</span>
                            <input id="file" type="file" accept=".csv,text/csv" className="sr-only" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
                        </Label>
                        {errors.file && <p className="text-sm text-damaged">{errors.file}</p>}
                        <div className="flex gap-2">
                            <Button variant="outline" disabled={!file || processing} onClick={() => send(false)}>
                                ফাইল যাচাই করুন
                            </Button>
                            <Button disabled={!file || processing || !result?.valid} onClick={() => send(true)} title={!result?.valid ? 'আগে ফাইল যাচাই করুন' : undefined}>
                                আমদানি করুন
                            </Button>
                        </div>
                    </div>
                </Panel>
                {result && (
                    <Panel title={result.committed ? 'আমদানি সম্পন্ন' : result.valid ? 'ফাইল ঠিক আছে' : 'ফাইলে ভুল আছে'}>
                        <div className="flex items-start gap-3">
                            {result.valid ? <CheckCircle2 className="size-5 shrink-0 text-available" /> : <TriangleAlert className="size-5 shrink-0 text-damaged" />}
                            <p className="text-sm">
                                {qty(result.summary.rows)}টি লাইন, {qty(result.summary.new_products)}টি নতুন পণ্য, {qty(result.summary.new_variants)}টি ভ্যারিয়েন্ট, {qty(result.summary.opening_units)} পিস শুরুর মজুদ।
                                {result.valid && !result.committed && ' সেভ করতে “আমদানি করুন” চাপুন।'}
                            </p>
                        </div>
                        {result.errors.length > 0 && (
                            <ul className="mt-4 divide-y rounded-lg border text-sm">
                                {result.errors.map((error, index) => (
                                    <li key={index} className="flex gap-4 px-4 py-2">
                                        <span className="w-20 shrink-0 font-semibold text-damaged">লাইন {qty(error.row)}</span>
                                        <span>{error.message}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                )}
            </Page>
        </>
    );
}

ImportPage.layout = { breadcrumbs: [{ title: 'CSV আমদানি', href: create() }] };
