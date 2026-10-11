import { Check, ChevronsUpDown, Package } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { qty } from '@/lib/format';
import { getJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import { products as lookupProducts } from '@/routes/lookup';
import type { ProductLookup } from '@/types';

export function ProductPicker({
    onSelect,
    excludeIds = [],
    placeholder = 'পণ্যের নাম, কোড বা SKU লিখে খুঁজুন',
    className,
}: {
    onSelect: (product: ProductLookup) => void;
    excludeIds?: number[];
    placeholder?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<ProductLookup[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            getJson<ProductLookup[]>(lookupProducts.url({ query: { search } }), controller.signal)
                .then(setResults)
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [search, open]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button variant="outline" role="combobox" aria-expanded={open} className={cn('h-12 w-full justify-between rounded-xl border-dashed border-primary/40 bg-accent/40 text-left font-normal hover:bg-accent', className)}>
                    <span className="flex items-center gap-2 text-muted-foreground">
                        <Package className="size-4 text-primary" />
                        {placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[--radix-popover-trigger-width] min-w-80 p-0" align="start">
                <Command shouldFilter={false}>
                    <CommandInput value={search} onValueChange={setSearch} placeholder="লিখে খুঁজুন…" />
                    <CommandList>
                        <CommandEmpty>{loading ? 'খোঁজা হচ্ছে…' : 'এই নামে কোনো চালু পণ্য নেই।'}</CommandEmpty>
                        <CommandGroup>
                            {results.map((product) => {
                                const added = excludeIds.includes(product.id);

                                return (
                                    <CommandItem
                                        key={product.id}
                                        value={String(product.id)}
                                        disabled={added}
                                        onSelect={() => {
                                            onSelect(product);
                                            setOpen(false);
                                            setSearch('');
                                        }}
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate font-medium">{product.name}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {product.code}, {qty(product.variants_count)}টি ভ্যারিয়েন্ট
                                            </span>
                                        </div>
                                        {added && <Check className="size-4" />}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
