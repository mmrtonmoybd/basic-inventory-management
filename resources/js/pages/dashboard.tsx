import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownToLine,
    ArrowUpFromLine,
    BadgeCheck,
    CheckCircle2,
    Clock,
    Layers,
    PackageCheck,
    PackageX,
    ShieldAlert,
    Shirt,
    Truck,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, Tooltip, XAxis, YAxis } from 'recharts';
import { axisProps, ChartLegend, ChartTooltip, compactTick, gridProps } from '@/components/inventory/chart-kit';
import { CONDITIONS } from '@/components/inventory/conditions';
import { CountUp } from '@/components/inventory/count-up';
import { EmptyState } from '@/components/inventory/empty-state';
import { Page, Panel } from '@/components/inventory/page-header';
import { StatCard } from '@/components/inventory/stat-card';
import { MovementType } from '@/components/inventory/status-badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCan } from '@/hooks/use-can';
import { businessDate, longDate, qty, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as adjustmentsIndex } from '@/routes/adjustments';
import { index as claimsIndex } from '@/routes/claims';
import { index as damageIndex } from '@/routes/damage';
import { show as movementShow, index as movementsIndex } from '@/routes/movements';
import { index as productsIndex, show as productShow } from '@/routes/products';
import { index as returnsIndex } from '@/routes/returns';
import { create as stockCreate } from '@/routes/stock';

type Metrics = {
    total_products: number;
    total_variants: number;
    available: number;
    inspection: number;
    damaged: number;
    with_supplier: number;
    physical: number;
    today_in: number;
    today_out: number;
    returns_month: number;
    returns_today: number;
    pending_claims: number;
    pending_adjustments: number;
    low_stock: number;
};

type Charts = {
    daily: { date: string; in: number; out: number; returns: number; damage: number }[];
    by_product: { product: string; available: number; inspection: number; damaged: number; with_supplier: number }[];
    by_attribute: { attribute: string; is_color: boolean; values: { value: string; swatch: string | null; available: number }[] }[];
    claims_by_status: { status: string; key: string; total: number }[];
    top_moving: { sku: string; label: string; product_id: number; quantity: number }[];
};

type Props = {
    restricted: boolean;
    metrics: Metrics | null;
    lowStock: { id: number; product_id: number; product: string; sku: string; variant: string; available: number; threshold: number }[];
    recentMovements: { id: number; reference_no: string; type: string; type_label: string; total_quantity: number; business_date: string; created_by: string | null; created_at: string }[];
    charts?: Charts | null;
};

const CONDITION_COLOR: Record<string, string> = { available: 'var(--available)', inspection: 'var(--inspection)', damaged: 'var(--damaged)', with_supplier: 'var(--supplier)' };

function greeting(): string {
    const hour = Number(new Intl.DateTimeFormat('en-GB', { hour: 'numeric', hour12: false, timeZone: 'Asia/Dhaka' }).format(new Date()));

    return hour < 12 ? 'শুভ সকাল' : hour < 17 ? 'শুভ দুপুর' : 'শুভ সন্ধ্যা';
}

