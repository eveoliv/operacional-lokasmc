<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, Search } from '@lucide/vue';
import { ref } from 'vue';
import EventController from '@/actions/App/Http/Controllers/EventController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/events';
import type { EventStatus, OperationalEvent, Paginated } from '@/types';
const props = defineProps<{
    events: Paginated<OperationalEvent>;
    filters: { search?: string; status?: string; archived?: boolean };
    statuses: EventStatus[];
    manageableUnitIds: number[];
}>();
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const archived = ref(Boolean(props.filters.archived));
const form = useForm({
    organizational_unit_id: '',
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    location: '',
    capacity: '',
    audiences: [],
});
const filter = () =>
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            archived: archived.value ? 1 : undefined,
        },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(EventController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const transition = (event: OperationalEvent, value: EventStatus) =>
    router.patch(
        EventController.transition.url(event.id),
        { status: value },
        { preserveScroll: true },
    );
const labels: Record<EventStatus, string> = {
    draft: 'Rascunho',
    published: 'Publicado',
    in_progress: 'Em andamento',
    completed: 'Concluído',
    cancelled: 'Cancelado',
    archived: 'Arquivado',
};
const date = (v: string | null) =>
    v
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(v))
        : '—';
defineOptions({
    layout: { breadcrumbs: [{ title: 'Eventos', href: index() }] },
});
</script>
<template>
    <Head title="Eventos" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Eventos</h1>
            <p class="text-sm text-muted-foreground">
                Programação, público e capacidade dos eventos.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex gap-2 text-base"
                    ><CalendarPlus class="size-4" /> Novo evento</CardTitle
                ></CardHeader
            ><CardContent
                ><form
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2 xl:col-span-2">
                        <Label for="event-title">Título</Label
                        ><Input
                            id="event-title"
                            v-model="form.title"
                            required
                        /><InputError :message="form.errors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-unit">ID da unidade</Label
                        ><Input
                            id="event-unit"
                            v-model="form.organizational_unit_id"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-location">Local</Label
                        ><Input id="event-location" v-model="form.location" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-start">Início</Label
                        ><Input
                            id="event-start"
                            v-model="form.starts_at"
                            type="datetime-local"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-end">Fim</Label
                        ><Input
                            id="event-end"
                            v-model="form.ends_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-capacity">Capacidade</Label
                        ><Input
                            id="event-capacity"
                            v-model="form.capacity"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><CalendarPlus class="size-4" /> Criar
                            evento</Button
                        >
                    </div>
                </form></CardContent
            ></Card
        ><Card class="overflow-hidden"
            ><CardHeader
                ><form
                    class="grid gap-3 md:grid-cols-[1fr_12rem_auto_auto] md:items-end"
                    @submit.prevent="filter"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            aria-label="Buscar eventos"
                            placeholder="Título ou descrição"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Situação"
                    >
                        <option value="">Todas as situações</option>
                        <option
                            v-for="item in statuses"
                            :key="item"
                            :value="item"
                        >
                            {{ labels[item] }}
                        </option></select
                    ><label class="flex h-9 items-center gap-2 text-sm"
                        ><input v-model="archived" type="checkbox" />
                        Arquivados</label
                    ><Button variant="secondary">Filtrar</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Evento</th>
                            <th class="px-4 py-3">Data e local</th>
                            <th class="px-4 py-3">Público</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Gestão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in events.data"
                            :key="event.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ event.title
                                }}<span
                                    class="block text-xs font-normal text-muted-foreground"
                                    >{{ event.organizational_unit.name }} ·
                                    {{
                                        event.capacity
                                            ? `${event.capacity} vagas`
                                            : 'Sem limite'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ date(event.starts_at)
                                }}<span
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        event.location || 'Local não informado'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="event.audiences.length">{{
                                    event.audiences
                                        .map((a) => a.organizational_unit.name)
                                        .join(', ')
                                }}</span
                                ><span v-else class="text-muted-foreground"
                                    >Não definido</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        event.status === 'cancelled' ||
                                        event.status === 'archived'
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    >{{ labels[event.status] }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <select
                                    v-if="
                                        manageableUnitIds.includes(
                                            event.organizational_unit_id,
                                        )
                                    "
                                    :value="event.status"
                                    class="h-8 rounded-md border bg-background px-2 text-xs"
                                    aria-label="Alterar situação"
                                    @change="
                                        transition(
                                            event,
                                            ($event.target as HTMLSelectElement)
                                                .value as EventStatus,
                                        )
                                    "
                                >
                                    <option
                                        v-for="item in statuses"
                                        :key="item"
                                        :value="item"
                                    >
                                        {{ labels[item] }}
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr v-if="!events.data.length">
                            <td
                                colspan="5"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Nenhum evento encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="events"
        /></Card>
    </main>
</template>
