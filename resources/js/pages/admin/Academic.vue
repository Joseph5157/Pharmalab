<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { BookOpen, CalendarRange, Plus } from '@lucide/vue';
import AdminWorkspaceHeader from '@/components/admin/AdminWorkspaceHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Cohort = {
    id: string;
    name: string;
    admission_year: number;
    academic_year_label: string;
    status: string;
};
type Programme = {
    id: string;
    name: string;
    code: string;
    duration_years: number;
    status: string;
    cohorts: Cohort[];
};

defineProps<{ programmes: Programme[] }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin' },
            { title: 'Academic setup', href: '/admin/academic' },
        ],
    },
});

const programmeForm = useForm({ name: '', code: '', duration_years: 4 });
const cohortForm = useForm({
    programme_id: '',
    name: '',
    admission_year: new Date().getFullYear(),
    academic_year_label: '',
});

const createProgramme = () =>
    programmeForm.post('/admin/programmes', {
        preserveScroll: true,
        onSuccess: () => programmeForm.reset(),
    });
const createCohort = () =>
    cohortForm.post('/admin/cohorts', {
        preserveScroll: true,
        onSuccess: () => cohortForm.reset(),
    });
</script>

<template>
    <Head title="Academic setup" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <AdminWorkspaceHeader
            eyebrow="Academic framework"
            title="Shape the learning pathway."
            description="Create programmes first, then organise students into named cohorts. This structure stays separate from clinical case content."
        />

        <section class="grid gap-5 lg:grid-cols-2">
            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createProgramme"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        ><BookOpen class="size-5"
                    /></span>
                    <div>
                        <h2
                            class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            New programme
                        </h2>
                        <p class="text-sm text-slate-500">
                            A stable academic pathway such as Pharm.D.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <Label for="programme-name">Programme name</Label
                        ><Input
                            id="programme-name"
                            v-model="programmeForm.name"
                            class="mt-2"
                            placeholder="Doctor of Pharmacy"
                        /><InputError :message="programmeForm.errors.name" />
                    </div>
                    <div>
                        <Label for="programme-code">Code</Label
                        ><Input
                            id="programme-code"
                            v-model="programmeForm.code"
                            class="mt-2 uppercase"
                            placeholder="PHARMD"
                        /><InputError :message="programmeForm.errors.code" />
                    </div>
                    <div>
                        <Label for="duration">Duration (years)</Label
                        ><Input
                            id="duration"
                            v-model="programmeForm.duration_years"
                            class="mt-2"
                            type="number"
                            min="1"
                            max="10"
                        /><InputError
                            :message="programmeForm.errors.duration_years"
                        />
                    </div>
                </div>
                <Button
                    class="mt-6 w-full bg-[#0b2942] text-white sm:w-auto"
                    :disabled="programmeForm.processing"
                    ><Plus class="size-4" /> Create programme</Button
                >
            </form>

            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createCohort"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                        ><CalendarRange class="size-5"
                    /></span>
                    <div>
                        <h2
                            class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            New cohort / batch
                        </h2>
                        <p class="text-sm text-slate-500">
                            Group one programme intake without setting case
                            quotas.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <Label for="cohort-programme">Programme</Label
                        ><select
                            id="cohort-programme"
                            v-model="cohortForm.programme_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="" disabled>Select programme</option>
                            <option
                                v-for="programme in programmes"
                                :key="programme.id"
                                :value="programme.id"
                            >
                                {{ programme.name }}
                            </option></select
                        ><InputError
                            :message="cohortForm.errors.programme_id"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <Label for="cohort-name">Cohort name</Label
                        ><Input
                            id="cohort-name"
                            v-model="cohortForm.name"
                            class="mt-2"
                            placeholder="2026 intake"
                        /><InputError :message="cohortForm.errors.name" />
                    </div>
                    <div>
                        <Label for="admission-year">Admission year</Label
                        ><Input
                            id="admission-year"
                            v-model="cohortForm.admission_year"
                            class="mt-2"
                            type="number"
                            min="2000"
                        /><InputError
                            :message="cohortForm.errors.admission_year"
                        />
                    </div>
                    <div>
                        <Label for="academic-label">Academic year</Label
                        ><Input
                            id="academic-label"
                            v-model="cohortForm.academic_year_label"
                            class="mt-2"
                            placeholder="2026–27"
                        /><InputError
                            :message="cohortForm.errors.academic_year_label"
                        />
                    </div>
                </div>
                <Button
                    class="mt-6 w-full bg-[#0b2942] text-white sm:w-auto"
                    :disabled="cohortForm.processing || programmes.length === 0"
                    ><Plus class="size-4" /> Create cohort</Button
                >
            </form>
        </section>

        <section
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                    >
                        Academic directory
                    </p>
                    <h2
                        class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                    >
                        {{ programmes.length }} programmes
                    </h2>
                </div>
            </div>
            <div
                v-if="programmes.length"
                class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="programme in programmes"
                    :key="programme.id"
                    class="rounded-2xl border border-slate-200 p-5 dark:border-slate-700"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-xs font-bold tracking-wider text-amber-700 uppercase"
                            >
                                {{ programme.code }}
                            </p>
                            <h3
                                class="mt-1 font-semibold text-[#0b2942] dark:text-white"
                            >
                                {{ programme.name }}
                            </h3>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                            >{{ programme.duration_years }} years</span
                        >
                    </div>
                    <div
                        class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800"
                    >
                        <p
                            class="text-xs font-semibold text-slate-500 uppercase"
                        >
                            Cohorts
                        </p>
                        <ul
                            v-if="programme.cohorts.length"
                            class="mt-2 space-y-2"
                        >
                            <li
                                v-for="cohort in programme.cohorts"
                                :key="cohort.id"
                                class="flex justify-between gap-3 text-sm"
                            >
                                <span>{{ cohort.name }}</span
                                ><span class="text-slate-500">{{
                                    cohort.academic_year_label
                                }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-sm text-slate-500">
                            No cohorts yet.
                        </p>
                    </div>
                </article>
            </div>
            <p
                v-else
                class="mt-6 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-500 dark:bg-slate-800"
            >
                Create the first programme to begin the academic structure.
            </p>
        </section>
    </main>
</template>
