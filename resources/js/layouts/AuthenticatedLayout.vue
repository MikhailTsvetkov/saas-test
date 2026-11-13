<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const logout = useForm({});
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
        <a href="#main-content" class="sr-only rounded-lg bg-white p-3 text-emerald-800 focus:not-sr-only focus:absolute focus:z-10">Перейти к содержимому</a>

        <header class="border-b border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6">
                <div class="min-w-0">
                    <Link :href="page.props.urls.communities" class="rounded text-sm font-semibold text-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-4 dark:text-emerald-400">{{ page.props.appName }}</Link>
                    <p class="mt-1 wrap-anywhere text-lg font-semibold">{{ page.props.community?.name ?? 'Мои сообщества' }}</p>
                </div>
                <div class="flex min-w-0 flex-wrap items-center gap-3 text-sm">
                    <span class="wrap-anywhere text-stone-600 dark:text-stone-400">{{ page.props.auth.user?.name }}</span>
                    <form @submit.prevent="logout.post(page.props.urls.logout)">
                        <button type="submit" :disabled="logout.processing" class="min-h-11 rounded-lg border border-stone-300 px-4 py-2 font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:opacity-60 dark:border-stone-700 dark:hover:bg-stone-800">
                            {{ logout.processing ? 'Выходим...' : 'Выйти' }}
                        </button>
                    </form>
                </div>
            </div>
            <nav aria-label="Основная навигация" class="mx-auto flex max-w-6xl flex-wrap gap-2 px-4 pb-4 sm:px-6">
                <Link :href="page.props.urls.communities" :aria-current="page.component === 'Communities/Index' ? 'page' : undefined" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 aria-[current=page]:bg-emerald-50 aria-[current=page]:text-emerald-800 dark:hover:bg-stone-800 dark:aria-[current=page]:bg-emerald-950 dark:aria-[current=page]:text-emerald-300">Все сообщества</Link>
                <Link v-if="page.props.urls.dashboard" :href="page.props.urls.dashboard" :aria-current="page.component === 'Communities/Dashboard' ? 'page' : undefined" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 aria-[current=page]:bg-emerald-50 aria-[current=page]:text-emerald-800 dark:hover:bg-stone-800 dark:aria-[current=page]:bg-emerald-950 dark:aria-[current=page]:text-emerald-300">Обзор</Link>
                <Link v-if="page.props.urls.members" :href="page.props.urls.members" :aria-current="page.component === 'Communities/Members' ? 'page' : undefined" class="rounded-lg px-3 py-3 text-sm font-medium hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-emerald-600 aria-[current=page]:bg-emerald-50 aria-[current=page]:text-emerald-800 dark:hover:bg-stone-800 dark:aria-[current=page]:bg-emerald-950 dark:aria-[current=page]:text-emerald-300">Участники</Link>
            </nav>
        </header>

        <main id="main-content" tabindex="-1" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <slot />
        </main>
    </div>
</template>
