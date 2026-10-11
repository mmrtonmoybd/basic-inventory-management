import { useMemo } from 'react';
import { onlyDigits, qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { AttributeValue, Variant, VariantLookup } from '@/types';

export type QuantityMap = Record<number, string>;

type Mode = 'in' | 'out';

/**
 * Quantity entry for one product. Two-attribute products render as a matrix where only real
 * combinations accept input; everything else renders as a list of variants.
 */
export function VariantQuantityGrid({
    lookup,
    quantities,
    onChange,
    mode,
    errors = {},
    stockKey = 'available',
    stockLabel = 'মজুদ',
}: {
    lookup: VariantLookup;
    quantities: QuantityMap;
    onChange: (variantId: number, value: string) => void;
    mode: Mode;
    errors?: Record<number, string>;
    stockKey?: 'available' | 'inspection' | 'damaged';
    stockLabel?: string;
}) {
    const { attributes, variants } = lookup;

    if (attributes.length === 2) {
        return <Matrix lookup={lookup} quantities={quantities} onChange={onChange} mode={mode} errors={errors} stockKey={stockKey} />;
    }

    return (
        <div className="divide-y rounded-lg border">
            {variants.map((variant) => (
                <div key={variant.id} className="flex items-center gap-4 px-4 py-2.5">
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{variant.name}</p>
                        <p className="text-xs text-muted-foreground">{variant.sku}</p>
                    </div>
                    <p className="hidden text-right text-xs text-muted-foreground sm:block">
                        <span className="block text-sm font-semibold text-foreground tabular-nums">{qty(variant.stock?.[stockKey])}</span>
                        {stockLabel}
                    </p>
                    <QuantityInput variant={variant} value={quantities[variant.id] ?? ''} onChange={onChange} mode={mode} error={errors[variant.id]} stockKey={stockKey} className="w-28" />
                </div>
            ))}
        </div>
    );
}

function Matrix({
    lookup,
    quantities,
    onChange,
    mode,
    errors,
    stockKey,
}: {
    lookup: VariantLookup;
    quantities: QuantityMap;
    onChange: (variantId: number, value: string) => void;
    mode: Mode;
    errors: Record<number, string>;
    stockKey: 'available' | 'inspection' | 'damaged';
}) {
    const [rowAttribute, columnAttribute] = lookup.attributes;

    const { rows, columns, cell } = useMemo(() => {
        const byKey = new Map<string, Variant>();

        for (const variant of lookup.variants) {
            const rowValue = variant.value_ids?.find((id) => rowAttribute.values.some((value) => value.id === id));
            const columnValue = variant.value_ids?.find((id) => columnAttribute.values.some((value) => value.id === id));
            byKey.set(`${rowValue}:${columnValue}`, variant);
        }

        const used = (attribute: typeof rowAttribute, position: 0 | 1) =>
            attribute.values.filter((value) => [...byKey.keys()].some((key) => key.split(':')[position] === String(value.id)));

        return {
            rows: used(rowAttribute, 0),
            columns: used(columnAttribute, 1),
            cell: (row: AttributeValue, column: AttributeValue) => byKey.get(`${row.id}:${column.id}`),
        };
    }, [lookup, rowAttribute, columnAttribute]);

    const rowTotal = (row: AttributeValue) => columns.reduce((sum, column) => sum + (Number(quantities[cell(row, column)?.id ?? -1]) || 0), 0);
    const columnTotal = (column: AttributeValue) => rows.reduce((sum, row) => sum + (Number(quantities[cell(row, column)?.id ?? -1]) || 0), 0);

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="bg-muted/50">
                        <th className="sticky left-0 z-10 bg-muted/50 px-3 py-2 text-left text-xs font-semibold text-muted-foreground">
                            {rowAttribute.name} / {columnAttribute.name}
                        </th>
                        {columns.map((column) => (
                            <th key={column.id} className="px-2 py-2 text-left text-xs font-semibold">
                                <span className="inline-flex items-center gap-1.5">
                                    {column.swatch && <span className="size-3 rounded-full border border-black/10" style={{ backgroundColor: column.swatch }} />}
                                    {column.value}
                                </span>
                            </th>
                        ))}
                        <th className="px-3 py-2 text-right text-xs font-semibold text-muted-foreground">সারির মোট</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id} className="border-t">
                            <th className="sticky left-0 z-10 bg-card px-3 py-2 text-left font-semibold">{row.value}</th>
                            {columns.map((column) => {
                                const variant = cell(row, column);

                                return (
                                    <td key={column.id} className="px-2 py-2 align-top">
                                        {variant ? (
                                            <div className="space-y-1">
                                                <QuantityInput variant={variant} value={quantities[variant.id] ?? ''} onChange={onChange} mode={mode} error={errors[variant.id]} stockKey={stockKey} className="w-24" />
                                                <p className="text-[11px] text-muted-foreground tabular-nums">মজুদ {qty(variant.stock?.[stockKey])}</p>
                                            </div>
                                        ) : (
                                            <div className="flex h-9 w-24 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground/60" title="এই কম্বিনেশনটি নেই">
                                                নেই
                                            </div>
                                        )}
                                    </td>
                                );
                            })}
                            <td className="px-3 py-2 text-right font-semibold tabular-nums">{qty(rowTotal(row))}</td>
                        </tr>
                    ))}
                    <tr className="border-t bg-muted/40">
                        <th className="sticky left-0 z-10 bg-muted/40 px-3 py-2 text-left text-xs font-semibold text-muted-foreground">কলামের মোট</th>
                        {columns.map((column) => (
                            <td key={column.id} className="px-2 py-2 font-semibold tabular-nums">
                                {qty(columnTotal(column))}
                            </td>
                        ))}
                        <td className="px-3 py-2 text-right font-bold tabular-nums">{qty(rows.reduce((sum, row) => sum + rowTotal(row), 0))}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

function QuantityInput({
    variant,
    value,
    onChange,
    mode,
    error,
    stockKey,
    className,
}: {
    variant: Variant;
    value: string;
    onChange: (variantId: number, value: string) => void;
    mode: Mode;
    error?: string;
    stockKey: 'available' | 'inspection' | 'damaged';
    className?: string;
}) {
    const onHand = variant.stock?.[stockKey] ?? 0;
    const exceeds = mode === 'out' && Number(value) > onHand;
    const invalid = Boolean(error) || exceeds;

    return (
        <div className={className}>
            <input
                type="text"
                inputMode="numeric"
                min={0}
                max={mode === 'out' ? onHand : undefined}
                step={1}
                value={value}
                placeholder="০"
                aria-label={`${variant.name} এর পরিমাণ`}
                aria-invalid={invalid}
                title={error ?? (exceeds ? `মজুদ আছে মাত্র ${qty(onHand)}` : undefined)}
                onChange={(event) => onChange(variant.id, onlyDigits(event.target.value))}
                onWheel={(event) => event.currentTarget.blur()}
                className={cn(
                    'h-9 w-full rounded-lg border border-input bg-card px-2.5 transition-colors text-right text-sm font-semibold tabular-nums shadow-xs outline-none placeholder:font-normal placeholder:text-muted-foreground/50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40',
                    Number(value) > 0 && (mode === 'in' ? 'border-available/60 bg-available-soft/50' : 'border-stock-out/50 bg-accent/60'),
                    invalid && 'border-damaged bg-damaged-soft/60 text-damaged',
                )}
            />
            {error && <p className="mt-1 text-[11px] leading-tight text-damaged">{error}</p>}
        </div>
    );
}
