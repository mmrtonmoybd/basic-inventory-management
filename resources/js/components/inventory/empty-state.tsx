import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function EmptyState({ icon: Icon, title, description, action, className }: { icon?: LucideIcon; title: string; description?: string; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('flex flex-col items-center justify-center gap-3 px-6 py-14 text-center', className)}>
            {Icon && (
                <div className="relative">
                    <div className="absolute inset-0 scale-150 rounded-full bg-primary/10 blur-xl" />
                    <div className="relative flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 text-primary dark:from-indigo-950 dark:to-violet-950">
                        <Icon className="size-6" />
                    </div>
                </div>
            )}
            <div className="space-y-1">
                <p className="font-semibold">{title}</p>
                {description && <p className="max-w-sm text-sm leading-relaxed text-muted-foreground">{description}</p>}
            </div>
            {action}
        </div>
    );
}
