<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardCheck,
    ShieldCheck,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Clinical learning, connected" />

    <main
        class="relative min-h-screen overflow-hidden bg-[#f5f8fa] text-[#0b2942] dark:bg-slate-950 dark:text-white"
    >
        <div
            class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#0b2942] via-[#1d6f78] to-[#d6a83e]"
        />
        <div
            class="absolute top-20 -right-40 size-[32rem] rounded-full bg-[#d6a83e]/10 blur-3xl"
        />
        <div
            class="absolute bottom-0 -left-52 size-[30rem] rounded-full bg-teal-600/10 blur-3xl"
        />

        <div
            class="relative mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8 lg:px-10"
        >
            <header class="flex items-center justify-between">
                <div
                    class="font-display flex items-center gap-3 text-xl font-semibold tracking-tight"
                >
                    <AppLogoIcon class="size-11 text-[#123b59]" />
                    <span>Pharmalab</span>
                </div>
                <Link
                    :href="$page.props.auth.user ? dashboard() : login()"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#0b2942] px-5 text-sm font-semibold text-white shadow-lg shadow-[#0b2942]/15 transition hover:-translate-y-0.5 hover:bg-[#123b59]"
                >
                    {{
                        $page.props.auth.user
                            ? 'Open dashboard'
                            : 'Institution sign in'
                    }}
                    <ArrowRight class="size-4" />
                </Link>
            </header>

            <section
                class="grid flex-1 items-center gap-14 py-16 lg:grid-cols-[1.05fr_0.95fr]"
            >
                <div>
                    <p
                        class="mb-6 flex items-center gap-3 text-xs font-semibold tracking-[0.2em] text-[#8a6515] uppercase dark:text-[#f0ce77]"
                    >
                        <span class="h-px w-10 bg-current" />
                        Clinical learning, connected
                    </p>
                    <h1
                        class="font-display max-w-3xl text-5xl leading-[1.04] font-semibold tracking-[-0.04em] sm:text-6xl lg:text-7xl"
                    >
                        From bedside notes to thoughtful review.
                    </h1>
                    <p
                        class="mt-7 max-w-xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300"
                    >
                        A secure, mobile-first learning record for pharmacy
                        rotations. Document de-identified cases, receive faculty
                        guidance, and build an approved portfolio.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-4">
                        <Link
                            :href="login()"
                            class="inline-flex min-h-12 items-center gap-2 rounded-xl bg-[#0b2942] px-6 font-semibold text-white shadow-xl shadow-[#0b2942]/15 transition hover:-translate-y-0.5"
                        >
                            Sign in to your workspace
                            <ArrowRight class="size-4" />
                        </Link>
                        <span
                            class="inline-flex min-h-12 items-center gap-2 rounded-xl border border-slate-200 bg-white/70 px-5 text-sm font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300"
                        >
                            <ShieldCheck class="size-4 text-emerald-600" />
                            Institution-managed access
                        </span>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="absolute -inset-5 -z-10 rotate-2 rounded-[2.25rem] bg-[#d6a83e]/20"
                    />
                    <div
                        class="rounded-[2rem] border border-white/60 bg-white/85 p-6 shadow-[0_35px_90px_-40px_rgba(11,41,66,0.45)] backdrop-blur sm:p-8 dark:border-slate-700 dark:bg-slate-900/85"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-200 pb-5 dark:border-slate-700"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-[0.14em] text-slate-500 uppercase"
                                >
                                    Learning case
                                </p>
                                <p
                                    class="font-display mt-1 text-2xl font-semibold"
                                >
                                    Case SIMS-024
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800"
                                >In progress</span
                            >
                        </div>
                        <div class="mt-6 space-y-4">
                            <div
                                v-for="(item, index) in [
                                    'Case details',
                                    'Medication chart',
                                    'SOAP note',
                                    'Faculty review',
                                ]"
                                :key="item"
                                class="flex items-center gap-4 rounded-2xl border border-slate-100 p-4 dark:border-slate-700"
                            >
                                <span
                                    class="grid size-9 place-items-center rounded-xl"
                                    :class="
                                        index < 2
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                                    "
                                >
                                    <CheckCircle2
                                        v-if="index < 2"
                                        class="size-5"
                                    />
                                    <ClipboardCheck v-else class="size-5" />
                                </span>
                                <div class="flex-1">
                                    <p class="font-semibold">{{ item }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        {{
                                            index < 2
                                                ? 'Complete'
                                                : index === 2
                                                  ? 'Continue documentation'
                                                  : 'Available after submission'
                                        }}
                                    </p>
                                </div>
                                <span class="text-sm font-medium text-slate-400"
                                    >0{{ index + 1 }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <footer
                class="flex flex-col gap-2 border-t border-slate-200 pt-5 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
            >
                <span
                    >Educational documentation—not a clinical order or patient
                    EHR.</span
                >
                <span
                    >Phase 1 foundation · SIMS Clinical Learning Platform</span
                >
            </footer>
        </div>
    </main>
</template>
