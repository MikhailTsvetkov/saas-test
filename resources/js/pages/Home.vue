<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <main class="flex min-h-screen flex-col items-center justify-center gap-6 bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <Head title="Добро пожаловать" />
        <h1 class="text-3xl font-medium">Добро пожаловать</h1>
        <template v-if="page.props.auth.user">
            <p>{{ page.props.auth.user.name }}, вы вошли в свой аккаунт.</p>
            <Link :href="page.props.urls.logout" method="post" as="button" class="rounded-lg border border-current px-5 py-3 focus-visible:outline-2 focus-visible:outline-offset-2">Выйти</Link>
        </template>
        <Link v-else :href="page.props.urls.login" class="rounded-lg bg-emerald-700 px-5 py-3 font-medium text-white focus-visible:outline-2 focus-visible:outline-offset-2">Войти</Link>
    </main>
</template>
