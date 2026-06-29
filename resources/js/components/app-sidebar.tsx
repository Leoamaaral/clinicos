import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    CalendarCheck,
    ClipboardList,
    LayoutGrid,
    BarChart3,
    Receipt,
    Settings,
    Sparkles,
    Users,
    UserCog,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { routes } from '@/lib/clinic';
import { dashboard } from '@/routes';
import type { Auth } from '@/types';
import type { NavItem } from '@/types';

type PageProps = {
    auth: Auth & { isAdmin?: boolean };
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const cleanup = useMobileNavigation();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Clientes',
            href: routes.clients.index(),
            icon: Users,
        },
        {
            title: 'Agenda',
            href: routes.appointments.index(),
            icon: Calendar,
        },
        {
            title: 'Concluir agendas',
            href: routes.appointments.complete.index(),
            icon: CalendarCheck,
        },
        {
            title: 'Tratamentos',
            href: routes.treatments.index(),
            icon: Sparkles,
        },
        {
            title: 'Orçamentos',
            href: routes.quotes.index(),
            icon: Receipt,
        },
        {
            title: 'Relatórios',
            href: routes.reports.index(),
            icon: BarChart3,
        },
        {
            title: 'Anamnese',
            href: routes.anamnesisQuestions.index(),
            icon: ClipboardList,
        },
    ];

    if (auth.isAdmin) {
        mainNavItems.push(
            {
                title: 'Profissionais',
                href: routes.admin.users.index(),
                icon: UserCog,
            },
            {
                title: 'Configurações',
                href: routes.admin.settings(),
                icon: Settings,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch onClick={cleanup}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
