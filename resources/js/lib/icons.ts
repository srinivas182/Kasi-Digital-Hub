import {
    BarChart3,
    BookOpen,
    Briefcase,
    Building2,
    CalendarDays,
    ClipboardList,
    Coins,
    Handshake,
    Home,
    Landmark,
    LayoutGrid,
    type LucideIcon,
    Map,
    QrCode,
    Rocket,
    Settings,
    ShieldCheck,
    Store,
    Users,
} from 'lucide-react';

/**
 * Icons that module manifests may reference by name in their "nav" section.
 * Only icons listed here are shipped, which keeps the bundle small.
 */
export const NAV_ICONS: Record<string, LucideIcon> = {
    'bar-chart': BarChart3,
    'book-open': BookOpen,
    briefcase: Briefcase,
    building: Building2,
    calendar: CalendarDays,
    clipboard: ClipboardList,
    coins: Coins,
    handshake: Handshake,
    home: Home,
    landmark: Landmark,
    grid: LayoutGrid,
    map: Map,
    qr: QrCode,
    rocket: Rocket,
    settings: Settings,
    shield: ShieldCheck,
    store: Store,
    users: Users,
};

export function navIcon(name: string | null | undefined): LucideIcon {
    return (name && NAV_ICONS[name]) || LayoutGrid;
}
