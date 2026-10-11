import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { StockSplit } from '@/types';
import { CONDITIONS } from './conditions';

/**
 * The signature stock visual: physical stock split by condition. Units held by suppliers are drawn
 * as a detached hatched segment because they are not inside the warehouse.
 */
export function ConditionBar({ split, size = 'sm', className }: { split: StockSplit; size?: 'sm' | 'md' | 'lg'; className?: string }) {
    const physical = split.available + split.inspection + split.damaged;
    const total = physical + split.with_supplier;
    const height = size === 'lg' ? 'h-4' : size === 'md' ? 'h-2.5' : 'h-1.5';
    const supplierShare = total > 0 ? Math.max((split.with_supplier / total) * 100, split.with_supplier > 0 ? 3 : 0) : 0;

    if (total === 0) {
        return <div className={cn('w-full rounded-full bg-muted', height, className)} aria-label="স্টক নেই" />;
    }

    return (
        <div className={cn('flex w-full items-center gap-1', className)} role="img" aria-label={CONDITIONS.map((condition) => `${condition.label} ${qty(split[condition.key])}`).join(', ')}>
            <div className={cn('flex min-w-0 flex-1 animate-grow-x gap-px overflow-hidden rounded-full bg-muted', height)}>
                {CONDITIONS.filter((condition) => condition.physical).map((condition) => {
                    const value = split[condition.key];

                    if (value <= 0) {
                        return null;
                    }

                    return (
                        <Tooltip key={condition.key}>
                            <TooltipTrigger asChild>
                                <div className={cn(condition.bar, 'h-full first:rounded-l-full last:rounded-r-full')} style={{ width: `${(value / physical) * 100}%`, minWidth: 3 }} />
                            </TooltipTrigger>
                            <TooltipContent>
                                {condition.label}: {qty(value)} পিস
                            </TooltipContent>
                        </Tooltip>
                    );
                })}
            </div>
            {split.with_supplier > 0 && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <div className={cn('hatch shrink-0 rounded-full border border-supplier/60 text-supplier', height)} style={{ width: `${Math.min(supplierShare, 30)}%`, minWidth: 8 }} />
                    </TooltipTrigger>
                    <TooltipContent>সরবরাহকারীর কাছে: {qty(split.with_supplier)} পিস (গুদামের মজুদে ধরা হয় না)</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

export function ConditionLegend({ split, className }: { split?: StockSplit; className?: string }) {
    return (
        <ul className={cn('flex flex-wrap gap-x-5 gap-y-2 text-sm', className)}>
            {CONDITIONS.map((condition) => (
                <li key={condition.key} className="flex items-center gap-2">
                    <span className={cn('size-2.5 rounded-full', condition.physical ? condition.bar : 'hatch border border-supplier/60 text-supplier')} />
                    <span className="text-muted-foreground">{condition.label}</span>
                    {split && <span className="font-semibold tabular-nums">{qty(split[condition.key])}</span>}
                </li>
            ))}
        </ul>
    );
}

export function ConditionQty({ value, condition, muted = true }: { value: number; condition: keyof StockSplit; muted?: boolean }) {
    const meta = CONDITIONS.find((item) => item.key === condition);

    return <span className={cn('tabular-nums', value === 0 && muted ? 'text-muted-foreground/50' : meta?.text, value !== 0 && 'font-semibold')}>{qty(value)}</span>;
}
