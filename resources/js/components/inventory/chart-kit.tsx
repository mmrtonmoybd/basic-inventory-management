import type { ReactNode } from 'react';
import { bnDigits, qty } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Shared Recharts styling: hairline solid grid, muted ink axes, surface-ringed markers. */
export const axisProps = {
    tick: { fill: 'var(--muted-foreground)', fontSize: 11 },
    tickLine: false,
    axisLine: { stroke: 'var(--border)' },
} as const;

export const gridProps = {
    stroke: 'var(--border)',
    strokeDasharray: undefined,
    vertical: false,
} as const;

export function compactTick(value: number): string {
    return value >= 1000 ? `${bnDigits(Math.round(value / 100) / 10)}হা` : bnDigits(value);
}

type TooltipEntry = { name?: string | number; value?: number | string; color?: string; dataKey?: string | number; payload?: Record<string, unknown> };

export function ChartTooltip({
    active,
    payload,
    label,
    labelFormatter,
}: {
    active?: boolean;
    payload?: TooltipEntry[];
    label?: string | number;
    labelFormatter?: (label: string) => ReactNode;
}) {
    if (!active || !payload?.length) {
        return null;
    }

    return (
        <div className="min-w-40 rounded-xl border bg-popover/95 px-3 py-2.5 text-xs shadow-xl backdrop-blur">
            {label !== undefined && <p className="mb-1.5 font-semibold text-foreground">{labelFormatter ? labelFormatter(String(label)) : label}</p>}
            <ul className="space-y-1">
                {payload.map((entry) => (
                    <li key={String(entry.dataKey ?? entry.name)} className="flex items-center gap-2">
                        <span className="size-2.5 rounded-sm" style={{ backgroundColor: entry.color }} />
                        <span className="flex-1 text-muted-foreground">{entry.name}</span>
                        <span className="font-semibold text-foreground tabular-nums">{qty(Number(entry.value))}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ChartLegend({ items, className }: { items: { label: string; color: string; hatch?: boolean }[]; className?: string }) {
    return (
        <ul className={cn('flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground', className)}>
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-1.5">
                    <span className={cn('size-2.5 rounded-sm', item.hatch && 'hatch border')} style={{ backgroundColor: item.hatch ? undefined : item.color, color: item.color, borderColor: item.color }} />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}
