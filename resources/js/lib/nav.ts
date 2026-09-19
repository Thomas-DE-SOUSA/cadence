import { Activity, CalendarCheck, CalendarDays, Dumbbell, Gauge, LayoutDashboard, Lightbulb, Scale, Sparkles, TrendingUp, Utensils, type LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
}

// Profile is reached via the athlete chip in the top bar, so it stays out of the
// main nav to keep the (mobile) bar uncluttered. Strength is reached via the world
// switch in the top bar, not the nav — two worlds, one uncluttered bar each.
export const navItems: NavItem[] = [
    { label: 'Tableau de bord', href: '/', icon: LayoutDashboard },
    { label: 'Progression', href: '/progression', icon: TrendingUp },
    { label: 'Forme', href: '/fitness', icon: Activity },
    { label: 'Programme', href: '/program', icon: CalendarDays },
    { label: 'Allures', href: '/paces', icon: Gauge },
    { label: 'Conseil', href: '/advice', icon: Lightbulb },
];

// The Strength world's own nav: the agenda (place sessions on days), the session
// templates library, and the strength progression.
export const strengthNavItems: NavItem[] = [
    { label: 'Agenda', href: '/strength', icon: CalendarCheck },
    { label: 'Séances', href: '/strength/sessions', icon: Dumbbell },
    { label: 'Progression', href: '/strength/progression', icon: TrendingUp },
    { label: 'Poids', href: '/strength/weight', icon: Scale },
    { label: 'Nutrition', href: '/strength/nutrition', icon: Utensils },
    { label: 'Bilan', href: '/strength/review', icon: Sparkles },
];
