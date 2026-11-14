<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';

defineOptions({ layout: AuthenticatedLayout });
defineProps<{ description: string | null }>();
const page = usePage();
</script>

<template>
    <section aria-labelledby="dashboard-title">
        <Head :title="page.props.community?.name ?? 'Обзор'" />
        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Обзор сообщества</p>
        <h1 id="dashboard-title" class="mt-3 wrap-anywhere text-3xl font-semibold tracking-tight">{{ page.props.community?.name }}</h1>
        <p class="mt-4 max-w-3xl whitespace-pre-line wrap-anywhere leading-7 text-stone-600 dark:text-stone-400">{{ description || 'Описание сообщества пока не добавлено.' }}</p>

        <div class="mt-8 grid gap-5 md:grid-cols-2">
            <section class="min-w-0 rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900" aria-labelledby="welcome-title">
                <h2 id="welcome-title" class="wrap-anywhere text-xl font-semibold">Добро пожаловать, {{ page.props.auth.user?.name }}!</h2>
                <p class="mt-3 text-sm leading-6 text-stone-600 dark:text-stone-400">Ваша роль в этом сообществе: <span class="font-medium text-stone-900 dark:text-stone-100">{{ page.props.community?.role === 'manager' ? 'Управляющий' : 'Житель' }}</span>.</p>
                <p class="mt-3 text-sm leading-6 text-stone-600 dark:text-stone-400">{{ page.props.community?.role === 'manager' ? 'В разделе «Участники» доступен список жителей и управляющих.' : 'Здесь будет собрана информация о жизни вашего сообщества.' }}</p>
            </section>
            <section class="rounded-2xl border border-dashed border-stone-300 p-6 dark:border-stone-700" aria-labelledby="requests-title">
                <h2 id="requests-title" class="mt-2 text-xl font-semibold">Обращения</h2>
                <p class="mt-3 text-sm leading-6 text-stone-600 dark:text-stone-400">{{ page.props.community?.role === 'manager' ? 'Просматривайте обращения жителей вашего сообщества.' : 'Сообщайте о проблемах и следите за состоянием своих обращений.' }}</p>
                <Link v-if="page.props.urls.requests" :href="page.props.urls.requests" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Открыть обращения</Link>
            </section>
        </div>
    </section>
</template>
