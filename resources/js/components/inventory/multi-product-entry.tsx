import { PackageSearch, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { qty } from '@/lib/format';
import { getJson } from '@/lib/http';
import { variants as lookupVariants } from '@/routes/lookup';
import type { ProductLookup, VariantLookup } from '@/types';
import { EmptyState } from './empty-state';
import { ProductPicker } from './product-picker';
import { VariantQuantityGrid, type QuantityMap } from './variant-quantity-grid';

export type EntryLine = { variant_id: number; quantity: number };

/**
 * Builds a list of variant quantities across one or more products.
 * Lines are reported in a stable order so server errors (`lines.N.quantity`) map back to variants.
 */
export function useMultiProductEntry(warehouseId: number | null) {
    const [products, setProducts] = useState<VariantLookup[]>([]);
    const [loadingIds, setLoadingIds] = useState<number[]>([]);
    const [quantities, setQuantities] = useState<QuantityMap>({});

    const load = useCallback(
        async (productId: number) => {
            const lookup = await getJson<VariantLookup>(lookupVariants.url(productId, { query: warehouseId ? { warehouse_id: warehouseId } : {} }));

            return lookup;
        },
        [warehouseId],
    );

    const addProduct = async (product: ProductLookup) => {
        setLoadingIds((ids) => [...ids, product.id]);

        try {
            const lookup = await load(product.id);
            setProducts((current) => [...current.filter((item) => item.product.id !== product.id), lookup]);
        } finally {
            setLoadingIds((ids) => ids.filter((id) => id !== product.id));
        }
    };

    useEffect(() => {
        if (products.length === 0) {
            return;
        }

        void Promise.all(products.map((item) => load(item.product.id))).then(setProducts);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [warehouseId]);

    const removeProduct = (productId: number) => {
        const removed = products.find((item) => item.product.id === productId);
        setProducts((current) => current.filter((item) => item.product.id !== productId));
        setQuantities((current) => {
            const next = { ...current };
            removed?.variants.forEach((variant) => delete next[variant.id]);

            return next;
        });
    };

    const lines: EntryLine[] = products.flatMap((item) =>
        item.variants.filter((variant) => Number(quantities[variant.id]) > 0).map((variant) => ({ variant_id: variant.id, quantity: Number(quantities[variant.id]) })),
    );

    const reset = () => {
        setProducts([]);
        setQuantities({});
    };

    const refresh = async () => setProducts(await Promise.all(products.map((item) => load(item.product.id))));

    return {
        products,
        loadingIds,
        quantities,
        setQuantity: (variantId: number, value: string) => setQuantities((current) => ({ ...current, [variantId]: value })),
        addProduct,
        removeProduct,
        lines,
        total: lines.reduce((sum, line) => sum + line.quantity, 0),
        reset,
        refresh,
    };
}

export type MultiProductEntryState = ReturnType<typeof useMultiProductEntry>;

export function MultiProductEntry({
    entry,
    mode,
    errors,
    stockKey,
    stockLabel,
}: {
    entry: MultiProductEntryState;
    mode: 'in' | 'out';
    errors: Record<string, string>;
    stockKey?: 'available' | 'inspection' | 'damaged';
    stockLabel?: string;
}) {
    const lineErrors: Record<number, string> = {};
    entry.lines.forEach((line, index) => {
        const message = errors[`lines.${index}.quantity`] ?? errors[`lines.${index}.variant_id`];

        if (message) {
            lineErrors[line.variant_id] = message;
        }
    });

    return (
        <div className="space-y-4">
            <ProductPicker onSelect={entry.addProduct} excludeIds={entry.products.map((item) => item.product.id)} />

            {errors.lines && <p className="text-sm font-medium text-damaged">{errors.lines}</p>}

            {entry.products.length === 0 && entry.loadingIds.length === 0 && (
                <div className="rounded-lg border border-dashed">
                    <EmptyState
                        icon={PackageSearch}
                        title="শুরু করতে একটি পণ্য বেছে নিন"
                        description="পণ্যের যে সাইজ-রঙগুলো আছে শুধু সেগুলোই দেখাবে। একটি এন্ট্রিতে একাধিক পণ্য যোগ করতে পারবেন।"
                    />
                </div>
            )}

            {entry.products.map((item) => {
                const productTotal = item.variants.reduce((sum, variant) => sum + (Number(entry.quantities[variant.id]) || 0), 0);

                return (
                    <section key={item.product.id} className="animate-rise space-y-3 rounded-xl border bg-muted/20 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">{item.product.name}</h3>
                                <p className="text-xs text-muted-foreground">
                                    {item.product.code}, {qty(item.variants.length)}টি ভ্যারিয়েন্ট
                                    {item.attributes.length > 0 && ` (${item.attributes.map((attribute) => attribute.name).join(' × ')})`}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-sm text-muted-foreground">
                                    এই পণ্যের মোট <span className="font-semibold text-foreground tabular-nums">{qty(productTotal)}</span>
                                </span>
                                <Button type="button" variant="ghost" size="icon" onClick={() => entry.removeProduct(item.product.id)} aria-label={`${item.product.name} সরান`}>
                                    <X className="size-4" />
                                </Button>
                            </div>
                        </div>
                        {item.variants.length === 0 ? (
                            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">এই পণ্যের কোনো চালু ভ্যারিয়েন্ট নেই।</p>
                        ) : (
                            <VariantQuantityGrid lookup={item} quantities={entry.quantities} onChange={entry.setQuantity} mode={mode} errors={lineErrors} stockKey={stockKey} stockLabel={stockLabel} />
                        )}
                    </section>
                );
            })}

            {entry.loadingIds.map((id) => (
                <Skeleton key={id} className="h-40 w-full" />
            ))}
        </div>
    );
}
