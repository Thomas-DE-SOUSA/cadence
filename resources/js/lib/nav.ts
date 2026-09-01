import { Activity, CalendarCheck, CalendarDays, Dumbbell, Gauge, LayoutDashboard, Lightbulb, Scale, TrendingUp, type LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
}

// Profil is reached via the athlete chip in the top bar, so it stays out of the
// main nav to keep the (mobile) bar uncluttered. Muscu is reached via the world
// switch in the top bar, not the nav — two worlds, one uncluttered bar each.
export const navItems: NavItem[] = [
    { label: 'Tableau de bord', href: '/', icon: LayoutDashboard },
    { label: 'Progression', href: '/progression', icon: TrendingUp },
    { label: 'Forme', href: '/forme', icon: Activity },
    { label: 'Programme', href: '/programme', icon: CalendarDays },
    { label: 'Allures', href: '/allures', icon: Gauge },
    { label: 'Conseil', href: '/conseil', icon: Lightbulb },
];

// The Muscu world's own nav: the agenda (place séances on days), the séance
// templates library, and the strength progression.
export const muscuNavItems: NavItem[] = [
    { label: 'Agenda', href: '/muscu', icon: CalendarCheck },
    { label: 'Séances', href: '/muscu/seances', icon: Dumbbell },
    { label: 'Progression', href: '/muscu/progression', icon: TrendingUp },
    { label: 'Poids', href: '/muscu/poids', icon: Scale },
];
