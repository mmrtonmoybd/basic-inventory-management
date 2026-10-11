import { useEffect, useRef, useState } from 'react';
import { qty } from '@/lib/format';

function prefersReducedMotion(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Animates a number from 0 to its value once, in Bengali digits. Static when reduced motion is on.
 */
export function CountUp({ value, duration = 900, className }: { value: number; duration?: number; className?: string }) {
    const [shown, setShown] = useState(() => (prefersReducedMotion() ? value : 0));
    const frame = useRef<number | null>(null);

    useEffect(() => {
        if (prefersReducedMotion()) {
            setShown(value);

            return;
        }

        const start = performance.now();
        const from = 0;

        const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            setShown(Math.round(from + (value - from) * eased));

            if (progress < 1) {
                frame.current = requestAnimationFrame(tick);
            }
        };

        frame.current = requestAnimationFrame(tick);

        return () => {
            if (frame.current) {
                cancelAnimationFrame(frame.current);
            }
        };
    }, [value, duration]);

    return <span className={className}>{qty(shown)}</span>;
}
