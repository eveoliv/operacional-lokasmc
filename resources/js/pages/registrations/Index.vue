<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import RegistrationController from '@/actions/App/Http/Controllers/RegistrationController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/registrations';
import type { Paginated, Registration } from '@/types';
const props = defineProps<{
    registrations: Paginated<Registration>;
    filters: { status?: string };
}>();
const status = ref(props.filters.status ?? '');
const form = useForm({ event_id: '', person_id: '', source: 'operator' });
const filter = () =>
    router.get(
        index.url(),
        { status: status.value || undefined },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(RegistrationController.store.url(Number(form.event_id)), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const cancel = (registration: Registration) => {
    const reason =
        window.prompt('Motivo do cancelamento (opcional):') ?? undefined;

    if (reason !== undefined) {
        router.patch(
            RegistrationController.cancel.url({
                event: registration.event_id,
                registration: registration.id,
            }),
            { reason },
            { preserveScroll: true },
        );
    }
};
const date = (v: string | null) =>
    v
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(v))
        : '—';
const sources = {
    self_service: 'Autoinscrição',
    operator: 'Operador',
    import: 'Importação',
};
defineOptions({
    layout: { breadcrumbs: [{ title: 'Inscrições', href: index() }] },
});
</script>
<template>
    <Head title="Inscrições" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Inscrições</h1>
            <p class="text-sm text-muted-foreground">
                Participantes inscritos nos eventos visíveis.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex gap-2 text-base"
                    ><UserPlus class="size-4" /> Nova inscrição</CardTitle
                ></CardHeader
            ><CardContent
                ><form
                    class="grid gap-4 sm:grid-cols-3 lg:grid-cols-4"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2">
                        <Label for="registration-event">ID do evento</Label
                        ><Input
                            id="registration-event"
                            v-model="form.event_id"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registration-person">ID da pessoa</Label
                        ><Input
                            id="registration-person"
                            v-model="form.person_id"
                            type="number"
                            min="1"
                            required
                        /><InputError :message="form.errors.person_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="registration-source">Origem</Label
                        ><select
                            id="registration-source"
                            v-model="form.source"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="operator">Operador</option>
                            <option value="self_service">Autoinscrição</option>
                            <option value="import">Importação</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><UserPlus class="size-4" /> Inscrever</Button
                        >
                    </div>
                </form></CardContent
            ></Card
        ><Card class="overflow-hidden"
            ><CardHeader
                ><form class="flex justify-end gap-2" @submit.prevent="filter">
                    <select
                        v-model="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        aria-label="Filtrar situação"
                    >
                        <option value="">Todas</option>
                        <option value="active">Ativas</option>
                        <option value="cancelled">Canceladas</option></select
                    ><Button variant="secondary">Filtrar</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Participante</th>
                            <th class="px-4 py-3">Evento</th>
                            <th class="px-4 py-3">Inscrição</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="registration in registrations.data"
                            :key="registration.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ registration.person.name }}
                            </td>
                            <td class="px-4 py-3">
                                {{ registration.event.title }}
                            </td>
                            <td class="px-4 py-3">
                                {{ date(registration.registered_at)
                                }}<span
                                    class="block text-xs text-muted-foreground"
                                    >{{ sources[registration.source] }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        registration.status === 'active'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    >{{
                                        registration.status === 'active'
                                            ? 'Ativa'
                                            : 'Cancelada'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="registration.status === 'active'"
                                    size="sm"
                                    variant="ghost"
                                    @click="cancel(registration)"
                                    ><Ban class="size-4" /> Cancelar</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!registrations.data.length">
                            <td
                                colspan="5"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Nenhuma inscrição encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="registrations"
        /></Card>
    </main>
</template>
