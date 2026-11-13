<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';
import type { CommunityListItem } from '../../types';

defineOptions({ layout: AuthenticatedLayout });
defineProps<{ communities: CommunityListItem[] }>();
</script>

<template>
    <section aria-labelledby="communities-title">
        <Head title="Мои сообщества" />
        <h1 id="communities-title" class="text-3xl font-semibold tracking-tight">Мои сообщества</h1>
        <p class="mt-3 text-stone-600 dark:text-stone-400">Выберите сообщество, чтобы открыть его обзор.</p>

        <div v-if="communities.length === 0" class="mt-8 rounded-2xl border border-dashed border-stone-300 p-8 dark:border-stone-700">
            <h2 class="text-lg font-semibold">Пока нет сообществ</h2>
            <p class="mt-2 max-w-xl text-sm leading-6 text-stone-600 dark:text-stone-400">Ваш аккаунт ещё не добавлен ни в одно сообщество. Обратитесь к управляющему, чтобы получить доступ.</p>
        </div>

        <ul v-else class="mt-8 grid gap-5 md:grid-cols-2">
            <li v-for="community in communities" :key="community.id" class="flex min-w-0 flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900">
                <span class="self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ community.role === 'manager' ? 'Управляющий' : 'Житель' }}</span>
                <h2 class="mt-4 wrap-anywhere text-xl font-semibold">{{ community.name }}</h2>
                <p class="mt-3 flex-1 whitespace-pre-line wrap-anywhere text-sm leading-6 text-stone-600 dark:text-stone-400">{{ community.description || 'Описание сообщества пока не добавлено.' }}</p>
                <Link :href="community.dashboardUrl" :aria-label="`Открыть сообщество ${community.name}`" class="mt-6 inline-flex min-h-11 items-center self-start rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-600 dark:hover:bg-emerald-500">Открыть сообщество</Link>
            </li>
        </ul>
    </section>
</template>
