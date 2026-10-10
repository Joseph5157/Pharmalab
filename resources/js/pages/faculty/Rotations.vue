<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MapPin, Plus, Target, Trash2, Users } from '@lucide/vue';
import { reactive, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CaseTarget = {
    id: string;
    category: string | null;
    target_count: number;
};
type Rotation = {
    id: string;
    name: string;
    starts_on: string;
    ends_on: string;
    clinical_site_name: string | null;
    ward_name: string | null;
    active_students_count: number;
    targets: CaseTarget[];
};

const props = defineProps<{
    rotations: Rotation[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Faculty', href: '/faculty' },
            { title: 'Rotation targets', href: '/faculty/rotations' },
        ],
    },
});

type Draft = {
    category: string;
    target_count: number | undefined;
    errors: Record<string, string>;
    processing: boolean;
};

const addDrafts = reactive<Record<string, Draft>>(
    Object.fromEntries(
        props.rotations.map((rotation) => [
            rotation.id,
            {
                category: '',
                target_count: undefined,
                errors: {},
                processing: false,
            },
        ]),
    ),
);

const editDrafts = reactive<Record<string, Draft>>({});

// Keep one edit draft per target, refreshed from the server after every
// save, add or delete (Inertia replaces `rotations` on each response).
watch(
    () => props.rotations,
    (rotations) => {
        for (const target of rotations.flatMap((r) => r.targets)) {
            editDrafts[target.id] = {
                category: target.category ?? '',
                target_count: target.target_count,
                errors: editDrafts[target.id]?.errors ?? {},
                processing: false,
            };
        }
    },
    { immediate: true },
);

const editDraft = (target: CaseTarget): Draft => editDrafts[target.id];

const payload = (draft: Draft) => ({
    category: draft.category.trim() === '' ? null : draft.category.trim(),
    target_count: draft.target_count,
});

const addTarget = (rotation: Rotation) => {
    const draft = addDrafts[rotation.id];
    draft.processing = true;
    router.post(`/faculty/rotations/${rotation.id}/targets`, payload(draft), {
        preserveScroll: true,
        onSuccess: () => {
            draft.category = '';
            draft.target_count = undefined;
            draft.errors = {};
        },
        onError: (errors) => {
            draft.errors = errors;
        },
        onFinish: () => {
            draft.processing = false;
        },
    });
};

const saveTarget = (rotation: Rotation, target: CaseTarget) => {
    const draft = editDraft(target);
    draft.processing = true;
    // Every Inertia response (including a validation redirect) refreshes
    // `rotations`, and the watcher above replaces each draft object, so look
    // the draft up again in the callbacks instead of holding the old one.
    const current = () => editDrafts[target.id];
    router.patch(
        `/faculty/rotations/${rotation.id}/targets/${target.id}`,
        payload(draft),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (current()) {
                    current().errors = {};
                }
            },
            onError: (errors) => {
                if (current()) {
                    current().category = draft.category;
                    current().target_count = draft.target_count;
                    current().errors = errors;
                }
            },
            onFinish: () => {
                if (current()) {
                    current().processing = false;
                }
            },
        },
    );
};

const removeTarget = (rotation: Rotation, target: CaseTarget) => {
    const label = target.category ?? 'overall';
    if (!window.confirm(`Remove the ${label} target?`)) {
        return;
    }
    router.delete(`/faculty/rotations/${rotation.id}/targets/${target.id}`, {
        preserveScroll: true,
    });
};

const hasOverall = (rotation: Rotation) =>
    rotation.targets.some((target) => target.category === null);

const formatDate = (value: string) =>
    new Intl.DateTimeFormat('en', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(value));
</script>

