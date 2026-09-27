<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import RoleBottomNav from '@/components/RoleBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import { deleteLegacyCaseDraftDatabase } from '@/lib/legacyCaseDraftCleanup';
import type { BreadcrumbItem } from '@/types';
import { onMounted } from 'vue';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

onMounted(() => {
    deleteLegacyCaseDraftDatabase();
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="min-w-0 overflow-x-clip">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
            <RoleBottomNav />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
