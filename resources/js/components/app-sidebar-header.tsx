import { Link, usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { GlobalSearch } from '@/components/global-search';
import { NotificationBell } from '@/components/notification-bell';
import { ThemeToggle } from '@/components/theme-toggle';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as demoDataIndex } from '@/routes/admin/demo-data';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function DemoBanner() {
    const { demoMode, auth } = usePage().props;

    if (!demoMode) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-inspection/30 bg-inspection-soft px-4 py-1.5 text-center text-sm text-inspection-ink">
            <FlaskConical className="size-4" />
            <span>ডেমো মোড চালু আছে। এখানকার পণ্য ও স্টক নমুনা, আসল নয়।</span>
            {auth.permissions.includes('*') && (
                <Link href={demoDataIndex.url()} className="font-semibold underline underline-offset-2">
                    আসল কাজ শুরুর আগে মুছে ফেলুন
                </Link>
            )}
        </div>
    );
}

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    return (
        <>
        <DemoBanner />
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-xl md:px-6">
            <SidebarTrigger className="-ml-1" />
            <div className="hidden min-w-0 flex-1 lg:block">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex flex-1 items-center justify-end gap-1 lg:flex-none">
                <GlobalSearch />
                <NotificationBell />
                <ThemeToggle />
            </div>
        </header>
        </>
    );
}
