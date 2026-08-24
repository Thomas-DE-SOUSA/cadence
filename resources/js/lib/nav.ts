import { CalendarDays, Gauge, LayoutDashboard, Lightbulb, TrendingUp, User, type LucideIcon } from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
}

export const navItems: NavItem[] = [
    { label: 'Tableau de bord', href: '/', icon: LayoutDashboard },
    { label: 'Progression', href: '/progression', icon: TrendingUp },
    { label: 'Programme', href: '/programme', icon: CalendarDays },
    { label: 'Allures', href: '/allures', icon: Gauge },
    { label: 'Conseil', href: '/conseil', icon: Lightbulb },
    { label: 'Profil', href: '/profil', icon: User },
];
