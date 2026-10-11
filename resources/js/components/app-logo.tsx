import { usePage } from '@inertiajs/react';

/**
 * Logo mark: three stacked cartons in the stock-condition colours.
 */
export function LogoMark({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
            <rect x="3" y="19" width="26" height="9" rx="2.5" className="fill-emerald-400" />
            <rect x="6" y="11" width="20" height="7" rx="2.5" className="fill-amber-400" />
            <rect x="9" y="4" width="14" height="6" rx="2.5" className="fill-white" />
        </svg>
    );
}

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 shadow-lg shadow-indigo-900/40">
                <LogoMark className="size-6" />
            </div>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-base leading-tight font-bold tracking-tight text-white">{name}</span>
                <span className="truncate text-[11px] leading-tight text-sidebar-foreground/70">স্টক ব্যবস্থাপনা</span>
            </div>
        </>
    );
}
