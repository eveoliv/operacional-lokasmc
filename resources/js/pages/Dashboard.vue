<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarRange,
    ClipboardCheck,
    KeyRound,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as accessGrants } from '@/routes/access-grants';
import { index as attendanceSessions } from '@/routes/attendance-sessions';
import { index as events } from '@/routes/events';
import { index as people } from '@/routes/people';
import { index as registrations } from '@/routes/registrations';
const page = usePage();
const user = computed(() => page.props.auth.user);
const links = [
    {
        title: 'Pessoas',
        description: 'Cadastros e vínculos organizacionais',
        href: people(),
        icon: UsersRound,
    },
    {
        title: 'Eventos',
        description: 'Programação e públicos dos eventos',
        href: events(),
        icon: CalendarDays,
    },
    {
        title: 'Inscrições',
        description: 'Participantes inscritos',
        href: registrations(),
        icon: ClipboardCheck,
    },
    {
        title: 'Frequência',
        description: 'Sessões e registros de presença',
        href: attendanceSessions(),
        icon: CalendarRange,
    },
    {
        title: 'Acessos',
        description: 'Papéis e escopos de acesso',
        href: accessGrants(),
        icon: KeyRound,
    },
];
defineOptions({
    layout: { breadcrumbs: [{ title: 'Painel', href: dashboard() }] },
});
</script>
<template>
    <Head title="Painel" />
    <main class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">
                Painel operacional
            </h1>
            <p class="mt-1 text-muted-foreground">
                Olá, {{ user.name }}. Acesse os principais fluxos do sistema.
            </p>
        </header>
        <section
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            aria-label="Atalhos operacionais"
        >
            <Link
                v-for="item in links"
                :key="item.title"
                :href="item.href"
                class="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                ><Card class="h-full transition-colors hover:bg-muted/50"
                    ><CardHeader
                        ><component
                            :is="item.icon"
                            class="size-5 text-muted-foreground"
                        /><CardTitle class="text-base">{{
                            item.title
                        }}</CardTitle
                        ><CardDescription>{{
                            item.description
                        }}</CardDescription></CardHeader
                    ></Card
                ></Link
            >
        </section>
    </main>
</template>
