import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type Tone = 'indigo' | 'teal' | 'amber' | 'rose' | 'violet' | 'sky' | 'slate';

export const TONE_CHIPS: Record<Tone, string> = {
    indigo: 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-indigo-500/30',
    teal: 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-emerald-500/30',
    amber: 'bg-gradient-to-br from-amber-400 to-orange-500 text-white shadow-amber-500/30',
    rose: 'bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-rose-500/30',
    violet: 'bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white shadow-violet-500/30',
    sky: 'bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-sky-500/30',
    slate: 'bg-gradient-to-br from-slate-600 to-slate-800 text-white shadow-slate-500/30',
};

export function IconChip({ icon: Icon, tone = 'indigo', className }: { icon: LucideIcon; tone?: Tone; className?: string }) {
    return (
        <span className={cn('flex size-11 shrink-0 items-center justify-center rounded-xl shadow-lg', TONE_CHIPS[tone], className)}>
            <Icon className="size-5" />
        </span>
    );
}

export function PageHeader({
    title,
    description,
    actions,
    meta,
    icon,
    tone = 'indigo',
    className,
}: {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    meta?: ReactNode;
    icon?: LucideIcon;
    tone?: Tone;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="flex min-w-0 items-start gap-4">
                {icon && <IconChip icon={icon} tone={tone} className="mt-0.5 hidden sm:flex" />}
                <div className="min-w-0 space-y-1">
                    <h1 className="text-2xl leading-tight font-bold tracking-tight text-balance sm:text-[1.65rem]">{title}</h1>
                    {description && <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">{description}</p>}
                    {meta && <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 pt-1 text-sm text-muted-foreground">{meta}</div>}
                </div>
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}

export function Page({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('stagger mx-auto flex w-full max-w-[1400px] flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8', className)}>{children}</div>;
}

export function Panel({
    title,
    description,
    actions,
    children,
    icon: Icon,
    tone = 'indigo',
    className,
    bodyClassName,
}: {
    title?: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    icon?: LucideIcon;
    tone?: Tone;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section className={cn('surface overflow-hidden text-card-foreground', className)}>
            {(title || actions) && (
                <header className="flex flex-wrap items-center justify-between gap-3 border-b bg-gradient-to-r from-muted/60 to-transparent px-5 py-3.5">
                    <div className="flex items-center gap-3">
                        {Icon && (
                            <span className={cn('flex size-8 items-center justify-center rounded-lg shadow-md', TONE_CHIPS[tone])}>
                                <Icon className="size-4" />
                            </span>
                        )}
                        <div>
                            {title && <h2 className="text-[0.98rem] font-semibold">{title}</h2>}
                            {description && <p className="text-xs leading-relaxed text-muted-foreground">{description}</p>}
                        </div>
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </header>
            )}
            <div className={cn('p-5', bodyClassName)}>{children}</div>
        </section>
    );
}

export function Field({ label, value, className }: { label: string; value: ReactNode; className?: string }) {
    return (
        <div className={cn('min-w-0', className)}>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 truncate text-sm font-semibold">{value ?? '—'}</dd>
        </div>
    );
}
