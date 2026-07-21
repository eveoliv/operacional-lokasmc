<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard } from '@/routes';

const page = usePage();
const user = computed(() => page.props.auth.user);

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Painel', href: dashboard() }],
    },
});
</script>

<template>
    <Head title="Painel" />
    <div class="flex h-full flex-1 flex-col p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h1 class="text-2xl font-semibold">Painel operacional</h1>
            <p class="mt-2 text-muted-foreground">Olá, {{ user.name }}.</p>
            <p class="text-sm text-muted-foreground">{{ user.email }}</p>
        </section>
    </div>
</template>
