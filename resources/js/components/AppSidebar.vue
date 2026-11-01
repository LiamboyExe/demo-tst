<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, CreditCard, LayoutGrid, Shield, Users } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
    useSidebar,
} from '@/components/ui/sidebar';
import { account, dashboard } from '@/routes';
import branches from '@/routes/branches';
import members from '@/routes/members';
import nfcCards from '@/routes/nfc-cards';
import type { NavItem, User } from '@/types';

const { isMobile, setOpenMobile } = useSidebar();
const page = usePage();

const isMainAdmin = computed(() => {
    const user = page.props.auth?.user as User | undefined;
    return user?.role === 'main';
});

/**
 * Close the mobile drawer whenever a visit starts, so picking a navigation
 * item fades the sidebar out instead of leaving it covering the new page.
 */
let stopListeningForNavigation: (() => void) | undefined;

onMounted(() => {
    stopListeningForNavigation = router.on('navigate', () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    });
});

onUnmounted(() => {
    stopListeningForNavigation?.();
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Members',
        href: members.index(),
        icon: Users,
    },
    {
        title: isMainAdmin.value ? 'Territories' : 'Territory',
        href: branches.index(),
        icon: Building2,
    },
    {
        title: 'NFC Cards',
        href: nfcCards.index(),
        icon: CreditCard,
    },
    {
        title: 'My Account',
        href: account(),
        icon: Shield,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
