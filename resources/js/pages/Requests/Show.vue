<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';
import type { ServiceRequest } from '../../types';
import { formatRequestDate, requestStatuses } from './presentation';

defineOptions({ layout: AuthenticatedLayout });
defineProps<{ serviceRequest: ServiceRequest }>();
const page = usePage();
</script>

<template>
    <section aria-labelledby="request-title" class="flex max-w-4xl flex-col gap-6">
        <Head :title="`Обращение №${serviceRequest.id}`" />
        <Link v-if="page.props.urls.requests" :href="page.props.urls.requests" class="self-start rounded text-sm font-medium text-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:text-emerald-400">← К обращениям</Link>
        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-stone-500 dark:text-stone-400">Обращение №{{ serviceRequest.id }}</p>
            <span :class="requestStatuses[serviceRequest.status].classes" class="rounded-full px-3 py-1 text-sm font-medium">{{ requestStatuses[serviceRequest.status].label }}</span>
        </div>
        <h1 id="request-title" class="wrap-anywhere text-3xl font-semibold tracking-tight">{{ serviceRequest.title }}</h1>
        <div class="rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
            <p class="whitespace-pre-wrap wrap-anywhere leading-7">{{ serviceRequest.description }}</p>
            <dl class="mt-6 grid gap-3 border-t border-stone-200 pt-5 text-sm dark:border-stone-800 sm:grid-cols-2">
                <div><dt class="text-stone-500 dark:text-stone-400">Автор</dt><dd class="mt-1 wrap-anywhere">{{ serviceRequest.author.name }}</dd></div>
                <div><dt class="text-stone-500 dark:text-stone-400">Создано (UTC)</dt><dd class="mt-1"><time :datetime="serviceRequest.created_at">{{ formatRequestDate(serviceRequest.created_at) }}</time></dd></div>
                <div v-if="serviceRequest.resolved_at"><dt class="text-stone-500 dark:text-stone-400">Решено (UTC)</dt><dd class="mt-1"><time :datetime="serviceRequest.resolved_at">{{ formatRequestDate(serviceRequest.resolved_at) }}</time></dd></div>
            </dl>
        </div>
        <section aria-labelledby="history-title" class="flex flex-col gap-4">
            <h2 id="history-title" class="text-xl font-semibold">История обращения</h2>
            <p v-if="serviceRequest.statusHistories.length === 0" class="text-sm text-stone-600 dark:text-stone-400">История пока пуста.</p>
            <ol v-else class="flex flex-col gap-4">
                <li v-for="event in serviceRequest.statusHistories" :key="event.id" class="rounded-xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                    <p class="font-medium">{{ event.from_status === null ? 'Обращение создано' : `${requestStatuses[event.from_status].label} → ${requestStatuses[event.to_status].label}` }}</p>
                    <p class="mt-2 wrap-anywhere text-sm text-stone-600 dark:text-stone-400">{{ event.author?.name ?? 'Автор не указан' }}</p>
                    <time :datetime="event.created_at" class="mt-1 block text-sm text-stone-500 dark:text-stone-400">{{ formatRequestDate(event.created_at) }} UTC</time>
                </li>
            </ol>
        </section>
    </section>
</template>
