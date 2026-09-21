<script setup lang="ts">
import { ArrowUpRight, CheckCircle2, Clock3, ShieldCheck } from '@lucide/vue';

type Metric = {
    label: string;
    value: string;
    detail: string;
};

defineProps<{
    eyebrow: string;
    title: string;
    description: string;
    metrics: Metric[];
    nextAction: string;
    nextActionDetail: string;
    accent: 'student' | 'faculty' | 'administrator';
}>();

const accentClasses = {
    student: 'from-teal-600 to-emerald-700',
    faculty: 'from-blue-700 to-indigo-800',
    administrator: 'from-amber-600 to-orange-700',
};
</script>

<template>
    <main
        class="mx-auto w-full max-w-7xl px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <section
            class="relative isolate overflow-hidden rounded-[1.75rem] bg-[#071a2b] px-6 py-8 text-white shadow-[0_24px_70px_-35px_rgba(7,26,43,0.65)] sm:px-9 sm:py-10"
        >
            <div
                class="absolute -top-24 -right-20 -z-10 size-72 rounded-full bg-[#d5a83f]/15 blur-3xl"
            />
            <div
                class="absolute right-0 bottom-0 -z-10 h-full w-2/5 opacity-[0.08]"
                style="
                    background-image: radial-gradient(
                        circle,
                        white 1px,
                        transparent 1px
                    );
                    background-size: 18px 18px;
                "
            />

            <div class="max-w-3xl">
                <p
                    class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-[0.2em] text-[#f0ce77] uppercase"
                >
                    <span class="h-px w-8 bg-[#f0ce77]" />
                    {{ eyebrow }}
                </p>
                <h1
                    class="font-display text-3xl leading-tight font-semibold tracking-[-0.025em] sm:text-5xl"
                >
                    {{ title }}
                </h1>
                <p
                    class="mt-4 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base"
                >
                    {{ description }}
                </p>
            </div>
        </section>

        <section
            class="relative z-10 -mt-4 grid gap-3 px-3 sm:grid-cols-3 sm:gap-4 sm:px-6"
        >
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-[0_16px_40px_-30px_rgba(15,23,42,0.45)] dark:border-slate-700 dark:bg-slate-900"
            >
                <p
                    class="text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase dark:text-slate-400"
                >
                    {{ metric.label }}
                </p>
                <p
                    class="mt-3 text-2xl font-semibold tracking-tight text-[#0b2942] dark:text-white"
                >
                    {{ metric.value }}
                </p>
                <p
                    class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400"
                >
                    {{ metric.detail }}
                </p>
            </article>
        </section>

        <section class="mt-8 grid gap-5 lg:grid-cols-[1.35fr_0.65fr]">
            <article
                class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-[0.16em] text-slate-500 uppercase"
                        >
                            Next milestone
                        </p>
                        <h2
                            class="font-display mt-2 text-2xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            {{ nextAction }}
                        </h2>
                        <p
                            class="mt-2 max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                        >
                            {{ nextActionDetail }}
                        </p>
                    </div>
                    <div
                        :class="accentClasses[accent]"
                        class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br text-white shadow-lg"
                    >
                        <ArrowUpRight class="size-5" />
                    </div>
                </div>

                <div
                    class="mt-7 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                >
                    <div
                        :class="accentClasses[accent]"
                        class="h-full w-1/3 rounded-full bg-gradient-to-r"
                    />
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Foundation ready · Clinical workflow coming next
                </p>
            </article>

            <aside
                class="rounded-3xl border border-[#d9bd76]/40 bg-[#fffaf0] p-6 dark:border-[#d9bd76]/20 dark:bg-[#201b10]"
            >
                <div
                    class="flex items-center gap-3 text-[#725414] dark:text-[#f0ce77]"
                >
                    <ShieldCheck class="size-5" />
                    <h2 class="font-semibold">Learning record safeguards</h2>
                </div>
                <ul
                    class="mt-5 space-y-4 text-sm text-slate-700 dark:text-slate-300"
                >
                    <li class="flex gap-3">
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-600"
                        />
                        Institution-scoped access
                    </li>
                    <li class="flex gap-3">
                        <CheckCircle2
                            class="mt-0.5 size-4 shrink-0 text-emerald-600"
                        />
                        De-identification by design
                    </li>
                    <li class="flex gap-3">
                        <Clock3 class="mt-0.5 size-4 shrink-0 text-amber-600" />
                        Immutable reviews arrive in Gate 3
                    </li>
                </ul>
            </aside>
        </section>
    </main>
</template>
