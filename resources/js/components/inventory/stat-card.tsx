import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { CountUp } from './count-up';
import { TONE_CHIPS, type Tone } from './page-header';

const TINTS: Record<Tone, string> = {
    indigo: 'from-indigo-500/10',
    teal: 'from-emerald-500/10',
    amber: 'from-amber-500/12',
    rose: 'from-rose-500/10',
    violet: 'from-violet-500/10',
    sky: 'from-sky-500/10',
    slate: 'from-slate-500/10',
};

/**
 * A coloured metric tile with an icon chip and a count-up number.
 */
export function StatCard({ label, value, icon, tone = 'indigo', hint, href, alert = false }: { label: string; value: number; icon: LucideIcon; tone?: Tone; hint?: ReactNode; href?: string | null; alert?: boolean }) {
    const Icon = icon;
    const body = (
        <>
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-muted-foreground">{label}</p>
                <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-xl shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3', TONE_CHIPS[tone])}>
                    <Icon className="size-[18px]" />
                </span>
            </div>
            <CountUp value={value} className={cn('mt-1 block text-3xl font-bold tracking-tight', alert && value > 0 && 'text-damaged')} />
            {hint && <p className="mt-1 truncate text-xs text-muted-foreground">{hint}</p>}
        </>
    );

    const className = cn('group surface relative block overflow-hidden bg-gradient-to-br to-transparent p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lift', TINTS[tone]);

    return href ? (
        <Link href={href} className={className}>
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}
