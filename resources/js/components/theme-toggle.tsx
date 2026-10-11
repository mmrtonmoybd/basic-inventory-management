import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Both icons are rendered and CSS shows the right one, so server and browser markup always match.
 */
export function ThemeToggle() {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    return (
        <Button variant="ghost" size="icon" onClick={() => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark')} aria-label="আলো/অন্ধকার মোড বদলান">
            <Sun className="hidden size-[18px] dark:block" />
            <Moon className="size-[18px] dark:hidden" />
        </Button>
    );
}
