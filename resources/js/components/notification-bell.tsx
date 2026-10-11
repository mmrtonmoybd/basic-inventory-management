import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, ClipboardList, Repeat, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { bnDigits, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as notificationsIndex, read, readAll } from '@/routes/notifications';

const ICONS = { low_stock: AlertTriangle, claim: Repeat, adjustment: ClipboardList, return_pending: Undo2 } as const;

export function NotificationBell() {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label={`নোটিফিকেশন, ${bnDigits(notifications.unread)}টি না পড়া`}>
                    <Bell className="size-[18px]" />
                    {notifications.unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 animate-in zoom-in items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-red-600 px-1 text-[10px] leading-4 font-bold text-white shadow">
                            {notifications.unread > 9 ? '৯+' : bnDigits(notifications.unread)}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-[22rem] p-0">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <p className="font-semibold">নোটিফিকেশন</p>
                    {notifications.unread > 0 && (
                        <button type="button" className="text-xs font-medium text-primary hover:underline" onClick={() => router.post(readAll.url(), {}, { preserveScroll: true })}>
                            সব পড়া হয়েছে
                        </button>
                    )}
                </div>
                <ul className="max-h-96 divide-y overflow-y-auto">
                    {notifications.latest.length === 0 && <li className="px-4 py-10 text-center text-sm text-muted-foreground">নতুন কিছু নেই।</li>}
                    {notifications.latest.map((notification) => {
                        const Icon = ICONS[notification.data.kind as keyof typeof ICONS] ?? Bell;

                        return (
                            <li key={notification.id}>
                                <Link href={read.url(notification.id)} className={cn('flex gap-3 px-4 py-3 hover:bg-muted/60', !notification.read_at && 'bg-accent/40')}>
                                    <Icon className={cn('mt-0.5 size-4 shrink-0', notification.data.kind === 'low_stock' ? 'text-damaged' : 'text-primary')} />
                                    <div className="min-w-0 space-y-0.5">
                                        <p className="text-sm font-medium">{notification.data.title}</p>
                                        <p className="line-clamp-2 text-xs text-muted-foreground">{notification.data.body}</p>
                                        <p className="text-[11px] text-muted-foreground">{relativeTime(notification.created_at)}</p>
                                    </div>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
                <Link href={notificationsIndex.url()} className="block border-t px-4 py-2.5 text-center text-sm font-medium text-primary hover:bg-muted/50">
                    সব নোটিফিকেশন দেখুন
                </Link>
            </PopoverContent>
        </Popover>
    );
}
