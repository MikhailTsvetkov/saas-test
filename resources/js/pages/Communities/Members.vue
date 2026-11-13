<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../layouts/AuthenticatedLayout.vue';
import type { CommunityMember } from '../../types';

defineOptions({ layout: AuthenticatedLayout });
defineProps<{ members: CommunityMember[] }>();
</script>

<template>
    <section aria-labelledby="members-title">
        <Head title="Участники" />
        <h1 id="members-title" class="text-3xl font-semibold tracking-tight">Участники</h1>
        <p class="mt-3 text-stone-600 dark:text-stone-400">Жители и управляющие текущего сообщества.</p>

        <div class="mt-8 overflow-x-auto rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Имена, email и роли участников сообщества</caption>
                <thead class="border-b border-stone-200 bg-stone-100 text-stone-600 dark:border-stone-800 dark:bg-stone-800 dark:text-stone-300">
                    <tr>
                        <th scope="col" class="px-5 py-4 font-medium">Имя</th>
                        <th scope="col" class="px-5 py-4 font-medium">Email</th>
                        <th scope="col" class="px-5 py-4 font-medium">Роль</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    <tr v-for="member in members" :key="member.id">
                        <th scope="row" class="min-w-36 px-5 py-4 font-medium wrap-anywhere">{{ member.name }}</th>
                        <td class="px-5 py-4 text-stone-600 dark:text-stone-400">{{ member.email }}</td>
                        <td class="px-5 py-4">{{ member.role === 'manager' ? 'Управляющий' : 'Житель' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
