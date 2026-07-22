<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileClock, Search } from '@lucide/vue';
import { ref } from 'vue';
import IndexPagination from '@/components/IndexPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/audit';
import type { AuditLog, Paginated } from '@/types';

const props = defineProps<{
    logs: Paginated<AuditLog>;
    filters: { action?: string; actor?: string };
    actions: string[];
}>();
const action = ref(props.filters.action ?? '');
const actor = ref(props.filters.actor ?? '');
const filter = () =>
    router.get(
        index.url(),
        { action: action.value || undefined, actor: actor.value || undefined },
        { preserveState: true, replace: true },
    );
const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
const values = (value: Record<string, unknown> | null) =>
    value && Object.keys(value).length ? JSON.stringify(value) : '—';
defineOptions({
    layout: { breadcrumbs: [{ title: 'Auditoria', href: index() }] },
});
</script>

<template>
    <Head title="Auditoria" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1
                class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
            >
                <FileClock class="size-6" /> Auditoria
            </h1>
            <p class="text-sm text-muted-foreground">
                Consulta somente leitura dos registros no seu escopo.
            </p>
        </header>
        <Card class="overflow-hidden"
            ><CardHeader
                ><form
                    class="flex flex-col gap-3 md:flex-row md:items-end"
                    @submit.prevent="filter"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="audit-actor">Ator</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                id="audit-actor"
                                v-model="actor"
                                class="pl-9"
                                placeholder="Nome ou e-mail"
                            />
                        </div>
                    </div>
                    <div class="grid flex-1 gap-2">
                        <Label for="audit-action">Ação</Label
                        ><select
                            id="audit-action"
                            v-model="action"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="item in actions"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select>
                    </div>
                    <Button type="submit" variant="secondary">Filtrar</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Ação</th>
                            <th class="px-4 py-3">Ator</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Alterações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in logs.data"
                            :key="log.id"
                            class="border-b align-top last:border-0"
                        >
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ date(log.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{ log.action }}</Badge
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{ log.auditable_type }} #{{
                                        log.auditable_id ?? '—'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ log.actor?.name ?? 'Sistema'
                                }}<span
                                    v-if="log.actor"
                                    class="block text-xs text-muted-foreground"
                                    >{{ log.actor.email }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ log.organizational_unit?.name ?? '—' }}
                            </td>
                            <td class="max-w-lg px-4 py-3 font-mono text-xs">
                                <div>
                                    <span class="text-muted-foreground"
                                        >Antes:</span
                                    >
                                    {{ values(log.old_values) }}
                                </div>
                                <div>
                                    <span class="text-muted-foreground"
                                        >Depois:</span
                                    >
                                    {{ values(log.new_values) }}
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Nenhum registro de auditoria encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="logs"
        /></Card>
    </main>
</template>
