<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    GraduationCap,
    MapPinned,
    Users,
} from '@lucide/vue';

defineProps<{ eyebrow: string; title: string; description: string }>();

const sections = [
    { label: 'Academic', href: '/admin/academic', icon: GraduationCap },
    { label: 'Sites', href: '/admin/clinical-sites', icon: MapPinned },
    { label: 'People', href: '/admin/people', icon: Users },
    { label: 'Rotations', href: '/admin/rotations', icon: Building2 },
];
</script>

<template>
    <header
        class="overflow-hidden rounded-[1.75rem] bg-[#071a2b] text-white shadow-[0_24px_70px_-38px_rgba(7,26,43,0.8)]"
    >
        <div class="relative px-5 py-7 sm:px-8 sm:py-9">
            <div
                class="absolute -top-20 right-0 size-64 rounded-full bg-amber-400/10 blur-3xl"
            />
            <Link
                href="/admin"
                class="relative inline-flex items-center gap-2 text-xs font-semibold text-slate-300 transition hover:text-white"
            >
                <ArrowLeft class="size-4" /> Administration home
            </Link>
            <p
                class="relative mt-6 text-xs font-bold tracking-[0.2em] text-[#f0ce77] uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1
                class="font-display relative mt-2 max-w-3xl text-3xl leading-tight font-semibold tracking-tight sm:text-5xl"
            >
                {{ title }}
            </h1>
            <p
                class="relative mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base"
            >
                {{ description }}
            </p>
        </div>
        <nav
            aria-label="Administration sections"
            class="grid grid-cols-2 border-t border-white/10 bg-white/5 sm:grid-cols-4"
        >
            <Link
                v-for="section in sections"
                :key="section.href"
                :href="section.href"
                class="flex min-h-14 items-center justify-center gap-2 border-r border-white/10 px-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"
            >
                <component :is="section.icon" class="size-4 text-[#f0ce77]" />
                {{ section.label }}
            </Link>
        </nav>
    </header>
</template>
