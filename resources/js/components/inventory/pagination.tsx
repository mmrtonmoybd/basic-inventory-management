import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { bnDigits, qty } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination({ page, className }: { page: Paginated<unknown>; className?: string }) {
    if (page.last_page <= 1) {
        return page.total > 0 ? <p className={cn('px-4 py-3 text-sm text-muted-foreground', className)}>মোট {qty(page.total)}টি</p> : null;
    }

    return (
        <nav className={cn('flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm', className)} aria-label="পাতা বদলান">
            <p className="text-muted-foreground">
                {qty(page.total)}টির মধ্যে {qty(page.from)}–{qty(page.to)}
            </p>
            <div className="flex items-center gap-1">
                {page.links.map((link, index) => {
                    const isPrev = index === 0;
                    const isNext = index === page.links.length - 1;
                    const label = isPrev ? <ChevronLeft className="size-4" /> : isNext ? <ChevronRight className="size-4" /> : bnDigits(link.label);

                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            aria-label={isPrev ? 'আগের পাতা' : isNext ? 'পরের পাতা' : `পাতা ${bnDigits(link.label)}`}
                            aria-current={link.active ? 'page' : undefined}
                            className={cn('flex h-8 min-w-8 items-center justify-center rounded-lg px-2 tabular-nums transition-colors hover:bg-accent', link.active && 'bg-brand text-white shadow-md hover:bg-brand')}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span key={index} className="flex h-8 min-w-8 items-center justify-center px-2 text-muted-foreground/40">
                            {label}
                        </span>
                    );
                })}
            </div>
        </nav>
    );
}
