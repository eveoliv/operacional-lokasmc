<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Início" />
    <main
        class="flex min-h-screen items-center justify-center bg-background p-6 text-foreground"
    >
        <section
            class="w-full max-w-xl rounded-xl border bg-card p-8 text-center shadow-sm"
        >
            <h1 class="text-3xl font-semibold tracking-tight">
                Operacional Lokas MC
            </h1>
            <p class="mt-3 text-muted-foreground">
                Acesso restrito ao sistema de processos operacionais.
            </p>
            <Link
                :href="$page.props.auth.user ? dashboard() : login()"
                class="mt-6 inline-flex h-10 items-center justify-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                {{ $page.props.auth.user ? 'Acessar painel' : 'Entrar' }}
            </Link>
        </section>
    </main>
</template>
