import { Check, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Attribute, AttributeValue } from '@/types';

/** One selected combination: one value id per attribute, in attribute order. */
export type Combination = number[];

export function combinationKey(valueIds: number[]): string {
    return [...valueIds].sort((a, b) => a - b).join('-');
}

function valueCode(value: AttributeValue): string {
    return (value.code || value.value).toUpperCase().replace(/[^A-Z0-9]/g, '');
}

export function previewSku(productCode: string, attributes: Attribute[], combination: Combination): string {
    const parts = combination.map((id, index) => {
        const value = attributes[index]?.values.find((item) => item.id === id);

        return value ? valueCode(value) : '';
    });

    return [productCode.toUpperCase() || 'কোড', ...parts].join('-');
}

export function combinationLabel(attributes: Attribute[], combination: Combination): string {
    return combination.map((id, index) => attributes[index]?.values.find((value) => value.id === id)?.value ?? '?').join(' / ');
}

/**
 * Selects which attribute combinations become variants. Never generates combinations the user did not pick.
 */
export function VariantBuilder({
    attributes,
    value,
    onChange,
    existingKeys = [],
}: {
    attributes: Attribute[];
    value: Combination[];
    onChange: (next: Combination[]) => void;
    existingKeys?: string[];
}) {
    if (attributes.length === 1) {
        return <SingleAttribute attribute={attributes[0]} value={value} onChange={onChange} existingKeys={existingKeys} />;
    }

    if (attributes.length === 2) {
        return <Matrix attributes={attributes} value={value} onChange={onChange} existingKeys={existingKeys} />;
    }

    return <CombinationList attributes={attributes} value={value} onChange={onChange} existingKeys={existingKeys} />;
}

function Swatch({ value }: { value: AttributeValue }) {
    return value.swatch ? <span className="size-3 shrink-0 rounded-full border border-black/15" style={{ backgroundColor: value.swatch }} /> : null;
}

function SingleAttribute({ attribute, value, onChange, existingKeys }: { attribute: Attribute; value: Combination[]; onChange: (next: Combination[]) => void; existingKeys: string[] }) {
    const selected = new Set(value.map((combination) => combination[0]));

    return (
        <div className="flex flex-wrap gap-2">
            {attribute.values.map((item) => {
                const exists = existingKeys.includes(String(item.id));
                const isOn = selected.has(item.id);

                return (
                    <button
                        key={item.id}
                        type="button"
                        disabled={exists}
                        aria-pressed={isOn}
                        onClick={() => onChange(isOn ? value.filter((combination) => combination[0] !== item.id) : [...value, [item.id]])}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                            isOn ? 'border-primary bg-primary text-primary-foreground' : 'hover:border-ring/50 hover:bg-muted',
                            exists && 'cursor-not-allowed border-dashed bg-muted text-muted-foreground',
                        )}
                    >
                        <Swatch value={item} />
                        {item.value}
                        {exists && <span className="text-xs">আছে</span>}
                    </button>
                );
            })}
        </div>
    );
}

