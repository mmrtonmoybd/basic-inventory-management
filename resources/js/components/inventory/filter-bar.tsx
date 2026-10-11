import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Filters = Record<string, string | undefined | null>;

/**
 * Keeps list filters in the URL so filtered views can be shared and survive reloads.
 */
export function useFilters(url: string, initial: Filters) {
    const [filters, setFilters] = useState<Filters>(initial);

    const apply = (next: Filters) => {
        const merged = { ...filters, ...next };
        setFilters(merged);
        const query = Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== undefined && value !== null && value !== ''));
        router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
    };

    return { filters, apply, reset: () => apply(Object.fromEntries(Object.keys(filters).map((key) => [key, undefined]))) };
}

export function FilterBar({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('flex flex-wrap items-center gap-2 border-b bg-muted/30 px-4 py-3', className)}>{children}</div>;
}

export function SearchFilter({ value, onChange, placeholder = 'খুঁজুন…', className }: { value?: string | null; onChange: (value: string) => void; placeholder?: string; className?: string }) {
    const [term, setTerm] = useState(value ?? '');
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => onChange(term), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    return (
        <div className={cn('relative w-full sm:w-72', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input value={term} onChange={(event) => setTerm(event.target.value)} placeholder={placeholder} className="pl-8" aria-label={placeholder} />
            {term && (
                <button type="button" onClick={() => setTerm('')} className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground" aria-label="খোঁজা মুছুন">
                    <X className="size-4" />
                </button>
            )}
        </div>
    );
}

export function SelectFilter({
    value,
    onChange,
    options,
    placeholder,
    className,
}: {
    value?: string | null;
    onChange: (value: string | undefined) => void;
    options: { value: string; label: string }[];
    placeholder: string;
    className?: string;
}) {
    return (
        <Select value={value ?? 'all'} onValueChange={(next) => onChange(next === 'all' ? undefined : next)}>
            <SelectTrigger className={cn('w-full sm:w-44', className)} aria-label={placeholder}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function ResetFilters({ visible, onReset }: { visible: boolean; onReset: () => void }) {
    if (!visible) {
        return null;
    }

    return (
        <Button variant="ghost" size="sm" onClick={onReset}>
            ফিল্টার মুছুন
        </Button>
    );
}
