<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });
const props = defineProps<{ storeUrl: string }>();
const page = usePage();
const form = useForm({ title: '', description: '' });
</script>

<template>
    <section aria-labelledby="create-title" class="flex max-w-3xl flex-col gap-6">
        <Head title="Создать обращение" />
        <Link v-if="page.props.urls.requests" :href="page.props.urls.requests" class="self-start rounded text-sm font-medium text-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-emerald-400">← К обращениям</Link>
        <h1 id="create-title" class="text-3xl font-semibold tracking-tight">Создать обращение</h1>
        <p class="text-stone-600 dark:text-stone-400">Опишите проблему в сообществе «{{ page.props.community?.name }}».</p>
        <form @submit.prevent="form.post(props.storeUrl)" class="flex flex-col gap-6 rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900" :aria-busy="form.processing">
            <div class="flex flex-col gap-2">
                <label for="request-title" class="text-sm font-medium">Заголовок</label>
                <input id="request-title" v-model="form.title" name="title" type="text" required minlength="5" maxlength="255" :aria-invalid="Boolean(form.errors.title)" aria-describedby="title-hint title-error" class="min-h-11 rounded-lg border border-stone-300 bg-transparent px-3 py-2 focus:outline-2 focus:outline-emerald-600 dark:border-stone-700" />
                <p id="title-hint" class="text-sm text-stone-500 dark:text-stone-400">От 5 до 255 символов.</p>
                <p v-if="form.errors.title" id="title-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ form.errors.title }}</p>
            </div>
            <div class="flex flex-col gap-2">
                <label for="request-description" class="text-sm font-medium">Описание</label>
                <textarea id="request-description" v-model="form.description" name="description" required minlength="10" maxlength="5000" rows="8" :aria-invalid="Boolean(form.errors.description)" aria-describedby="description-hint description-error" class="min-h-40 rounded-lg border border-stone-300 bg-transparent px-3 py-2 focus:outline-2 focus:outline-emerald-600 dark:border-stone-700" />
                <p id="description-hint" class="text-sm text-stone-500 dark:text-stone-400">Укажите место и подробности проблемы. От 10 до 5000 символов.</p>
                <p v-if="form.errors.description" id="description-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ form.errors.description }}</p>
            </div>
            <button type="submit" :disabled="form.processing" class="min-h-11 self-start rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Отправляем...' : 'Отправить обращение' }}</button>
        </form>
    </section>
</template>