function Matrix({ attributes, value, onChange, existingKeys }: { attributes: Attribute[]; value: Combination[]; onChange: (next: Combination[]) => void; existingKeys: string[] }) {
    const [rowAttribute, columnAttribute] = attributes;
    const selected = useMemo(() => new Set(value.map((combination) => combinationKey(combination))), [value]);

    const isExisting = (row: number, column: number) => existingKeys.includes(combinationKey([row, column]));
    const isSelected = (row: number, column: number) => selected.has(combinationKey([row, column]));

    const toggle = (row: number, column: number) => {
        if (isExisting(row, column)) {
            return;
        }

        onChange(isSelected(row, column) ? value.filter((combination) => combinationKey(combination) !== combinationKey([row, column])) : [...value, [row, column]]);
    };

    const setMany = (cells: [number, number][], on: boolean) => {
        const editable = cells.filter(([row, column]) => !isExisting(row, column));
        const keys = new Set(editable.map((cell) => combinationKey(cell)));
        const remaining = value.filter((combination) => !keys.has(combinationKey(combination)));

        onChange(on ? [...remaining, ...editable] : remaining);
    };

    const rowCells = (row: number): [number, number][] => columnAttribute.values.map((column) => [row, column.id]);
    const columnCells = (column: number): [number, number][] => rowAttribute.values.map((row) => [row.id, column]);
    const allOn = (cells: [number, number][]) => cells.filter(([row, column]) => !isExisting(row, column)).every(([row, column]) => isSelected(row, column));

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full border-collapse text-sm">
                <thead>
                    <tr className="bg-muted/50">
                        <th className="sticky left-0 z-10 bg-muted/50 px-3 py-2.5 text-left text-xs font-semibold text-muted-foreground">
                            {rowAttribute.name} ↓ {columnAttribute.name} →
                        </th>
                        {columnAttribute.values.map((column) => {
                            const cells = columnCells(column.id);
                            const on = allOn(cells);

                            return (
                                <th key={column.id} className="px-1.5 py-2 text-center">
                                    <button
                                        type="button"
                                        onClick={() => setMany(cells, !on)}
                                        className="mx-auto inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold hover:bg-muted"
                                        title={on ? `সব ${rowAttribute.name} থেকে ${column.value} সরান` : `সব ${rowAttribute.name} এর জন্য ${column.value} বেছে নিন`}
                                    >
                                        <Swatch value={column} />
                                        {column.value}
                                    </button>
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {rowAttribute.values.map((row) => {
                        const cells = rowCells(row.id);
                        const count = cells.filter(([r, c]) => isSelected(r, c) || isExisting(r, c)).length;

                        return (
                            <tr key={row.id} className="border-t">
                                <th className="sticky left-0 z-10 bg-card px-2 py-1.5 text-left">
                                    <button
                                        type="button"
                                        onClick={() => setMany(cells, !allOn(cells))}
                                        className="inline-flex w-full items-center justify-between gap-3 rounded-md px-2 py-1 text-sm font-semibold hover:bg-muted"
                                        title={`${row.value} এর সব ${columnAttribute.name} একসাথে বেছে নিন/সরান`}
                                    >
                                        {row.value}
                                        <span className="text-xs font-normal text-muted-foreground tabular-nums">{count}</span>
                                    </button>
                                </th>
                                {columnAttribute.values.map((column) => {
                                    const exists = isExisting(row.id, column.id);
                                    const on = isSelected(row.id, column.id);

                                    return (
                                        <td key={column.id} className="px-1.5 py-1.5 text-center">
                                            <button
                                                type="button"
                                                disabled={exists}
                                                aria-pressed={on}
                                                aria-label={`${row.value} / ${column.value}`}
                                                onClick={() => toggle(row.id, column.id)}
                                                className={cn(
                                                    'mx-auto flex size-9 items-center justify-center rounded-lg border transition-all duration-200',
                                                    on && 'scale-105 border-transparent bg-brand text-white shadow-md shadow-indigo-500/30',
                                                    !on && !exists && 'border-dashed hover:border-solid hover:border-ring/60 hover:bg-accent',
                                                    exists && 'cursor-not-allowed border-transparent bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {on && <Check className="size-4" />}
                                                {exists && <span className="text-[10px] font-semibold">আছে</span>}
                                            </button>
                                        </td>
                                    );
                                })}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function CombinationList({ attributes, value, onChange, existingKeys }: { attributes: Attribute[]; value: Combination[]; onChange: (next: Combination[]) => void; existingKeys: string[] }) {
    const [picks, setPicks] = useState<number[][]>(attributes.map(() => []));
    const selectedKeys = new Set(value.map((combination) => combinationKey(combination)));

    const togglePick = (attributeIndex: number, valueId: number) =>
        setPicks((current) => current.map((ids, index) => (index !== attributeIndex ? ids : ids.includes(valueId) ? ids.filter((id) => id !== valueId) : [...ids, valueId])));

    const generated = picks.reduce<number[][]>((combos, ids) => combos.flatMap((combo) => ids.map((id) => [...combo, id])), [[]]);
    const additions = picks.every((ids) => ids.length > 0) ? generated.filter((combo) => !selectedKeys.has(combinationKey(combo)) && !existingKeys.includes(combinationKey(combo))) : [];

    return (
        <div className="space-y-4">
            <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                <p className="text-sm text-muted-foreground">
                    যে মানগুলো একসাথে হবে সেগুলো বেছে যোগ করুন। প্রতিটি দলের জন্য আবার করুন, যেমন: L সাইজে সাদা ও নীল, তারপর XL সাইজে শুধু নীল।
                </p>
                {attributes.map((attribute, attributeIndex) => (
                    <div key={attribute.id} className="flex flex-wrap items-center gap-2">
                        <span className="w-20 shrink-0 text-sm font-semibold">{attribute.name}</span>
                        {attribute.values.map((item) => {
                            const on = picks[attributeIndex]?.includes(item.id);

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    aria-pressed={on}
                                    onClick={() => togglePick(attributeIndex, item.id)}
                                    className={cn('inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm', on ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:bg-muted')}
                                >
                                    <Swatch value={item} />
                                    {item.value}
                                </button>
                            );
                        })}
                    </div>
                ))}
                <Button
                    type="button"
                    size="sm"
                    disabled={additions.length === 0}
                    onClick={() => {
                        onChange([...value, ...additions]);
                        setPicks(attributes.map(() => []));
                    }}
                >
                    <Plus /> {additions.length ? `${qty(additions.length)}টি কম্বিনেশন যোগ করুন` : 'কম্বিনেশন যোগ করুন'}
                </Button>
            </div>

            {value.length > 0 && (
                <ul className="divide-y rounded-lg border">
                    {value.map((combination) => (
                        <li key={combinationKey(combination)} className="flex items-center justify-between px-4 py-2 text-sm">
                            <span className="font-medium">{combinationLabel(attributes, combination)}</span>
                            <Button type="button" variant="ghost" size="icon" aria-label="কম্বিনেশন সরান" onClick={() => onChange(value.filter((item) => combinationKey(item) !== combinationKey(combination)))}>
                                <Trash2 className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
