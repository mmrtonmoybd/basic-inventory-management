import { router } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, FileText, Package, Search, Undo2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { useCan } from '@/hooks/use-can';
import { getJson } from '@/lib/http';
import { search as searchRoute } from '@/routes';
import { create as returnsCreate } from '@/routes/returns';
import { create as stockCreate } from '@/routes/stock';

type Result = { group: string; title: string; subtitle: string | null; url: string };

const GROUP_LABELS: Record<string, string> = { Products: 'পণ্য', SKUs: 'SKU', Movements: 'স্টক লেনদেন', Returns: 'রিটার্ন', Claims: 'বদলির দাবি' };

export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [loading, setLoading] = useState(false);
    const can = useCan();

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            getJson<Result[]>(searchRoute.url({ query: { q: term } }), controller.signal)
                .then(setResults)
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 180);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [term]);

    const go = (url: string) => {
        setOpen(false);
        setTerm('');
        router.visit(url);
    };

    const groups = [...new Set(results.map((result) => result.group))];

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="flex h-10 w-full max-w-sm items-center gap-2 rounded-xl border bg-card px-3 text-sm text-muted-foreground shadow-card transition-all hover:border-primary/40 hover:text-foreground"
            >
                <Search className="size-4" />
                <span className="flex-1 truncate text-left">পণ্য, SKU বা রেফারেন্স খুঁজুন</span>
                <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 text-[10px] font-medium sm:inline">Ctrl K</kbd>
            </button>
            <CommandDialog open={open} onOpenChange={setOpen} shouldFilter={false}>
                <CommandInput value={term} onValueChange={setTerm} placeholder="পণ্য, SKU, বারকোড, রেফারেন্স বা দাবি নম্বর লিখুন" />
                <CommandList>
                    {term.trim().length < 2 ? (
                        <CommandGroup heading="দ্রুত কাজ">
                            {can('stock.in') && (
                                <CommandItem onSelect={() => go(stockCreate.url('in'))}>
                                    <ArrowDownToLine /> স্টক ইন করুন
                                </CommandItem>
                            )}
                            {can('stock.out') && (
                                <CommandItem onSelect={() => go(stockCreate.url('out'))}>
                                    <ArrowUpFromLine /> স্টক আউট করুন
                                </CommandItem>
                            )}
                            {can('returns.create') && (
                                <CommandItem onSelect={() => go(returnsCreate.url())}>
                                    <Undo2 /> কাস্টমার রিটার্ন লিখুন
                                </CommandItem>
                            )}
                        </CommandGroup>
                    ) : (
                        <>
                            <CommandEmpty>{loading ? 'খোঁজা হচ্ছে…' : `“${term}” দিয়ে কিছু পাওয়া যায়নি।`}</CommandEmpty>
                            {groups.map((group) => (
                                <CommandGroup key={group} heading={GROUP_LABELS[group] ?? group}>
                                    {results
                                        .filter((result) => result.group === group)
                                        .map((result) => (
                                            <CommandItem key={result.url + result.title} value={result.url + result.title} onSelect={() => go(result.url)}>
                                                {group === 'Products' || group === 'SKUs' ? <Package /> : <FileText />}
                                                <div className="flex min-w-0 flex-col">
                                                    <span className="truncate font-medium">{result.title}</span>
                                                    {result.subtitle && <span className="truncate text-xs text-muted-foreground">{result.subtitle}</span>}
                                                </div>
                                            </CommandItem>
                                        ))}
                                </CommandGroup>
                            ))}
                        </>
                    )}
                </CommandList>
            </CommandDialog>
        </>
    );
}
