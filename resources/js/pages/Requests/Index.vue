<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';
import type { PaginatedRequests, RequestStatus } from '../../types';
import { formatRequestDate, requestStatuses } from './presentation';

defineOptions({ layout: AuthenticatedLayout });
const props = defineProps<{
    requests: PaginatedRequests;
    filters: { status: RequestStatus | null };
    createUrl: string | null;
}>();
const page = usePage();
const filter = useForm({ status: props.filters.status ?? '' });

function applyFilter(): void {
    if (page.props.urls.requests) {
        filter.transform((data) => data.status ? { status: data.status } : {})
            .get(page.props.urls.requests, { preserveState: false });
    }
}
</script>

<template>
    <section aria-labelledby="requests-title" class="flex flex-col gap-6">
        <Head title="Обращения" />
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 id="requests-title" class="text-3xl font-semibold tracking-tight">{{ page.props.community?.role === 'manager' ? 'Обращения жителей' : 'Мои обращения' }}</h1>
            <Link v-if="createUrl" :href="createUrl" class="inline-flex min-h-11 items-center rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">Создать обращение</Link>
        </div>

        <form @submit.prevent="applyFilter" class="flex flex-wrap items-end gap-3" :aria-busy="filter.processing">
            <div class="flex flex-col gap-2">
                <label for="status-filter" class="text-sm font-medium">Статус</label>
                <select id="status-filter" v-model="filter.status" :disabled="filter.processing" :aria-invalid="Boolean(filter.errors.status)" :aria-describedby="filter.errors.status ? 'filter-error' : undefined" class="min-h-11 rounded-lg border border-stone-300 bg-white px-3 py-2 focus:outline-2 focus:outline-emerald-600 dark:border-stone-700 dark:bg-stone-900">
                    <option value="">Все</option>
                    <option value="new">Новые</option>
                    <option value="in_progress">В работе</option>
                    <option value="resolved">Решённые</option>
                </select>
            </div>
            <button :disabled="filter.processing" type="submit" class="min-h-11 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 disabled:opacity-60 dark:border-stone-700 dark:hover:bg-stone-800">{{ filter.processing ? 'Загружаем...' : 'Применить' }}</button>
            <p v-if="filter.errors.status" id="filter-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ filter.errors.status }}</p>
        </form>

        <p class="text-sm text-stone-500 dark:text-stone-400">Даты и время указаны в UTC.</p>
        <div v-if="requests.data.length === 0" class="rounded-2xl border border-dashed border-stone-300 p-8 text-center dark:border-stone-700">
            <h2 class="text-lg font-semibold">{{ filters.status ? 'Обращений с этим статусом нет' : 'Обращений пока нет' }}</h2>
            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">{{ filters.status ? 'Выберите другой статус или покажите все обращения.' : createUrl ? 'Создайте обращение, чтобы сообщить о проблеме в сообществе.' : 'Здесь появятся обращения жителей вашего сообщества.' }}</p>
        </div>
        <ul v-else class="grid gap-4">
            <li v-for="item in requests.data" :key="item.id" class="min-w-0 rounded-2xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-stone-500 dark:text-stone-400">Обращение №{{ item.id }}</p>
                        <h2 class="mt-2 wrap-anywhere text-xl font-semibold"><Link :href="item.showUrl" class="rounded hover:text-emerald-700 focus-visible:outline-2 focus-visible:outline-emerald-600 dark:hover:text-emerald-400">{{ item.title }}</Link></h2>
                    </div>
                    <span :class="requestStatuses[item.status].classes" class="rounded-full px-3 py-1 text-sm font-medium">{{ requestStatuses[item.status].label }}</span>
                </div>
                <p v-if="item.author" class="mt-3 wrap-anywhere text-sm">Автор: {{ item.author.name }}</p>
                <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm text-stone-600 dark:text-stone-400">
                    <div><dt class="inline">Создано: </dt><dd class="inline"><time :datetime="item.created_at">{{ formatRequestDate(item.created_at) }}</time></dd></div>
                    <div><dt class="inline">Обновлено: </dt><dd class="inline"><time :datetime="item.updated_at">{{ formatRequestDate(item.updated_at) }}</time></dd></div>
                </dl>
            </li>
        </ul>

        <nav v-if="requests.last_page > 1" aria-label="Страницы обращений" class="flex flex-wrap items-center justify-between gap-3">
            <Link v-if="requests.prev_page_url" :href="requests.prev_page_url" class="rounded-lg border border-stone-300 px-4 py-3 text-sm focus-visible:outline-2 focus-visible:outline-emerald-600 dark:border-stone-700">Назад</Link>
            <span class="text-sm text-stone-600 dark:text-stone-400">Страница {{ requests.current_page }} из {{ requests.last_page }} · Всего {{ requests.total }}</span>
            <Link v-if="requests.next_page_url" :href="requests.next_page_url" class="rounded-lg border border-stone-300 px-4 py-3 text-sm focus-visible:outline-2 focus-visible:outline-emerald-600 dark:border-stone-700">Далее</Link>
        </nav>
    </section>
</template>
