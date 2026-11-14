<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';
import type { ServiceRequest } from '../../types';
import { formatRequestDate, requestStatuses } from './presentation';

defineOptions({ layout: AuthenticatedLayout });
const props = defineProps<{ serviceRequest: ServiceRequest; messageStoreUrl?: string }>();
const page = usePage();
const form = useForm({ body: '' });
const messages = computed(() => props.serviceRequest.messages ?? []);

function submitMessage(): void {
    if (!props.messageStoreUrl) {
        return;
    }

    form.post(props.messageStoreUrl, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}
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

        <section v-if="messageStoreUrl" aria-labelledby="new-message-title" class="rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
            <h2 id="new-message-title" class="text-xl font-semibold">Добавить комментарий</h2>
            <form @submit.prevent="submitMessage" class="mt-4 flex flex-col gap-3">
                <textarea v-model="form.body" name="body" rows="4" required maxlength="3000" :aria-invalid="Boolean(form.errors.body)" class="min-h-28 rounded-lg border border-stone-300 bg-transparent px-3 py-2 focus:outline-2 focus:outline-emerald-600 dark:border-stone-700" placeholder="Напишите комментарий..." />
                <p v-if="form.errors.body" class="text-sm text-red-700 dark:text-red-400">{{ form.errors.body }}</p>
                <button type="submit" :disabled="form.processing" class="min-h-11 self-start rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60">
                    {{ form.processing ? 'Отправляем...' : 'Добавить комментарий' }}
                </button>
            </form>
        </section>

        <section aria-labelledby="messages-title" class="flex flex-col gap-4">
            <h2 id="messages-title" class="text-xl font-semibold">Комментарии</h2>
            <p v-if="messages.length === 0" class="text-sm text-stone-600 dark:text-stone-400">Пока нет комментариев.</p>
            <ol v-else class="flex flex-col gap-4">
                <li v-for="message in messages" :key="message.id" class="rounded-xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="font-medium">{{ message.author.name }}</p>
                        <span v-if="message.role" class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ message.role === 'manager' ? 'Управляющий' : 'Житель' }}</span>
                        <span v-else class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700 dark:bg-stone-800 dark:text-stone-300">Бывший участник</span>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap wrap-anywhere leading-7">{{ message.body }}</p>
                    <time :datetime="message.created_at" class="mt-3 block text-sm text-stone-500 dark:text-stone-400">{{ formatRequestDate(message.created_at) }} UTC</time>
                </li>
            </ol>
        </section>

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
