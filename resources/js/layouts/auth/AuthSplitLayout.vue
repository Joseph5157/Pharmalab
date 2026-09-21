<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col overflow-hidden bg-[#071a2b] p-12 text-white lg:flex dark:border-r"
        >
            <div
                class="absolute top-1/3 -left-32 size-96 rounded-full bg-emerald-500/10 blur-3xl"
            />
            <div
                class="absolute -top-24 -right-24 size-80 rounded-full bg-[#d5a83f]/15 blur-3xl"
            />
            <div
                class="absolute inset-0 opacity-[0.06]"
                style="
                    background-image: radial-gradient(
                        circle,
                        white 1px,
                        transparent 1px
                    );
                    background-size: 22px 22px;
                "
            />
            <Link
                :href="home()"
                class="font-display relative z-20 flex items-center text-xl font-semibold tracking-tight"
            >
                <AppLogoIcon class="mr-3 size-10 text-[#123b59]" />
                {{ name }}
            </Link>
            <div class="relative z-20 my-auto max-w-xl">
                <p
                    class="mb-5 text-xs font-semibold tracking-[0.22em] text-[#f0ce77] uppercase"
                >
                    Clinical learning, connected
                </p>
                <h2
                    class="font-display text-5xl leading-[1.08] font-semibold tracking-[-0.03em]"
                >
                    Document carefully.<br />Review meaningfully.
                </h2>
                <p class="mt-6 max-w-md text-base leading-7 text-slate-300">
                    A secure learning record for pharmacy rotations—from first
                    draft to faculty-approved portfolio.
                </p>
            </div>
            <p class="relative z-20 text-xs text-slate-400">
                Educational records only · Never a clinical order
            </p>
        </div>
        <div class="bg-[#f8fafb] px-6 py-10 lg:p-12 dark:bg-slate-950">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground text-sm" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
