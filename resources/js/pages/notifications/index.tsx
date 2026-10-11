import { Head, Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { EmptyState } from '@/components/inventory/empty-state';
import { Page, PageHeader } from '@/components/inventory/page-header';
import { Pagination } from '@/components/inventory/pagination';
import { Button } from '@/components/ui/button';
import { dateTime, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, read, readAll } from '@/routes/notifications';
import type { AppNotification, Paginated } from '@/types';

export default function NotificationsIndex({ items: notifications }: { items: Paginated<AppNotification> }) {
    const unread = notifications.data.some((notification) => !notification.read_at);

    return (
        <>
            <Head title="নোটিফিকেশন" />
            <Page className="max-w-3xl">
                <PageHeader
                    title="নোটিফিকেশন"
                    description="স্টক কমে যাওয়ার সতর্কতা আর আপনার অনুমোদনের অপেক্ষায় থাকা কাজ।"
                    icon={Bell}
                    tone="amber"
                    actions={
                        unread && (
                            <Button variant="outline" onClick={() => router.post(readAll.url(), {}, { preserveScroll: true })}>
                                সব পড়া হয়েছে
                            </Button>
                        )
                    }
                />
                <section className="surface overflow-hidden">
                    {notifications.data.length === 0 ? (
                        <EmptyState icon={Bell} title="কোনো নোটিফিকেশন নেই" description="স্টক কমে গেলে বা অনুমোদন দরকার হলে এখানে দেখবেন।" />
                    ) : (
                        <ul className="divide-y">
                            {notifications.data.map((notification) => (
                                <li key={notification.id}>
                                    <Link href={read.url(notification.id)} className={cn('flex gap-4 px-5 py-4 hover:bg-muted/50', !notification.read_at && 'bg-accent/40')}>
                                        <span className={cn('mt-2 size-2 shrink-0 rounded-full', notification.read_at ? 'bg-transparent' : 'bg-primary')} />
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">{notification.data.title}</p>
                                            <p className="text-sm text-muted-foreground">{notification.data.body}</p>
                                        </div>
                                        <span className="shrink-0 text-xs text-muted-foreground" title={dateTime(notification.created_at)}>
                                            {relativeTime(notification.created_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination page={notifications} className="border-t" />
                </section>
            </Page>
        </>
    );
}

NotificationsIndex.layout = { breadcrumbs: [{ title: 'নোটিফিকেশন', href: index() }] };
