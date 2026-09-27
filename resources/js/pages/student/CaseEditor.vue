<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, CircleDotDashed } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import CaseProfileSection from './case-editor/CaseProfileSection.vue';
import HistoryDiagnosisSection from './case-editor/HistoryDiagnosisSection.vue';
import MedicationChartSection from './case-editor/MedicationChartSection.vue';
import VitalsInvestigationsSection from './case-editor/VitalsInvestigationsSection.vue';

type SectionId =
    | 'case_profile'
    | 'history_diagnosis'
    | 'vitals_investigations'
    | 'medication_chart'
    | 'soap'
    | 'clinical_activities';
type Section = { id: SectionId; label: string; available: boolean };

const props = defineProps<{
    clinicalCase: { id: string; case_number: number; status: string };
    userId: number;
    context: Record<string, unknown> & {
        lock_version: number;
        updated_at: string;
    };
    clinicalProfile:
        | (Record<string, unknown> & {
              lock_version: number;
              updated_at: string;
          })
        | null;
    vitals: (Record<string, unknown> & { id: string })[];
    investigations: (Record<string, unknown> & { id: string })[];
    medications: (Record<string, unknown> & { id: string })[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
        ],
    },
});

const sections: Section[] = [
    { id: 'case_profile', label: 'Case Profile', available: true },
    { id: 'history_diagnosis', label: 'History & Diagnosis', available: true },
    {
        id: 'vitals_investigations',
        label: 'Vitals & Investigations',
        available: true,
    },
    { id: 'medication_chart', label: 'Medication Chart', available: true },
    { id: 'soap', label: 'SOAP', available: false },
    {
        id: 'clinical_activities',
        label: 'Conditional Clinical Activities',
        available: false,
    },
];
const activeIndex = ref(0);
const tabButtons = ref<HTMLButtonElement[]>([]);
const activeSection = computed(() => sections[activeIndex.value]);
const availableCount = sections.filter((section) => section.available).length;
const progress = computed(
    () => `${activeIndex.value + 1} of ${availableCount}`,
);
const emptyClinicalProfile = {
    chief_complaints: null,
    history_present_illness: null,
    diagnoses: null,
    past_medical_history: null,
    past_medical_history_none: false,
    past_surgical_history: null,
    adherence_status: null,
    family_history: null,
    substance_history: null,
    examination_findings: null,
    allergy_status: 'unknown',
    allergy_substance: null,
    allergy_reaction: null,
    lock_version: 0,
    updated_at: new Date().toISOString(),
};

function setTabRef(element: unknown, index: number): void {
    if (element instanceof HTMLButtonElement) tabButtons.value[index] = element;
}

function goTo(index: number, moveFocus = false): void {
    const section = sections[index];
    if (!section) return;
    if (!section.available) {
        if (section.id === 'soap') {
            router.get(`/student/cases/${props.clinicalCase.id}/soap`);
        }
        return;
    }
    activeIndex.value = index;
    if (moveFocus) void nextTick(() => tabButtons.value[index]?.focus());
}

function moveAvailable(direction: -1 | 1, moveFocus = false): void {
    const next = activeIndex.value + direction;
    if (sections[next]?.available) goTo(next, moveFocus);
}

function handleTabKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        moveAvailable(1, true);
    }
    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        moveAvailable(-1, true);
    }
    if (event.key === 'Home') {
        event.preventDefault();
        goTo(0, true);
    }
    if (event.key === 'End') {
        event.preventDefault();
        goTo(1, true);
    }
}
</script>

