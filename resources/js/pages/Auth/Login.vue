<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({ email: '', password: '' });

function submit(): void {
    form.post(page.props.urls.loginStore, {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-stone-50 px-4 py-12 text-stone-900 dark:bg-stone-950 dark:text-stone-100">
        <Head title="Вход" />

        <section class="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8 dark:border-stone-800 dark:bg-stone-900" aria-labelledby="login-title">
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">{{ page.props.appName }}</p>
            <h1 id="login-title" class="mt-3 text-3xl font-semibold tracking-tight">Добро пожаловать</h1>
            <p class="mt-3 text-sm leading-6 text-stone-600 dark:text-stone-400">Войдите в свой аккаунт жителя или управляющего.</p>

            <form class="mt-8 flex flex-col gap-5" @submit.prevent="submit">
                <div class="flex flex-col gap-2">
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        name="email"
                        type="email"
                        autocomplete="username"
                        inputmode="email"
                        required
                        maxlength="255"
                        autofocus
                        :aria-invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                        class="min-h-11 rounded-lg border border-stone-300 bg-transparent px-3 py-2 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-stone-700"
                    />
                    <p v-if="form.errors.email" id="email-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ form.errors.email }}</p>
                </div>

                <div class="flex flex-col gap-2">
                    <label for="password" class="text-sm font-medium">Пароль</label>
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :aria-invalid="Boolean(form.errors.password)"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                        class="min-h-11 rounded-lg border border-stone-300 bg-transparent px-3 py-2 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-stone-700"
                    />
                    <p v-if="form.errors.password" id="password-error" role="alert" class="text-sm text-red-700 dark:text-red-400">{{ form.errors.password }}</p>
                </div>

                <button type="submit" :disabled="form.processing" class="mt-1 min-h-11 rounded-lg bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 disabled:cursor-wait disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    {{ form.processing ? 'Входим...' : 'Войти' }}
                </button>
            </form>

            <p class="mt-6 text-sm leading-6 text-stone-500 dark:text-stone-400">Если у вас ещё нет данных для входа, обратитесь к управляющему вашего сообщества.</p>
        </section>
    </main>
</template>
