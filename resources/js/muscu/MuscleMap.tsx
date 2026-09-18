type MuscleKey =
    | 'CHEST'
    | 'BACK'
    | 'SHOULDERS'
    | 'BICEPS'
    | 'TRICEPS'
    | 'FOREARMS'
    | 'QUADS'
    | 'HAMSTRINGS'
    | 'GLUTES'
    | 'CALVES'
    | 'CORE'
    | 'FULL_BODY';

const BASE = '#d4d4d4'; // neutral-300 — the resting body

/**
 * Consistent, data-driven exercise thumbnail: a stylised front + back body
 * silhouette with the exercise's primary muscle group highlighted in the brand
 * colour. One art style used everywhere, no per-exercise assets or licensing.
 * The brand colour follows the muscu palette via the CSS var (Hevy blue).
 */
export function MuscleMap({ muscle, className }: { muscle?: string | null; className?: string }) {
    const active = (muscle ?? '') as MuscleKey;
    const on = (region: MuscleKey): boolean => active === region || active === 'FULL_BODY';
    const f = (region: MuscleKey | null): string => (region && on(region) ? 'var(--color-brand-500, #2979ff)' : BASE);

    const Figure = ({ ox, side }: { ox: number; side: 'front' | 'back' }) => {
        const arm: MuscleKey = side === 'front' ? 'BICEPS' : 'TRICEPS';
        const torso: MuscleKey = side === 'front' ? 'CHEST' : 'BACK';
        const mid: MuscleKey = side === 'front' ? 'CORE' : 'BACK';
        const pelvis: MuscleKey | null = side === 'front' ? null : 'GLUTES';
        const thigh: MuscleKey = side === 'front' ? 'QUADS' : 'HAMSTRINGS';
        const lower: MuscleKey | null = side === 'front' ? null : 'CALVES';
        const X = (x: number): number => x + ox;
        return (
            <g>
                <rect x={X(17)} y={22} width={20} height={13} rx={5} fill={f(torso)} />
                <rect x={X(19)} y={34} width={16} height={13} rx={4} fill={f(mid)} />
                <rect x={X(19)} y={46} width={16} height={8} rx={3} fill={f(pelvis)} />
                <circle cx={X(16)} cy={26} r={5.5} fill={f('SHOULDERS')} />
                <circle cx={X(38)} cy={26} r={5.5} fill={f('SHOULDERS')} />
                <rect x={X(10)} y={27} width={6} height={14} rx={3} fill={f(arm)} />
                <rect x={X(38)} y={27} width={6} height={14} rx={3} fill={f(arm)} />
                <rect x={X(9)} y={40} width={5.5} height={14} rx={2.75} fill={f('FOREARMS')} />
                <rect x={X(39.5)} y={40} width={5.5} height={14} rx={2.75} fill={f('FOREARMS')} />
                <rect x={X(19)} y={54} width={7.5} height={23} rx={3.5} fill={f(thigh)} />
                <rect x={X(27.5)} y={54} width={7.5} height={23} rx={3.5} fill={f(thigh)} />
                <rect x={X(20)} y={77} width={6} height={22} rx={3} fill={f(lower)} />
                <rect x={X(28)} y={77} width={6} height={22} rx={3} fill={f(lower)} />
                <rect x={X(24)} y={17} width={6} height={4} fill={BASE} />
                <circle cx={X(27)} cy={12} r={6.5} fill={BASE} />
            </g>
        );
    };

    return (
        <svg viewBox="0 0 104 106" className={className} role="img" aria-label={`Muscle ciblé : ${muscle ?? 'n/a'}`} preserveAspectRatio="xMidYMid meet">
            <Figure ox={0} side="front" />
            <Figure ox={50} side="back" />
        </svg>
    );
}