<template>
    <Head :title="`Case #${clinicalCase.case_number} - Edit`" />
    <main
        class="mx-auto w-full max-w-2xl px-4 pt-5 pb-44 sm:px-6 md:pt-8 md:pb-32"
    >
        <header
            class="mb-5 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                    >
                        Clinical documentation
                    </p>
                    <h1
                        class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                    >
                        Case #{{ clinicalCase.case_number }}
                    </h1>
                </div>
                <p
                    class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    aria-live="polite"
                >
                    {{ progress }}
                </p>
            </div>
            <div
                class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                aria-hidden="true"
            >
                <div
                    class="h-full rounded-full bg-amber-600 transition-[width] duration-200"
                    :style="{
                        width: `${((activeIndex + 1) / availableCount) * 100}%`,
                    }"
                />
            </div>
            <p
                class="mt-3 flex items-center gap-1.5 text-xs text-slate-500"
                role="status"
            >
                <CircleDotDashed class="size-3.5" /> Each section saves
                independently. Offline changes stay on this device until they
                can sync.
            </p>
        </header>

        <nav
            aria-label="Case sections"
            class="mb-5 overflow-x-auto pb-1"
            @keydown="handleTabKeydown"
        >
            <div
                role="tablist"
                aria-label="Documentation sections"
                class="flex min-w-max gap-1.5"
            >
                <button
                    v-for="(section, index) in sections"
                    :id="`section-tab-${section.id}`"
                    :key="section.id"
                    :ref="(element) => setTabRef(element, index)"
                    type="button"
                    role="tab"
                    :aria-selected="index === activeIndex"
                    :aria-controls="
                        section.available
                            ? `section-panel-${section.id}`
                            : undefined
                    "
                    :aria-disabled="!section.available && section.id !== 'soap'"
                    :tabindex="index === activeIndex ? 0 : -1"
                    :data-test="`section-nav-${section.id}`"
                    class="shrink-0 rounded-full border px-3 py-2 text-xs font-bold transition focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2"
                    :class="[
                        index === activeIndex
                            ? 'border-[#0b2942] bg-[#0b2942] text-white'
                            : 'border-slate-200 bg-white text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
                        !section.available ? 'opacity-60' : '',
                    ]"
                    @click="goTo(index)"
                >
                    {{ section.label
                    }}<span v-if="!section.available" class="sr-only"
                        >, not yet available</span
                    >
                </button>
            </div>
        </nav>

        <div
            id="section-panel-case_profile"
            role="tabpanel"
            aria-labelledby="section-tab-case_profile"
            :hidden="activeSection.id !== 'case_profile'"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <CaseProfileSection
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial="context as any"
            />
        </div>
        <div
            id="section-panel-history_diagnosis"
            role="tabpanel"
            aria-labelledby="section-tab-history_diagnosis"
            :hidden="activeSection.id !== 'history_diagnosis'"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <HistoryDiagnosisSection
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial="(clinicalProfile ?? emptyClinicalProfile) as any"
            />
        </div>
        <div
            id="section-panel-vitals_investigations"
            role="tabpanel"
            aria-labelledby="section-tab-vitals_investigations"
            :hidden="activeSection.id !== 'vitals_investigations'"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <VitalsInvestigationsSection
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial-vitals="vitals as any"
                :initial-investigations="investigations as any"
                :initial-vitals-availability="
                    {
                        vitals_status: context.vitals_status,
                        vitals_unavailable_reason:
                            context.vitals_unavailable_reason,
                        lock_version: context.vitals_availability_lock_version,
                        updated_at: context.updated_at,
                    } as any
                "
                :initial-investigations-availability="
                    {
                        investigations_status: context.investigations_status,
                        investigations_unavailable_reason:
                            context.investigations_unavailable_reason,
                        lock_version:
                            context.investigations_availability_lock_version,
                        updated_at: context.updated_at,
                    } as any
                "
            />
        </div>
        <div
            id="section-panel-medication_chart"
            role="tabpanel"
            aria-labelledby="section-tab-medication_chart"
            :hidden="activeSection.id !== 'medication_chart'"
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <MedicationChartSection
                :case-id="clinicalCase.id"
                :user-id="userId"
                :initial-medications="medications as any"
                :initial-availability="
                    {
                        medication_chart_status:
                            context.medication_chart_status,
                        medication_chart_none_reason:
                            context.medication_chart_none_reason,
                        lock_version:
                            context.medication_chart_availability_lock_version,
                        updated_at: context.updated_at,
                    } as any
                "
            />
        </div>

        <p
            v-if="!activeSection.available"
            class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
            role="status"
        >
            {{ activeSection.label }} is represented in this editor and will
            become available in its planned Slice 2 work.
        </p>

        <div
            class="fixed inset-x-0 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-[45] border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur md:bottom-0 md:z-20 dark:border-slate-700 dark:bg-slate-950/95"
        >
            <div
                class="mx-auto flex max-w-2xl items-center justify-between gap-3"
            >
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-xl px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-amber-600 disabled:opacity-40 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="activeIndex === 0"
                    @click="moveAvailable(-1)"
                >
                    <ChevronLeft class="size-4" /> Previous</button
                ><button
                    type="button"
                    class="flex items-center gap-1 rounded-xl bg-[#0b2942] px-4 py-2 text-sm font-bold text-white transition hover:bg-[#164566] focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 disabled:opacity-40"
                    :disabled="activeIndex === availableCount - 1"
                    @click="moveAvailable(1)"
                >
                    Next <ChevronRight class="size-4" />
                </button>
            </div>
        </div>
    </main>
</template>
