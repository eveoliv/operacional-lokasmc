<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, Lock, Unlock } from '@lucide/vue';
import { ref } from 'vue';
import AttendanceSessionController from '@/actions/App/Http/Controllers/AttendanceSessionController';
import AttendanceSessionLockController from '@/actions/App/Http/Controllers/AttendanceSessionLockController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/attendance-sessions';
import type { AttendanceSession, Paginated } from '@/types';
const props = defineProps<{
    attendanceSessions: Paginated<AttendanceSession>;
    filters: { archived?: boolean };
}>();
const archived = ref(Boolean(props.filters.archived));
const form = useForm({ event_id: '', name: '', starts_at: '', ends_at: '' });
const filter = () =>
    router.get(
        index.url(),
        { archived: archived.value ? 1 : undefined },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(AttendanceSessionController.store.url(Number(form.event_id)), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const toggleLock = (session: AttendanceSession) =>
    router.patch(
        (session.locked_at
            ? AttendanceSessionLockController.unlock
            : AttendanceSessionLockController.lock
        ).url(session.id),
        {},
        { preserveScroll: true },
    );
const date = (v: string | null) =>
    v
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(v))
        : '—';
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sessões de frequência', href: index() }],
    },
});
</script>
<template>
    <Head title="Sessões de frequência" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Sessões de frequência</h1>
            <p class="text-sm text-muted-foreground">
                Janelas de registro de presença vinculadas aos eventos.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex gap-2 text-base"
                    ><CalendarClock class="size-4" /> Nova sessão</CardTitle
                ></CardHeader
            ><CardContent
                ><form
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2">
                        <Label for="session-event">ID do evento</Label
                        ><Input
                            id="session-event"
                            v-model="form.event_id"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="session-name">Nome</Label
                        ><Input
                            id="session-name"
                            v-model="form.name"
                            required
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="session-start">Início</Label
                        ><Input
                            id="session-start"
                            v-model="form.starts_at"
                            type="datetime-local"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="session-end">Fim</Label
                        ><Input
                            id="session-end"
                            v-model="form.ends_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><CalendarClock class="size-4" /> Criar
                            sessão</Button
                        >
                    </div>
                </form></CardContent
            ></Card
        ><Card class="overflow-hidden"
            ><CardHeader
                ><form
                    class="flex items-center justify-between gap-2"
                    @submit.prevent="filter"
                >
                    <label class="flex items-center gap-2 text-sm"
                        ><input v-model="archived" type="checkbox" /> Exibir
                        arquivadas</label
                    ><Button variant="secondary">Aplicar filtro</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Sessão</th>
                            <th class="px-4 py-3">Evento</th>
                            <th class="px-4 py-3">Período</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="session in attendanceSessions.data"
                            :key="session.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ session.name }}
                            </td>
                            <td class="px-4 py-3">{{ session.event.title }}</td>
                            <td class="px-4 py-3">
                                {{ date(session.starts_at)
                                }}<span
                                    class="block text-xs text-muted-foreground"
                                    >até {{ date(session.ends_at) }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        session.locked_at
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    >{{
                                        session.archived_at
                                            ? 'Arquivada'
                                            : session.locked_at
                                              ? 'Bloqueada'
                                              : 'Aberta'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="!session.archived_at"
                                    size="sm"
                                    :variant="
                                        session.locked_at ? 'outline' : 'ghost'
                                    "
                                    @click="toggleLock(session)"
                                    ><Unlock
                                        v-if="session.locked_at"
                                        class="size-4"
                                    /><Lock v-else class="size-4" />{{
                                        session.locked_at
                                            ? 'Desbloquear'
                                            : 'Bloquear'
                                    }}</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!attendanceSessions.data.length">
                            <td
                                colspan="5"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Nenhuma sessão encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="attendanceSessions"
        /></Card>
    </main>
</template>
