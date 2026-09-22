<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ClipboardCheck,
    FileClock,
    GraduationCap,
    LayoutGrid,
    MapPinned,
    Users,
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
import type { NavItem } from '@/types';

const page = usePage();
const role = computed(() => page.props.auth.user.role);
const homeHref = computed(
    () =>
        ({ student: '/student', faculty: '/faculty', administrator: '/admin' })[
            role.value
        ],
);

const mainNavItems = computed<NavItem[]>(() => {
    const dashboard = {
        title: 'Dashboard',
        href: homeHref.value,
        icon: LayoutGrid,
    };

    if (role.value === 'student') {
        return [
            dashboard,
            { title: 'Cases', href: '#', icon: ClipboardCheck },
            { title: 'Portfolio', href: '#', icon: GraduationCap },
        ];
    }

    if (role.value === 'faculty') {
        return [
            dashboard,
            { title: 'Review queue', href: '#', icon: FileClock },
            { title: 'Students', href: '#', icon: Users },
        ];
    }

    return [
        dashboard,
        { title: 'People', href: '/admin/people', icon: Users },
        {
            title: 'Academic setup',
            href: '/admin/academic',
            icon: GraduationCap,
        },
        {
            title: 'Clinical sites',
            href: '/admin/clinical-sites',
            icon: MapPinned,
        },
        { title: 'Rotations', href: '/admin/rotations', icon: ClipboardCheck },
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Help & guidance',
        href: '#',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref" prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
