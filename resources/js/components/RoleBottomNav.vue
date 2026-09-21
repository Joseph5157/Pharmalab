<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BookOpen,
    ClipboardList,
    Home,
    Plus,
    Settings,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const role = computed(() => page.props.auth.user.role);

const items = computed(() => {
    if (role.value === 'student') {
        return [
            { label: 'Home', href: '/student', icon: Home },
            { label: 'Cases', href: '#', icon: ClipboardList },
            { label: 'Add', href: '#', icon: Plus, primary: true },
            { label: 'Reference', href: '#', icon: BookOpen },
            { label: 'Profile', href: '/settings/profile', icon: Settings },
        ];
    }

    if (role.value === 'faculty') {
        return [
            { label: 'Home', href: '/faculty', icon: Home },
            { label: 'Review', href: '#', icon: ClipboardList },
            { label: 'Students', href: '#', icon: Users },
            { label: 'Alerts', href: '#', icon: Bell },
            { label: 'Profile', href: '/settings/profile', icon: Settings },
        ];
    }

    return [
        { label: 'Home', href: '/admin', icon: Home },
        { label: 'People', href: '/admin/people', icon: Users },
        { label: 'Rotations', href: '/admin/rotations', icon: ClipboardList },
        { label: 'Profile', href: '/settings/profile', icon: Settings },
    ];
});
</script>

<template>
    <nav
        aria-label="Primary"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_-12px_30px_-24px_rgba(15,23,42,0.5)] backdrop-blur md:hidden dark:border-slate-700 dark:bg-slate-950/95"
    >
        <div class="mx-auto flex max-w-lg items-end justify-around">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                :aria-disabled="item.href === '#'"
                class="flex min-h-12 min-w-12 flex-col items-center justify-center gap-1 rounded-xl px-2 text-[0.65rem] font-semibold text-slate-500 transition-colors hover:text-[#0b2942] dark:text-slate-400 dark:hover:text-white"
                :class="
                    item.primary
                        ? '-mt-5 bg-[#0b2942] text-white shadow-lg hover:text-white'
                        : ''
                "
            >
                <component :is="item.icon" class="size-5" />
                <span>{{ item.label }}</span>
            </Link>
        </div>
    </nav>
</template>