<template>
    <Head title="Rotation targets" />
    <main
        class="mx-auto w-full max-w-5xl space-y-6 px-4 pt-5 pb-28 sm:px-6 md:pt-8 md:pb-10"
    >
        <div>
            <p
                class="text-xs font-bold tracking-[0.16em] text-amber-700 uppercase"
            >
                Rotation targets
            </p>
            <h1
                class="font-display mt-1 text-2xl font-semibold text-[#0b2942] dark:text-white"
            >
                Case targets for your rotations
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Each assigned student is measured individually. Only approved
                cases count. Adding category targets turns the student's case
                category into a choice from your list.
            </p>
        </div>

        <section v-if="rotations.length" class="space-y-5">
            <article
                v-for="rotation in rotations"
                :key="rotation.id"
                :data-test="`rotation-${rotation.id}`"
                class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2
                            class="text-lg font-semibold text-[#0b2942] dark:text-white"
                        >
                            {{ rotation.name }}
                        </h2>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500"
                        >
                            <span class="flex items-center gap-1">
                                <MapPin class="size-3.5" />
                                {{ rotation.clinical_site_name ?? 'No site' }}
                                <template v-if="rotation.ward_name">
                                    · {{ rotation.ward_name }}
                                </template>
                            </span>
                            <span>
                                {{ formatDate(rotation.starts_on) }} –
                                {{ formatDate(rotation.ends_on) }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Users class="size-3.5" />
                                {{ rotation.active_students_count }} student{{
                                    rotation.active_students_count === 1
                                        ? ''
                                        : 's'
                                }}
                            </span>
                        </p>
                    </div>
                </div>

                <ul
                    v-if="rotation.targets.length"
                    class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <li
                        v-for="target in rotation.targets"
                        :key="target.id"
                        class="py-3"
                    >
                        <div class="flex flex-wrap items-end gap-3">
                            <label class="min-w-40 flex-1 text-sm">
                                <span class="text-slate-500">
                                    {{
                                        target.category === null
                                            ? 'Overall (all approved cases)'
                                            : 'Category'
                                    }}
                                </span>
                                <Input
                                    v-if="target.category !== null"
                                    v-model="editDraft(target).category"
                                    maxlength="80"
                                    class="mt-1"
                                />
                            </label>
                            <label class="w-28 text-sm">
                                <span class="text-slate-500">Target</span>
                                <Input
                                    v-model.number="
                                        editDraft(target).target_count
                                    "
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="mt-1"
                                />
                            </label>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="editDraft(target).processing"
                                @click="saveTarget(rotation, target)"
                            >
                                Save
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                :aria-label="`Remove ${target.category ?? 'overall'} target`"
                                @click="removeTarget(rotation, target)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <InputError
                            :message="
                                editDraft(target).errors.category ??
                                editDraft(target).errors.target_count
                            "
                        />
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-4 rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500"
                >
                    No targets yet. Students on this rotation can still record
                    any case category.
                </p>

                <form
                    class="mt-4 flex flex-wrap items-end gap-3 rounded-2xl bg-slate-50 p-4 dark:bg-slate-800"
                    @submit.prevent="addTarget(rotation)"
                >
                    <label class="min-w-40 flex-1 text-sm">
                        <span class="text-slate-500">
                            Category
                            {{
                                hasOverall(rotation)
                                    ? ''
                                    : '(leave blank for the overall target)'
                            }}
                        </span>
                        <Input
                            v-model="addDrafts[rotation.id].category"
                            maxlength="80"
                            placeholder="e.g. Cardiology"
                            class="mt-1"
                        />
                    </label>
                    <label class="w-28 text-sm">
                        <span class="text-slate-500">Target</span>
                        <Input
                            v-model.number="addDrafts[rotation.id].target_count"
                            type="number"
                            min="1"
                            max="999"
                            required
                            class="mt-1"
                        />
                    </label>
                    <Button
                        type="submit"
                        class="bg-[#0b2942] text-white"
                        :disabled="addDrafts[rotation.id].processing"
                    >
                        <Plus class="size-4" /> Add target
                    </Button>
                    <InputError
                        class="w-full"
                        :message="
                            addDrafts[rotation.id].errors.category ??
                            addDrafts[rotation.id].errors.target_count
                        "
                    />
                </form>
            </article>
        </section>

        <p
            v-else
            class="flex items-center gap-2 rounded-3xl border border-dashed border-slate-300 p-8 text-sm text-slate-500"
        >
            <Target class="size-4" />
            You have no active rotation assignments, so there are no targets to
            set.
        </p>
    </main>
</template>
