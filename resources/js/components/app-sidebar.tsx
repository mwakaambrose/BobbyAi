import { Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    History,
    LayoutGrid,
    Palette,
    PenLine,
    Plug,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, studio } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { index as autopilotIndex } from '@/routes/autopilot';
import { edit as brandEdit } from '@/routes/brand';
import { index as runsIndex } from '@/routes/runs';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const teamSlug = page.props.currentTeam?.slug;

    const overviewNavItems: NavItem[] = [
        {
            title: 'Home',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    /* The nav splits along the only division that matters day to day: the
       work you do with the agent, and the setup that shapes what it does. */
    const workNavItems: NavItem[] = teamSlug
        ? [
              { title: 'Studio', href: studio(teamSlug), icon: PenLine },
              {
                  title: 'Autopilot',
                  href: autopilotIndex(teamSlug),
                  icon: CalendarClock,
              },
              { title: 'Runs', href: runsIndex(teamSlug), icon: History },
          ]
        : [];

    const setupNavItems: NavItem[] = teamSlug
        ? [
              { title: 'Accounts', href: accountsIndex(teamSlug), icon: Plug },
              { title: 'Brand', href: brandEdit(teamSlug), icon: Palette },
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-5">
                <NavMain items={overviewNavItems} />
                <NavMain items={workNavItems} label="Work" />
                <NavMain items={setupNavItems} label="Setup" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
