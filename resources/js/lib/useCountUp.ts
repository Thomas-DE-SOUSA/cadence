import { useEffect, useRef, useState } from 'react';

/** Eases a number from 0 to `target` once on mount (respects reduced-motion). */
export function useCountUp(target: number, duration = 750): number {
    const [value, setValue] = useState(0);
    const raf = useRef<number | undefined>(undefined);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setValue(target);
            return;
        }
        const start = performance.now();
        const tick = (now: number) => {
            const p = Math.min(1, (now - start) / duration);
            setValue(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) raf.current = requestAnimationFrame(tick);
        };
        raf.current = requestAnimationFrame(tick);
        return () => {
            if (raf.current) cancelAnimationFrame(raf.current);
        };
    }, [target, duration]);

    return value;
}
