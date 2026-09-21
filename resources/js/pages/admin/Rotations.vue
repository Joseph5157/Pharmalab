<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, Link2, MapPin, Plus, Users } from '@lucide/vue';
import AdminWorkspaceHeader from '@/components/admin/AdminWorkspaceHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Cohort = { id: string; name: string; academic_year_label: string };
type Programme = { id: string; name: string; code: string; cohorts: Cohort[] };
type Department = { id: string; name: string };
type Ward = {
    id: string;
    department_id: string | null;
    name: string;
    code: string;
};
type Site = {
    id: string;
    name: string;
    code: string;
    departments: Department[];
    wards: Ward[];
};
type Person = { id: number; name: string; email: string };
type Assignment = {
    id: string;
    status: 'active' | 'inactive';
    student: Person;
    faculty: Person;
};
type Rotation = {
    id: string;
    name: string;
    starts_on: string;
    ends_on: string;
    status: string;
    programme: Programme;
    cohort: Cohort | null;
    clinical_site: Site;
    department: Department | null;
    ward: Ward | null;
    assignments: Assignment[];
};
type Status = { value: string; label: string };

const props = defineProps<{
    rotations: Rotation[];
    programmes: Programme[];
    sites: Site[];
    students: Person[];
    faculty: Person[];
    statuses: Status[];
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin' },
            { title: 'Rotations', href: '/admin/rotations' },
        ],
    },
});

const rotationForm = useForm({
    name: '',
    programme_id: '',
    academic_cohort_id: '',
    clinical_site_id: '',
    department_id: '',
    ward_id: '',
    starts_on: '',
    ends_on: '',
    status: 'draft',
});
const assignmentForm = useForm({
    rotation_id: '',
    student_id: '',
    faculty_id: '',
});
const cohorts = computed(
    () =>
        props.programmes.find((item) => item.id === rotationForm.programme_id)
            ?.cohorts ?? [],
);
const departments = computed(
    () =>
        props.sites.find((item) => item.id === rotationForm.clinical_site_id)
            ?.departments ?? [],
);
const wards = computed(() => {
    const all =
        props.sites.find((item) => item.id === rotationForm.clinical_site_id)
            ?.wards ?? [];
    return rotationForm.department_id
        ? all.filter(
              (ward) => ward.department_id === rotationForm.department_id,
          )
        : all;
});

const nullableRotationData = () => ({
    ...rotationForm.data(),
    academic_cohort_id: rotationForm.academic_cohort_id || null,
    department_id: rotationForm.department_id || null,
    ward_id: rotationForm.ward_id || null,
});
const createRotation = () =>
    rotationForm.transform(nullableRotationData).post('/admin/rotations', {
        preserveScroll: true,
        onSuccess: () => rotationForm.reset(),
    });
const createAssignment = () =>
    assignmentForm.post(
        `/admin/rotations/${assignmentForm.rotation_id}/assignments`,
        {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset('student_id', 'faculty_id'),
        },
    );
const updateRotationStatus = (rotation: Rotation, status: string) =>
    router.patch(
        `/admin/rotations/${rotation.id}/status`,
        { status },
        { preserveScroll: true },
    );
