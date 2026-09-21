<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Building2, DoorOpen, Network, Plus } from '@lucide/vue';
import AdminWorkspaceHeader from '@/components/admin/AdminWorkspaceHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Department = {
    id: string;
    clinical_site_id: string;
    name: string;
    status: string;
};
type Ward = {
    id: string;
    clinical_site_id: string;
    department_id: string | null;
    name: string;
    code: string;
    status: string;
    department?: Department | null;
};
type Site = {
    id: string;
    name: string;
    code: string;
    status: string;
    departments: Department[];
    wards: Ward[];
};

const props = defineProps<{ sites: Site[] }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin' },
            { title: 'Clinical sites', href: '/admin/clinical-sites' },
        ],
    },
});

const siteForm = useForm({ name: '', code: '' });
const departmentForm = useForm({ clinical_site_id: '', name: '' });
const wardForm = useForm({
    clinical_site_id: '',
    department_id: '',
    name: '',
    code: '',
});
const availableDepartments = computed(
    () =>
        props.sites.find((site) => site.id === wardForm.clinical_site_id)
            ?.departments ?? [],
);

const createSite = () =>
    siteForm.post('/admin/clinical-sites', {
        preserveScroll: true,
        onSuccess: () => siteForm.reset(),
    });
const createDepartment = () =>
    departmentForm.post('/admin/departments', {
        preserveScroll: true,
        onSuccess: () => departmentForm.reset(),
    });
const createWard = () =>
    wardForm
        .transform((data) => ({
            ...data,
            department_id: data.department_id || null,
        }))
        .post('/admin/wards', {
            preserveScroll: true,
            onSuccess: () => wardForm.reset(),
        });
</script>

<template>
    <Head title="Clinical sites" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <AdminWorkspaceHeader
            eyebrow="Practice network"
            title="Map learning to real places."
            description="Register hospitals or clinical sites, then organise their departments and wards without storing any patient information."
        />

        <section class="grid gap-5 xl:grid-cols-3">
            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createSite"
            >
                <div class="flex items-center gap-3">
                    <Building2 class="size-5 text-amber-700" />
                    <h2
                        class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                    >
                        Clinical site
                    </h2>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <Label for="site-name">Hospital / site name</Label
                        ><Input
                            id="site-name"
                            v-model="siteForm.name"
                            class="mt-2"
                            placeholder="City Teaching Hospital"
                        /><InputError :message="siteForm.errors.name" />
                    </div>
                    <div>
                        <Label for="site-code">Site code</Label
                        ><Input
                            id="site-code"
                            v-model="siteForm.code"
                            class="mt-2 uppercase"
                            placeholder="CTH"
                        /><InputError :message="siteForm.errors.code" />
                    </div>
                </div>
                <Button
                    class="mt-5 w-full bg-[#0b2942] text-white"
                    :disabled="siteForm.processing"
                    ><Plus class="size-4" /> Add site</Button
                >
            </form>

            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createDepartment"
            >
                <div class="flex items-center gap-3">
                    <Network class="size-5 text-sky-700" />
                    <h2
                        class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                    >
                        Department
                    </h2>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <Label for="department-site">Clinical site</Label
                        ><select
                            id="department-site"
                            v-model="departmentForm.clinical_site_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
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
                            :message="departmentForm.errors.clinical_site_id"
                        />
                    </div>
                    <div>
                        <Label for="department-name">Department name</Label
                        ><Input
                            id="department-name"
                            v-model="departmentForm.name"
                            class="mt-2"
                            placeholder="General Medicine"
                        /><InputError :message="departmentForm.errors.name" />
                    </div>
                </div>
                <Button
                    class="mt-5 w-full bg-[#0b2942] text-white"
                    :disabled="departmentForm.processing || sites.length === 0"
                    ><Plus class="size-4" /> Add department</Button
                >
            </form>

            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createWard"
            >
                <div class="flex items-center gap-3">
                    <DoorOpen class="size-5 text-emerald-700" />
                    <h2
                        class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                    >
                        Ward
                    </h2>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <div>
                        <Label for="ward-site">Clinical site</Label
                        ><select
                            id="ward-site"
                            v-model="wardForm.clinical_site_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                            @change="wardForm.department_id = ''"
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
                            :message="wardForm.errors.clinical_site_id"
                        />
                    </div>
                    <div>
                        <Label for="ward-department"
                            >Department (optional)</Label
                        ><select
                            id="ward-department"
                            v-model="wardForm.department_id"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="">No department</option>
                            <option
                                v-for="department in availableDepartments"
                                :key="department.id"
                                :value="department.id"
                            >
                                {{ department.name }}
                            </option></select
                        ><InputError :message="wardForm.errors.department_id" />
                    </div>
                    <div>
                        <Label for="ward-name">Ward name</Label
                        ><Input
                            id="ward-name"
                            v-model="wardForm.name"
                            class="mt-2"
                            placeholder="Medical Ward 3"
                        /><InputError :message="wardForm.errors.name" />
                    </div>
                    <div>
                        <Label for="ward-code">Ward code</Label
                        ><Input
                            id="ward-code"
                            v-model="wardForm.code"
                            class="mt-2 uppercase"
                            placeholder="MW3"
                        /><InputError :message="wardForm.errors.code" />
                    </div>
                </div>
                <Button
                    class="mt-5 w-full bg-[#0b2942] text-white"
                    :disabled="wardForm.processing || sites.length === 0"
                    ><Plus class="size-4" /> Add ward</Button
                >
            </form>
        </section>

        <section
            class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
        >
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Clinical network
            </p>
            <h2
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                {{ sites.length }} registered sites
            </h2>
            <div v-if="sites.length" class="mt-5 space-y-4">
                <article
                    v-for="site in sites"
                    :key="site.id"
                    class="rounded-2xl border border-slate-200 p-5 dark:border-slate-700"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-xs font-bold text-amber-700 uppercase"
                            >
                                {{ site.code }}
                            </p>
                            <h3
                                class="font-semibold text-[#0b2942] dark:text-white"
                            >
                                {{ site.name }}
                            </h3>
                        </div>
                        <p class="text-sm text-slate-500">
                            {{ site.departments.length }} departments ·
                            {{ site.wards.length }} wards
                        </p>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800"
                        >
                            <p
                                class="text-xs font-semibold text-slate-500 uppercase"
                            >
                                Departments
                            </p>
                            <p class="mt-2 text-sm">
                                {{
                                    site.departments
                                        .map((item) => item.name)
                                        .join(', ') || 'None yet'
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800"
                        >
                            <p
                                class="text-xs font-semibold text-slate-500 uppercase"
                            >
                                Wards
                            </p>
                            <p class="mt-2 text-sm">
                                {{
                                    site.wards
                                        .map(
                                            (item) =>
                                                `${item.name} (${item.code})`,
                                        )
                                        .join(', ') || 'None yet'
                                }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
            <p
                v-else
                class="mt-6 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-500 dark:bg-slate-800"
            >
                Add the pilot hospital or clinical site first.
            </p>
        </section>
    </main>
</template>
