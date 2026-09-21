<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

type Case = {
    id: string;
    status: string;
    case_number: number;
};

type SoapNote = {
    id: string | null;
    subjective: string | null;
    objective: string | null;
    assessment: string | null;
    plan: string | null;
    revision_number: number;
} | null;

const props = defineProps<{
    clinicalCase: Case;
    soap: SoapNote;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Student', href: '/student' },
            { title: 'Clinical Cases', href: '/student/cases' },
            { title: 'SOAP', href: '/student/cases/soap' },
        ],
    },
});

const form = useForm({
    subjective: props.soap?.subjective ?? '',
    objective: props.soap?.objective ?? '',
    assessment: props.soap?.assessment ?? '',
    plan: props.soap?.plan ?? '',
});

const save = () =>
    form.put(`/student/cases/${props.clinicalCase.id}/soap`, {
        preserveScroll: true,
    });
</script>

<template>
    <Head title="SOAP Note" />
    <main
        class="mx-auto w-full max-w-3xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                SOAP documentation
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Case #{{ clinicalCase.case_number }}
            </h1>
        </div>

        <form
            class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
            @submit.prevent="save"
        >
            <div>
                <label for="soap-subjective" class="text-sm font-semibold text-[#0b2942] dark:text-white">
                    Subjective
                </label>
                <p class="text-xs text-slate-500">Patient-reported symptoms, history, and concerns.</p>
                <textarea
                    id="soap-subjective"
                    v-model="form.subjective"
                    class="border-input bg-background mt-2 min-h-[120px] w-full rounded-md border px-3 py-2 text-sm"
                    :disabled="clinicalCase.status === 'submitted' || clinicalCase.status === 'approved'"
                />
                <InputError :message="form.errors.subjective" />
            </div>

            <div>
                <label for="soap-objective" class="text-sm font-semibold text-[#0b2942] dark:text-white">
                    Objective
                </label>
                <p class="text-xs text-slate-500">Vital signs, examination findings, lab results.</p>
                <textarea
                    id="soap-objective"
                    v-model="form.objective"
                    class="border-input bg-background mt-2 min-h-[120px] w-full rounded-md border px-3 py-2 text-sm"
                    :disabled="clinicalCase.status === 'submitted' || clinicalCase.status === 'approved'"
                />
                <InputError :message="form.errors.objective" />
            </div>

            <div>
                <label for="soap-assessment" class="text-sm font-semibold text-[#0b2942] dark:text-white">
                    Assessment
                </label>
                <p class="text-xs text-slate-500">Clinical impression, diagnosis, or problem list.</p>
                <textarea
                    id="soap-assessment"
                    v-model="form.assessment"
                    class="border-input bg-background mt-2 min-h-[120px] w-full rounded-md border px-3 py-2 text-sm"
                    :disabled="clinicalCase.status === 'submitted' || clinicalCase.status === 'approved'"
                />
                <InputError :message="form.errors.assessment" />
            </div>

            <div>
                <label for="soap-plan" class="text-sm font-semibold text-[#0b2942] dark:text-white">
                    Plan
                </label>
                <p class="text-xs text-slate-500">Treatment plan, follow-up, and counselling.</p>
                <textarea
                    id="soap-plan"
                    v-model="form.plan"
                    class="border-input bg-background mt-2 min-h-[120px] w-full rounded-md border px-3 py-2 text-sm"
                    :disabled="clinicalCase.status === 'submitted' || clinicalCase.status === 'approved'"
                />
                <InputError :message="form.errors.plan" />
            </div>

            <Button
                v-if="clinicalCase.status === 'draft' || clinicalCase.status === 'returned'"
                type="submit"
                class="w-full bg-[#0b2942] text-white sm:w-auto"
                :disabled="form.processing"
            >
                <Save class="size-4 mr-1" /> Save
            </Button>
        </form>
    </main>
</template>