const toggleAssignment = (assignment: Assignment) =>
    router.patch(
        `/admin/rotation-assignments/${assignment.id}/status`,
        { status: assignment.status === 'active' ? 'inactive' : 'active' },
        { preserveScroll: true },
    );

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <Head title="Rotations" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <AdminWorkspaceHeader
            eyebrow="Placement operations"
            title="Connect learners, preceptors, and place."
            description="Create a bounded clinical rotation and pair each student with one primary faculty/preceptor. Advanced scheduling is intentionally outside this gate."
        />

        <section class="grid gap-5 xl:grid-cols-[1.25fr_0.75fr]">
            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createRotation"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        ><CalendarDays class="size-5"
                    /></span>
                    <div>
                        <h2
                            class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            Create rotation
                        </h2>
                        <p class="text-sm text-slate-500">
                            Academic pathway, placement location, and dates.
                        </p>
                    </div>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <Label for="rotation-name">Rotation name</Label
                        ><Input
                            id="rotation-name"
                            v-model="rotationForm.name"
                            class="mt-2"
                            placeholder="General Medicine · Block A"
                        /><InputError :message="rotationForm.errors.name" />
                    </div>
                    <div>
                        <Label for="rotation-programme">Programme</Label
                        ><select
                            id="rotation-programme"
                            v-model="rotationForm.programme_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                            @change="rotationForm.academic_cohort_id = ''"
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
                            :message="rotationForm.errors.programme_id"
                        />
                    </div>
                    <div>
                        <Label for="rotation-cohort">Cohort (optional)</Label
                        ><select
                            id="rotation-cohort"
                            v-model="rotationForm.academic_cohort_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">All / not specified</option>
                            <option
                                v-for="cohort in cohorts"
                                :key="cohort.id"
                                :value="cohort.id"
                            >
                                {{ cohort.name }}
                            </option></select
                        ><InputError
                            :message="rotationForm.errors.academic_cohort_id"
                        />
                    </div>
                    <div>
                        <Label for="rotation-site">Clinical site</Label
                        ><select
                            id="rotation-site"
                            v-model="rotationForm.clinical_site_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                            @change="
                                rotationForm.department_id = '';
                                rotationForm.ward_id = '';
                            "
                        >
                            <option value="" disabled>Select site</option>
                            <option
                                v-for="site in sites"
                                :key="site.id"
                                :value="site.id"
                            >
                                {{ site.name }}
                            </option></select
                        ><InputError
                            :message="rotationForm.errors.clinical_site_id"
                        />
                    </div>
                    <div>
                        <Label for="rotation-department"
                            >Department (optional)</Label
                        ><select
                            id="rotation-department"
                            v-model="rotationForm.department_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                            @change="rotationForm.ward_id = ''"
                        >
                            <option value="">Not specified</option>
                            <option
                                v-for="department in departments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option></select
                        ><InputError
                            :message="rotationForm.errors.department_id"
                        />
                    </div>
                    <div>
                        <Label for="rotation-ward">Ward (optional)</Label
                        ><select
                            id="rotation-ward"
                            v-model="rotationForm.ward_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">Not specified</option>
                            <option
                                v-for="ward in wards"
                                :key="ward.id"
                                :value="ward.id"
                            >
                                {{ ward.name }}
                            </option></select
                        ><InputError :message="rotationForm.errors.ward_id" />
                    </div>
                    <div>
                        <Label for="rotation-status">Initial status</Label
                        ><select
                            id="rotation-status"
                            v-model="rotationForm.status"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option></select
                        ><InputError :message="rotationForm.errors.status" />
                    </div>
                    <div>
                        <Label for="starts-on">Starts on</Label
                        ><Input
                            id="starts-on"
                            v-model="rotationForm.starts_on"
                            class="mt-2"
                            type="date"
                        /><InputError
                            :message="rotationForm.errors.starts_on"
                        />
                    </div>
                    <div>
                        <Label for="ends-on">Ends on</Label
                        ><Input
                            id="ends-on"
                            v-model="rotationForm.ends_on"
                            class="mt-2"
                            type="date"
                        /><InputError :message="rotationForm.errors.ends_on" />
                    </div>
                </div>
                <Button
                    class="mt-6 w-full bg-[#0b2942] text-white sm:w-auto"
                    :disabled="
                        rotationForm.processing ||
                        programmes.length === 0 ||
                        sites.length === 0
                    "
                    ><Plus class="size-4" /> Create rotation</Button
                >
            </form>

            <form
                class="rounded-3xl border border-[#d9bd76]/50 bg-[#fffaf0] p-5 sm:p-7 dark:border-[#d9bd76]/20 dark:bg-[#201b10]"
                @submit.prevent="createAssignment"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-[#0b2942] text-white"
                        ><Link2 class="size-5"
                    /></span>
                    <div>
                        <h2
                            class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            Assign the pair
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            One student and primary preceptor.
                        </p>
                    </div>
                </div>
                <div class="mt-6 space-y-4">
                    <div>
                        <Label for="assignment-rotation">Rotation</Label
                        ><select
                            id="assignment-rotation"
                            v-model="assignmentForm.rotation_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="" disabled>Select rotation</option>
                            <option
                                v-for="rotation in rotations"
                                :key="rotation.id"
                                :value="rotation.id"
                            >
                                {{ rotation.name }}
                            </option></select
                        ><InputError
                            :message="assignmentForm.errors.rotation_id"
                        />
                    </div>
                    <div>
                        <Label for="assignment-student">Student</Label
                        ><select
                            id="assignment-student"
                            v-model="assignmentForm.student_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="" disabled>
                                Select active student
                            </option>
                            <option
                                v-for="student in students"
                                :key="student.id"
                                :value="student.id"
                            >
                                {{ student.name }}
                            </option></select
                        ><InputError
                            :message="assignmentForm.errors.student_id"
                        />
                    </div>
                    <div>
                        <Label for="assignment-faculty"
                            >Faculty / preceptor</Label
                        ><select
                            id="assignment-faculty"
                            v-model="assignmentForm.faculty_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="" disabled>
                                Select active faculty
                            </option>
                            <option
                                v-for="person in faculty"
                                :key="person.id"
                                :value="person.id"
                            >
                                {{ person.name }}
                            </option></select
                        ><InputError
                            :message="assignmentForm.errors.faculty_id"
                        />
                    </div>
                </div>
                <Button
                    class="mt-6 w-full bg-[#0b2942] text-white"
                    :disabled="
                        assignmentForm.processing ||
                        rotations.length === 0 ||
                        students.length === 0 ||
                        faculty.length === 0
                    "
                    ><Users class="size-4" /> Save assignment</Button
                >
                <p class="mt-4 text-xs leading-5 text-slate-500">
                    Saving the same student again updates their primary
                    preceptor instead of duplicating the assignment.
                </p>
            </form>
        </section>

        <section class="space-y-4">
            <div>
                <p
                    class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                >
                    Rotation board
                </p>
                <h2
                    class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                >
                    {{ rotations.length }} rotations
                </h2>
            </div>
            <article
                v-for="rotation in rotations"
                :key="rotation.id"
                class="overflow-hidden rounded-3xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="grid gap-5 p-5 sm:p-7 lg:grid-cols-[1fr_auto]">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"
                                >{{ rotation.programme.code }}</span
                            ><span
                                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                >{{ rotation.status }}</span
                            >
                        </div>
                        <h3
                            class="font-display mt-3 text-2xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            {{ rotation.name }}
                        </h3>
                        <div
                            class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-500"
                        >
                            <span class="flex items-center gap-1.5"
                                ><CalendarDays class="size-4" />
                                {{ formatDate(rotation.starts_on) }} →
                                {{ formatDate(rotation.ends_on) }}</span
                            ><span class="flex items-center gap-1.5"
                                ><MapPin class="size-4" />
                                {{ rotation.clinical_site.name
                                }}<template v-if="rotation.ward">
                                    · {{ rotation.ward.name }}</template
                                ></span
                            >
                        </div>
                    </div>
                    <div>
                        <Label
                            :for="`status-${rotation.id}`"
                            class="text-xs text-slate-500 uppercase"
                            >Status</Label
                        ><select
                            :id="`status-${rotation.id}`"
                            :value="rotation.status"
                            class="border-input bg-background mt-2 h-10 rounded-md border px-3 text-sm"
                            @change="
                                updateRotationStatus(
                                    rotation,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div
                    class="border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-7 dark:border-slate-800 dark:bg-slate-950/30"
                >
                    <p
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                    >
                        Assignments · {{ rotation.assignments.length }}
                    </p>
                    <div
                        v-if="rotation.assignments.length"
                        class="mt-3 grid gap-3 lg:grid-cols-2"
                    >
                        <div
                            v-for="assignment in rotation.assignments"
                            :key="assignment.id"
                            class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ assignment.student.name }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    Preceptor: {{ assignment.faculty.name }}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="toggleAssignment(assignment)"
                                >{{
                                    assignment.status === 'active'
                                        ? 'Active'
                                        : 'Inactive'
                                }}</Button
                            >
                        </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-slate-500">
                        No students assigned yet.
                    </p>
                </div>
            </article>
            <p
                v-if="rotations.length === 0"
                class="rounded-3xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
            >
                Create academic, site, and account records before the first
                rotation.
            </p>
        </section>
    </main>
</template>
