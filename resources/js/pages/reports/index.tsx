import { Head, Link } from '@inertiajs/react';
import { BarChart3, Boxes, CalendarDays, History, Palette, PackageX, Repeat, Ruler, Shirt, Undo2, Users } from 'lucide-react';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { index, show } from '@/routes/reports';

type Report = { key: string; title: string; description: string };

const ICONS: Record<string, typeof BarChart3> = {
    'daily-in-out': CalendarDays,
    'product-stock': Shirt,
    'size-stock': Ruler,
    'color-stock': Palette,
    'variant-stock': Boxes,
    returns: Undo2,
    damage: PackageX,
    'supplier-exchange': Repeat,
    'movement-history': History,
    'user-activity': Users,
};

const GROUPS = [
    { title: 'স্টকের হিসাব', keys: ['daily-in-out', 'product-stock', 'variant-stock', 'size-stock', 'color-stock'], tone: 'from-emerald-500 to-teal-600' },
    { title: 'মান যাচাই ও সরবরাহকারী', keys: ['returns', 'damage', 'supplier-exchange'], tone: 'from-amber-400 to-orange-500' },
    { title: 'নিরীক্ষা', keys: ['movement-history', 'user-activity'], tone: 'from-sky-500 to-blue-600' },
];

export default function ReportsIndex({ reports }: { reports: Report[] }) {
    return (
        <>
            <Head title="রিপোর্ট" />
            <Page>
                <PageHeader title="রিপোর্ট" description="সব রিপোর্ট সরাসরি খতিয়ান থেকে হিসাব করা হয়: শুরুর মজুদ, লেনদেন আর শেষের মজুদ। তারিখ ঢাকার সময় অনুযায়ী।" icon={BarChart3} tone="sky" />
                {GROUPS.map((group) => (
                    <section key={group.title} className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground">{group.title}</h2>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {group.keys
                                .map((key) => reports.find((report) => report.key === key))
                                .filter((report): report is Report => Boolean(report))
                                .map((report) => {
                                    const Icon = ICONS[report.key] ?? BarChart3;

                                    return (
                                        <Link key={report.key} href={show.url(report.key)} className="group surface flex gap-4 p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lift">
                                            <span className={`flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${group.tone} text-white shadow-lg transition-transform duration-300 group-hover:scale-110`}>
                                                <Icon className="size-5" />
                                            </span>
                                            <div>
                                                <p className="font-semibold group-hover:text-primary">{report.title}</p>
                                                <p className="mt-1 text-sm text-muted-foreground">{report.description}</p>
                                            </div>
                                        </Link>
                                    );
                                })}
                        </div>
                    </section>
                ))}
            </Page>
        </>
    );
}

ReportsIndex.layout = { breadcrumbs: [{ title: 'রিপোর্ট', href: index() }] };