export default function Dashboard({ restricted, metrics, lowStock, recentMovements }: Props) {
    const can = useCan();
    const { auth } = usePage().props;

    return (
        <>
            <Head title="ড্যাশবোর্ড" />
            <Page>
                {restricted || !metrics ? (
                    <section className="surface">
                        <EmptyState icon={ShieldAlert} title="এখনো স্টক দেখার অনুমতি নেই" description="আপনার অ্যাকাউন্টে স্টক দেখার মতো কোনো রোল দেওয়া হয়নি। অ্যাডমিনকে বলুন সেটআপ > ব্যবহারকারী পাতা থেকে একটি রোল দিতে।" />
                    </section>
                ) : (
                    <>
                        <Hero metrics={metrics} name={auth.user.name} canIn={can('stock.in')} canOut={can('stock.out')} />

                        <div className="stagger grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-5">
                            <StatCard label="মোট পণ্য" value={metrics.total_products} icon={Shirt} tone="indigo" href={productsIndex.url()} />
                            <StatCard label="মোট ভ্যারিয়েন্ট" value={metrics.total_variants} icon={Layers} tone="violet" hint="সাইজ ও রঙের কম্বিনেশন" />
                            <StatCard label="বিক্রয়যোগ্য স্টক" value={metrics.available} icon={PackageCheck} tone="teal" hint="এখনই বিক্রি করা যাবে" />
                            <StatCard label="আজকের স্টক ইন" value={metrics.today_in} icon={ArrowDownToLine} tone="sky" href={movementsIndex.url({ query: { type: 'stock_in' } })} />
                            <StatCard label="আজকের স্টক আউট" value={metrics.today_out} icon={ArrowUpFromLine} tone="indigo" href={movementsIndex.url({ query: { type: 'stock_out' } })} />
                            <StatCard label="এই মাসের রিটার্ন" value={metrics.returns_month} icon={Undo2} tone="amber" hint={`আজ ${qty(metrics.returns_today)} পিস`} href={returnsIndex.url()} />
                            <StatCard label="ক্ষতিগ্রস্ত মাল" value={metrics.damaged} icon={PackageX} tone="rose" href={damageIndex.url()} />
                            <StatCard label="সরবরাহকারীর কাছে" value={metrics.with_supplier} icon={Truck} tone="violet" hint="বদলির জন্য পাঠানো" href={claimsIndex.url({ query: { status: 'open' } })} />
                            <StatCard
                                label="অনুমোদনের অপেক্ষায়"
                                value={metrics.pending_claims + metrics.pending_adjustments}
                                icon={Clock}
                                tone="amber"
                                alert
                                hint={`${qty(metrics.pending_claims)}টি দাবি, ${qty(metrics.pending_adjustments)}টি সমন্বয়`}
                                href={metrics.pending_claims ? claimsIndex.url({ query: { status: 'open' } }) : adjustmentsIndex.url({ query: { status: 'pending' } })}
                            />
                            <StatCard label="স্টক কম এমন ভ্যারিয়েন্ট" value={metrics.low_stock} icon={AlertTriangle} tone="rose" alert hint="সর্বনিম্ন সীমায় বা তার নিচে" />
                        </div>

                        {can('reports.view') && (
                            <Deferred data="charts" fallback={<ChartsSkeleton />}>
                                <ChartsSection />
                            </Deferred>
                        )}

                        <div className="grid items-start gap-6 xl:grid-cols-2">
                            <Panel title="স্টক কমে যাওয়ার সতর্কতা" description="যে ভ্যারিয়েন্টের বিক্রয়যোগ্য স্টক সর্বনিম্ন সীমায় পৌঁছেছে" icon={AlertTriangle} tone="rose" bodyClassName="p-0">
                                {lowStock.length === 0 ? (
                                    <div className="flex items-center gap-3 px-5 py-8 text-sm text-muted-foreground">
                                        <CheckCircle2 className="size-5 text-available" />
                                        সব ভ্যারিয়েন্টের স্টক সর্বনিম্ন সীমার উপরে আছে।
                                    </div>
                                ) : (
                                    <ul className="divide-y">
                                        {lowStock.map((item) => (
                                            <li key={item.id}>
                                                <Link href={productShow.url(item.product_id)} className="flex items-center gap-4 px-5 py-3 transition-colors hover:bg-accent/50">
                                                    <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-lg', item.available === 0 ? 'bg-damaged-soft text-damaged' : 'bg-inspection-soft text-inspection-ink')}>
                                                        <AlertTriangle className="size-4" />
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-semibold">
                                                            {item.product} <span className="font-normal text-muted-foreground">/ {item.variant}</span>
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">{item.sku}</p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className={cn('text-base font-bold', item.available === 0 && 'text-damaged')}>{qty(item.available)}</p>
                                                        <p className="text-[11px] text-muted-foreground">সীমা {qty(item.threshold)}</p>
                                                    </div>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>

                            <Panel
                                title="সাম্প্রতিক স্টক লেনদেন"
                                icon={BadgeCheck}
                                tone="sky"
                                actions={
                                    can('movements.view') && (
                                        <Link href={movementsIndex.url()} className="text-sm font-semibold text-primary hover:underline">
                                            সব দেখুন
                                        </Link>
                                    )
                                }
                                bodyClassName="p-0"
                            >
                                {recentMovements.length === 0 ? (
                                    <p className="px-5 py-8 text-center text-sm text-muted-foreground">এখনো কোনো লেনদেন হয়নি।</p>
                                ) : (
                                    <ul className="divide-y">
                                        {recentMovements.map((movement) => (
                                            <li key={movement.id}>
                                                <Link href={movementShow.url(movement.id)} className="flex items-center gap-4 px-5 py-3 transition-colors hover:bg-accent/50">
                                                    <div className="min-w-0 flex-1 space-y-1">
                                                        <MovementType type={movement.type} label={movement.type_label} />
                                                        <p className="truncate text-xs text-muted-foreground">
                                                            {movement.reference_no}, {movement.created_by ?? 'সিস্টেম'}, {relativeTime(movement.created_at)}
                                                        </p>
                                                    </div>
                                                    <p className="text-sm font-bold">{qty(movement.total_quantity)} পিস</p>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>
                    </>
                )}
            </Page>
        </>
    );
}

function Hero({ metrics, name, canIn, canOut }: { metrics: Metrics; name: string; canIn: boolean; canOut: boolean }) {
    const physical = metrics.available + metrics.inspection + metrics.damaged || 1;

    return (
        <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#312e81] via-[#4338ca] to-[#7c3aed] p-6 text-white shadow-[0_24px_48px_-20px_rgb(67_56_202/0.6)] sm:p-8">
            <div className="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-fuchsia-400/30 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-28 left-1/3 size-72 rounded-full bg-sky-400/25 blur-3xl" />
            <div className="relative grid gap-8 lg:grid-cols-[1fr_1.25fr] lg:items-end">
                <div>
                    <p className="text-sm text-indigo-100/90">{longDate()}</p>
                    <h1 className="mt-1 text-2xl font-bold sm:text-3xl">
                        {greeting()}, {name.split(' ')[0]}
                    </h1>
                    <p className="mt-6 text-sm text-indigo-100/90">গুদামে মোট মজুদ</p>
                    <p className="text-5xl leading-none font-bold tracking-tight sm:text-6xl">
                        <CountUp value={metrics.physical} duration={1200} />
                        <span className="ml-2 text-xl font-semibold text-indigo-100/80">পিস</span>
                    </p>
                    <div className="mt-6 flex flex-wrap gap-2">
                        {canIn && (
                            <Button asChild className="bg-white bg-none text-indigo-700 shadow-lg hover:bg-indigo-50">
                                <Link href={stockCreate.url('in')}>
                                    <ArrowDownToLine /> স্টক ইন
                                </Link>
                            </Button>
                        )}
                        {canOut && (
                            <Button asChild variant="outline" className="border-white/30 bg-white/10 text-white backdrop-blur hover:bg-white/20 hover:text-white">
                                <Link href={stockCreate.url('out')}>
                                    <ArrowUpFromLine /> স্টক আউট
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                <div className="space-y-4 rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur">
                    <div className="flex h-4 gap-1">
                        <div className="flex min-w-0 flex-1 animate-grow-x gap-px overflow-hidden rounded-full bg-white/15">
                            {CONDITIONS.filter((condition) => condition.physical).map((condition) =>
                                metrics[condition.key] > 0 ? <div key={condition.key} className={cn(condition.bar, 'h-full')} style={{ width: `${(metrics[condition.key] / physical) * 100}%`, minWidth: 4 }} /> : null,
                            )}
                        </div>
                        {metrics.with_supplier > 0 && <div className="hatch w-6 rounded-full border border-white/50 text-white" title="সরবরাহকারীর কাছে" />}
                    </div>
                    <dl className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                        {CONDITIONS.map((condition) => (
                            <div key={condition.key}>
                                <dt className="flex items-center gap-2 text-xs text-indigo-100/90">
                                    <span className={cn('size-2.5 rounded-full', condition.physical ? condition.bar : 'hatch border border-white/70 text-white')} />
                                    {condition.label}
                                </dt>
                                <dd className="mt-1 text-2xl font-bold">
                                    <CountUp value={metrics[condition.key]} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <p className="text-xs text-indigo-100/80">গুদামের মোট মজুদ = বিক্রয়যোগ্য + পরীক্ষাধীন + ক্ষতিগ্রস্ত। সরবরাহকারীর কাছে থাকা মাল আলাদা ধরা হয়।</p>
                </div>
            </div>
        </section>
    );
}

function ChartsSkeleton() {
    return (
        <div className="grid gap-6 xl:grid-cols-3">
            <Skeleton className="h-80 rounded-2xl xl:col-span-2" />
            <Skeleton className="h-80 rounded-2xl" />
            <Skeleton className="h-72 rounded-2xl xl:col-span-2" />
            <Skeleton className="h-72 rounded-2xl" />
        </div>
    );
}

function ChartsSection() {
    const { charts } = usePage<{ charts: Charts }>().props;

    return (
        <div className="stagger grid gap-6 xl:grid-cols-3">
            <Panel
                title="স্টক ইন বনাম স্টক আউট"
                description="গত ৩০ দিনে প্রতিদিন কত পিস"
                icon={ArrowDownToLine}
                tone="teal"
                className="xl:col-span-2"
                actions={
                    <ChartLegend
                        items={[
                            { label: 'ইন', color: 'var(--stock-in)' },
                            { label: 'আউট', color: 'var(--stock-out)' },
                        ]}
                    />
                }
            >
                <div className="h-64">
                    <BarChart responsive style={{ width: '100%', height: '100%' }} data={charts.daily} margin={{ top: 8, right: 8, left: -8, bottom: 0 }} barGap={2} barCategoryGap="22%">
                        <CartesianGrid {...gridProps} />
                        <XAxis dataKey="date" {...axisProps} tickFormatter={(value: string) => businessDate(value)} minTickGap={24} />
                        <YAxis {...axisProps} axisLine={false} tickFormatter={compactTick} width={44} allowDecimals={false} />
                        <Tooltip content={<ChartTooltip labelFormatter={(value) => businessDate(value, 'long')} />} cursor={{ fill: 'var(--muted)', opacity: 0.6 }} />
                        <Bar dataKey="in" name="স্টক ইন" fill="var(--stock-in)" radius={[4, 4, 0, 0]} maxBarSize={12} />
                        <Bar dataKey="out" name="স্টক আউট" fill="var(--stock-out)" radius={[4, 4, 0, 0]} maxBarSize={12} />
                    </BarChart>
                </div>
            </Panel>

            <Panel title="সবচেয়ে বেশি বিক্রি হওয়া" description="গত ৩০ দিনের স্টক আউট" icon={BadgeCheck} tone="indigo">
                {charts.top_moving.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">গত ৩০ দিনে কোনো স্টক আউট হয়নি।</p>
                ) : (
                    <ol className="space-y-3.5">
                        {charts.top_moving.map((item, index) => (
                            <li key={item.sku}>
                                <Link href={productShow.url(item.product_id)} className="group block">
                                    <div className="flex items-baseline justify-between gap-3 text-sm">
                                        <span className="truncate group-hover:text-primary">
                                            <span className="mr-1.5 text-xs font-bold text-muted-foreground">{qty(index + 1)}.</span>
                                            {item.label}
                                        </span>
                                        <span className="font-bold">{qty(item.quantity)}</span>
                                    </div>
                                    <div className="mt-1.5 h-1.5 rounded-full bg-muted">
                                        <div className="h-full animate-grow-x rounded-full bg-gradient-to-r from-indigo-500 to-violet-500" style={{ width: `${(item.quantity / charts.top_moving[0].quantity) * 100}%` }} />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ol>
                )}
            </Panel>

            <Panel
                title="পণ্য অনুযায়ী স্টক"
                description="বিক্রয়যোগ্য স্টক বেশি এমন ১০টি পণ্য"
                icon={Shirt}
                tone="violet"
                className="xl:col-span-2"
                actions={<ChartLegend items={CONDITIONS.map((condition) => ({ label: condition.label, color: CONDITION_COLOR[condition.key] }))} />}
            >
                <div style={{ height: Math.max(180, charts.by_product.length * 36 + 30) }}>
                    <BarChart responsive style={{ width: '100%', height: '100%' }} data={charts.by_product} layout="vertical" margin={{ top: 0, right: 16, left: 8, bottom: 0 }} barSize={16}>
                        <CartesianGrid {...gridProps} vertical horizontal={false} />
                        <XAxis type="number" {...axisProps} tickFormatter={compactTick} allowDecimals={false} />
                        <YAxis type="category" dataKey="product" {...axisProps} axisLine={false} width={170} tick={{ fill: 'var(--foreground)', fontSize: 12 }} />
                        <Tooltip content={<ChartTooltip />} cursor={{ fill: 'var(--muted)', opacity: 0.5 }} />
                        {CONDITIONS.map((condition, index) => (
                            <Bar key={condition.key} dataKey={condition.key} name={condition.label} stackId="stock" fill={CONDITION_COLOR[condition.key]} stroke="var(--card)" strokeWidth={2} radius={index === CONDITIONS.length - 1 ? [0, 4, 4, 0] : 0} />
                        ))}
                    </BarChart>
                </div>
            </Panel>

            <AttributeStock groups={charts.by_attribute} />

            <Panel
                title="রিটার্ন ও ক্ষতির প্রবণতা"
                description="গত ৩০ দিনে প্রতিদিন কত পিস"
                icon={Undo2}
                tone="amber"
                className="xl:col-span-2"
                actions={
                    <ChartLegend
                        items={[
                            { label: 'রিটার্ন', color: 'var(--inspection)' },
                            { label: 'ক্ষতিগ্রস্ত', color: 'var(--damaged)' },
                        ]}
                    />
                }
            >
                <div className="h-52">
                    <LineChart responsive style={{ width: '100%', height: '100%' }} data={charts.daily} margin={{ top: 8, right: 8, left: -8, bottom: 0 }}>
                        <CartesianGrid {...gridProps} />
                        <XAxis dataKey="date" {...axisProps} tickFormatter={(value: string) => businessDate(value)} minTickGap={24} />
                        <YAxis {...axisProps} axisLine={false} width={44} allowDecimals={false} tickFormatter={compactTick} />
                        <Tooltip content={<ChartTooltip labelFormatter={(value) => businessDate(value, 'long')} />} cursor={{ stroke: 'var(--muted-foreground)', strokeWidth: 1 }} />
                        <Line type="linear" dataKey="returns" name="রিটার্ন" stroke="var(--inspection)" strokeWidth={2} dot={false} activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--card)' }} />
                        <Line type="linear" dataKey="damage" name="ক্ষতিগ্রস্ত" stroke="var(--damaged)" strokeWidth={2} dot={false} activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--card)' }} />
                    </LineChart>
                </div>
            </Panel>

            <Panel title="বদলির দাবির অবস্থা" description="এখন কোন অবস্থায় কতটি দাবি" icon={Truck} tone="violet">
                {charts.claims_by_status.length === 0 ? (
                    <p className="py-10 text-center text-sm text-muted-foreground">এখনো কোনো বদলির দাবি নেই।</p>
                ) : (
                    <div style={{ height: Math.max(150, charts.claims_by_status.length * 36 + 20) }}>
                        <BarChart responsive style={{ width: '100%', height: '100%' }} data={charts.claims_by_status} layout="vertical" margin={{ top: 0, right: 28, left: 0, bottom: 0 }} barSize={16}>
                            <XAxis type="number" hide allowDecimals={false} />
                            <YAxis type="category" dataKey="status" {...axisProps} axisLine={false} width={130} tick={{ fill: 'var(--foreground)', fontSize: 12 }} />
                            <Tooltip content={<ChartTooltip />} cursor={{ fill: 'var(--muted)', opacity: 0.5 }} />
                            <Bar dataKey="total" name="দাবি" fill="var(--supplier)" radius={[0, 4, 4, 0]} label={{ position: 'right', fill: 'var(--foreground)', fontSize: 12, formatter: (value: unknown) => qty(Number(value)) }} />
                        </BarChart>
                    </div>
                )}
            </Panel>
        </div>
    );
}

function AttributeStock({ groups }: { groups: Charts['by_attribute'] }) {
    const [active, setActive] = useState(groups[0]?.attribute);
    const group = groups.find((item) => item.attribute === active) ?? groups[0];

    return (
        <Panel
            title="সাইজ ও রং অনুযায়ী"
            description="সব পণ্যের বিক্রয়যোগ্য স্টক"
            icon={Layers}
            tone="sky"
            actions={
                groups.length > 1 && (
                    <div className="flex rounded-lg bg-muted p-0.5 text-xs" role="tablist">
                        {groups.map((item) => (
                            <button
                                key={item.attribute}
                                type="button"
                                role="tab"
                                aria-selected={item.attribute === group?.attribute}
                                onClick={() => setActive(item.attribute)}
                                className={cn('rounded-md px-2.5 py-1 font-semibold text-muted-foreground transition-all', item.attribute === group?.attribute && 'bg-card text-foreground shadow-sm')}
                            >
                                {item.attribute}
                            </button>
                        ))}
                    </div>
                )
            }
        >
            {!group ? (
                <p className="py-10 text-center text-sm text-muted-foreground">এখনো সাইজ বা রঙ অনুযায়ী কোনো ভ্যারিয়েন্ট নেই।</p>
            ) : (
                <div className="h-56">
                    <BarChart responsive style={{ width: '100%', height: '100%' }} data={group.values} margin={{ top: 16, right: 4, left: -16, bottom: 0 }} barSize={22}>
                        <CartesianGrid {...gridProps} />
                        <XAxis dataKey="value" {...axisProps} interval={0} />
                        <YAxis {...axisProps} axisLine={false} tickFormatter={compactTick} allowDecimals={false} />
                        <Tooltip content={<ChartTooltip />} cursor={{ fill: 'var(--muted)', opacity: 0.5 }} />
                        <Bar dataKey="available" name="বিক্রয়যোগ্য" radius={[4, 4, 0, 0]} fill="var(--available)">
                            {group.values.map((value) => (
                                <Cell key={value.value} fill={group.is_color && value.swatch ? value.swatch : 'var(--available)'} stroke={group.is_color ? 'var(--border)' : undefined} />
                            ))}
                        </Bar>
                    </BarChart>
                </div>
            )}
        </Panel>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'ড্যাশবোর্ড', href: dashboard() }],
};
