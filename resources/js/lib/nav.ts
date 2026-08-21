import { CalendarDays, Gauge, LayoutDashboard, TrendingUp, User, type LucideIcon } from 'lucide-react';

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
    { label: 'Profil', href: '/profil', icon: User },
];
