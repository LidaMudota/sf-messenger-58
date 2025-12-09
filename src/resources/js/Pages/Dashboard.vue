<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const shortcuts = [
    { label: 'Открыть мессенджер', path: '/messenger' },
    { label: 'Логин', path: '/login' },
    { label: 'Регистрация', path: '/register' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
                >
                    <div class="space-y-6 p-6 text-gray-900">
                        <div class="text-lg font-semibold">You're logged in!</div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <Link
                                v-for="shortcut in shortcuts"
                                :key="shortcut.path"
                                :href="shortcut.path"
                                class="flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                {{ shortcut.label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
