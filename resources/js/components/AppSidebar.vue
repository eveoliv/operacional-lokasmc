<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarRange,
    Building2,
    ClipboardCheck,
    FileClock,
    KeyRound,
    LayoutGrid,
    UserRoundCog,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as accessGrants } from '@/routes/access-grants';
import { index as attendanceSessions } from '@/routes/attendance-sessions';
import { index as audit } from '@/routes/audit';
import { index as events } from '@/routes/events';
import { index as organization } from '@/routes/organization';
import { index as people } from '@/routes/people';
import { index as registrations } from '@/routes/registrations';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';
const page = usePage();
const capabilities = computed(() => page.props.auth.user.capabilities ?? []);
const can = (codes: string[]) =>
    codes.some((code) => capabilities.value.includes(code));
const mainNavItems = computed<NavItem[]>(() => [
    { title: 'Painel', href: dashboard(), icon: LayoutGrid },
    ...(can(['organization.view', 'organization.manage'])
        ? [{ title: 'Organização', href: organization(), icon: Building2 }]
        : []),
    ...(can(['people.view', 'people.manage'])
        ? [{ title: 'Pessoas', href: people(), icon: UsersRound }]
        : []),
    ...(can(['users.view', 'users.manage'])
        ? [{ title: 'Usuários', href: users(), icon: UserRoundCog }]
        : []),
    ...(can(['access.manage'])
        ? [{ title: 'Acessos', href: accessGrants(), icon: KeyRound }]
        : []),
    ...(can(['events.view', 'events.manage'])
        ? [{ title: 'Eventos', href: events(), icon: CalendarDays }]
        : []),
    ...(can(['registrations.view', 'registrations.manage'])
        ? [{ title: 'Inscrições', href: registrations(), icon: ClipboardCheck }]
        : []),
    ...(can(['attendance.view', 'attendance.manage'])
        ? [
              {
                  title: 'Frequência',
                  href: attendanceSessions(),
                  icon: CalendarRange,
              },
          ]
        : []),
    ...(can(['audit.view'])
        ? [{ title: 'Auditoria', href: audit(), icon: FileClock }]
        : []),
]);
const footerNavItems: NavItem[] = [];
</script>
<template>
    <Sidebar collapsible="icon" variant="inset"
        ><SidebarHeader
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton size="lg" as-child
                        ><Link :href="dashboard()"
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem></SidebarMenu></SidebarHeader
        ><SidebarContent><NavMain :items="mainNavItems" /></SidebarContent
        ><SidebarFooter
            ><NavFooter
                :items="footerNavItems" /><NavUser /></SidebarFooter></Sidebar
    ><slot />
</template>
