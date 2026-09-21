<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { GraduationCap, ShieldCheck, UserPlus, Users } from '@lucide/vue';
import AdminWorkspaceHeader from '@/components/admin/AdminWorkspaceHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Person = {
    id: number;
    name: string;
    email: string;
    role: 'student' | 'faculty';
    status: 'active' | 'inactive';
    created_at: string;
};
defineProps<{ people: Person[] }>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administration', href: '/admin' },
            { title: 'People', href: '/admin/people' },
        ],
    },
});

const form = useForm({
    name: '',
    email: '',
    role: 'student',
    password: '',
    password_confirmation: '',
});
const createPerson = () =>
    form.post('/admin/people', {
        preserveScroll: true,
        onSuccess: () =>
            form.reset('name', 'email', 'password', 'password_confirmation'),
    });
const toggleStatus = (person: Person) =>
    router.patch(
        `/admin/people/${person.id}/status`,
        { status: person.status === 'active' ? 'inactive' : 'active' },
        { preserveScroll: true },
    );
</script>

<template>
    <Head title="People" />
    <main
        class="mx-auto w-full max-w-7xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <AdminWorkspaceHeader
            eyebrow="Institution directory"
            title="Give the right people the right doorway."
            description="Create student and faculty accounts inside this institution. Role and status are enforced on the server, not inferred from the interface."
        />

        <section class="grid gap-5 lg:grid-cols-[0.8fr_1.2fr]">
            <form
                class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
                @submit.prevent="createPerson"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-10 place-items-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        ><UserPlus class="size-5"
                    /></span>
                    <div>
                        <h2
                            class="font-display text-xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            Create account
                        </h2>
                        <p class="text-sm text-slate-500">
                            For a student or faculty/preceptor.
                        </p>
                    </div>
                </div>
                <div class="mt-6 space-y-4">
                    <div>
                        <Label for="person-name">Full name</Label
                        ><Input
                            id="person-name"
                            v-model="form.name"
                            class="mt-2"
                            autocomplete="off"
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <Label for="person-email">Email</Label
                        ><Input
                            id="person-email"
                            v-model="form.email"
                            class="mt-2"
                            type="email"
                            autocomplete="off"
                        /><InputError :message="form.errors.email" />
                    </div>
                    <div>
                        <Label for="person-role">Role</Label
                        ><select
                            id="person-role"
                            v-model="form.role"
                            class="border-input bg-background mt-2 h-10 w-full rounded-md border px-3 text-sm"
                        >
                            <option value="student">Student</option>
                            <option value="faculty">
                                Faculty / preceptor
                            </option></select
                        ><InputError :message="form.errors.role" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="person-password"
                                >Temporary password</Label
                            ><Input
                                id="person-password"
                                v-model="form.password"
                                class="mt-2"
                                type="password"
                                autocomplete="new-password"
                            /><InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <Label for="person-confirmation"
                                >Confirm password</Label
                            ><Input
                                id="person-confirmation"
                                v-model="form.password_confirmation"
                                class="mt-2"
                                type="password"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">
                    Passwords are never written to the audit trail. Production
                    password rules apply automatically.
                </p>
                <Button
                    class="mt-5 w-full bg-[#0b2942] text-white"
                    :disabled="form.processing"
                    ><UserPlus class="size-4" /> Create account</Button
                >
            </form>

            <section
                class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
                        >
                            Account directory
                        </p>
                        <h2
                            class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
                        >
                            {{ people.length }} students and faculty
                        </h2>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <ShieldCheck class="size-4 text-emerald-600" />
                        Institution scoped
                    </div>
                </div>
                <div
                    v-if="people.length"
                    class="mt-5 divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <article
                        v-for="person in people"
                        :key="person.id"
                        class="flex flex-col gap-4 py-4 first:pt-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full"
                                :class="
                                    person.role === 'faculty'
                                        ? 'bg-sky-100 text-sky-700'
                                        : 'bg-emerald-100 text-emerald-700'
                                "
                                ><Users
                                    v-if="person.role === 'faculty'"
                                    class="size-5" /><GraduationCap
                                    v-else
                                    class="size-5"
                            /></span>
                            <div class="min-w-0">
                                <p
                                    class="truncate font-semibold text-[#0b2942] dark:text-white"
                                >
                                    {{ person.name }}
                                </p>
                                <p class="truncate text-sm text-slate-500">
                                    {{ person.email }}
                                </p>
                                <p
                                    class="mt-1 text-xs font-semibold tracking-wide uppercase"
                                >
                                    {{
                                        person.role === 'faculty'
                                            ? 'Faculty / preceptor'
                                            : 'Student'
                                    }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 sm:justify-end"
                        >
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="
                                    person.status === 'active'
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600'
                                "
                                >{{ person.status }}</span
                            ><Button
                                variant="outline"
                                size="sm"
                                @click="toggleStatus(person)"
                                >{{
                                    person.status === 'active'
                                        ? 'Deactivate'
                                        : 'Reactivate'
                                }}</Button
                            >
                        </div>
                    </article>
                </div>
                <p
                    v-else
                    class="mt-6 rounded-2xl bg-slate-50 p-6 text-center text-sm text-slate-500 dark:bg-slate-800"
                >
                    Create a student and faculty account to prepare rotation
                    assignments.
                </p>
            </section>
        </section>
    </main>
</template>
