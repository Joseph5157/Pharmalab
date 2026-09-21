<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    GraduationCap,
    MapPinned,
    Users,
} from '@lucide/vue';
import RoleDashboard from '@/components/dashboard/RoleDashboard.vue';

defineProps<{
    metrics: Array<{ label: string; value: string; detail: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Administration', href: '/admin' }] },
});
</script>

<template>
    <Head title="Administration" />
    <RoleDashboard
        eyebrow="Institution operations"
        title="Build the structure learning depends on."
        description="Manage programmes, clinical sites, people, and rotations without crossing the boundary into student academic decisions."
        :metrics="metrics"
        next-action="Complete academic setup"
        next-action-detail="Create the academic structure, people, sites, rotations, and assignments required by the walking skeleton."
        accent="administrator"
    />
    <section
        class="mx-auto -mt-20 w-full max-w-7xl px-4 pb-28 sm:px-6 md:pb-10"
    >
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="item in [
                    {
                        label: 'Academic',
                        detail: 'Programmes and cohorts',
                        href: '/admin/academic',
                        icon: GraduationCap,
                    },
                    {
                        label: 'Clinical sites',
                        detail: 'Hospitals, departments, wards',
                        href: '/admin/clinical-sites',
                        icon: MapPinned,
                    },
                    {
                        label: 'People',
                        detail: 'Students and faculty',
                        href: '/admin/people',
                        icon: Users,
                    },
                    {
                        label: 'Rotations',
                        detail: 'Dates and assignments',
                        href: '/admin/rotations',
                        icon: Building2,
                    },
                ]"
                :key="item.href"
                :href="item.href"
                class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 dark:border-slate-700 dark:bg-slate-900"
                ><span
                    class="grid size-10 place-items-center rounded-xl bg-[#0b2942] text-white"
                    ><component :is="item.icon" class="size-5" /></span
                ><span class="min-w-0 flex-1"
                    ><span
                        class="block font-semibold text-[#0b2942] dark:text-white"
                        >{{ item.label }}</span
                    ><span class="block truncate text-xs text-slate-500">{{
                        item.detail
                    }}</span></span
                ><ArrowRight
                    class="size-4 text-slate-400 transition group-hover:translate-x-1 group-hover:text-amber-600"
            /></Link>
        </div>
    </section>
</template>
