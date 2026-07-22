<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Plus, ShieldX } from '@lucide/vue';
import { ref } from 'vue';
import AccessGrantController from '@/actions/App/Http/Controllers/AccessGrantController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/access-grants';
import type { AccessGrant, Paginated } from '@/types';
const props = defineProps<{
    accessGrants: Paginated<AccessGrant>;
    filters: { revoked?: boolean };
}>();
const revoked = ref(Boolean(props.filters.revoked));
const form = useForm({
    user_id: '',
    role_id: '',
    organizational_unit_id: '',
    starts_at: '',
    ends_at: '',
});
const filter = () =>
    router.get(
        index.url(),
        { revoked: revoked.value ? 1 : undefined },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(AccessGrantController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const revoke = (grant: AccessGrant) => {
    const reason =
        window.prompt('Motivo da revogação (opcional):') ?? undefined;

    if (reason !== undefined) {
        router.patch(
            AccessGrantController.revoke.url(grant.id),
            { reason },
            { preserveScroll: true },
        );
    }
};
defineOptions({
    layout: { breadcrumbs: [{ title: 'Concessões de acesso', href: index() }] },
});
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Sem limite';
</script>
<template>
    <Head title="Concessões de acesso" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Concessões de acesso</h1>
            <p class="text-sm text-muted-foreground">
                Papéis atribuídos por escopo organizacional.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex gap-2 text-base"
                    ><KeyRound class="size-4" /> Nova concessão</CardTitle
                ></CardHeader
            ><CardContent
                ><form
                    class="grid gap-4 md:grid-cols-3 xl:grid-cols-6"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2">
                        <Label for="grant-user">ID do usuário</Label
                        ><Input
                            id="grant-user"
                            v-model="form.user_id"
                            type="number"
                            min="1"
                            required
                        /><InputError :message="form.errors.user_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="grant-role">ID do papel</Label
                        ><Input
                            id="grant-role"
                            v-model="form.role_id"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="grant-unit">ID da unidade</Label
                        ><Input
                            id="grant-unit"
                            v-model="form.organizational_unit_id"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="grant-start">Início</Label
                        ><Input
                            id="grant-start"
                            v-model="form.starts_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="grant-end">Fim</Label
                        ><Input
                            id="grant-end"
                            v-model="form.ends_at"
                            type="datetime-local"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><Plus class="size-4" /> Conceder</Button
                        >
                    </div>
                </form></CardContent
            ></Card
        ><Card class="overflow-hidden"
            ><CardHeader
                ><form
                    class="flex items-center justify-between gap-3"
                    @submit.prevent="filter"
                >
                    <label class="flex items-center gap-2 text-sm"
                        ><input
                            v-model="revoked"
                            type="checkbox"
                            class="size-4"
                        />
                        Exibir revogadas</label
                    ><Button variant="secondary">Aplicar filtro</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Papel</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Validade</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="grant in accessGrants.data"
                            :key="grant.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ grant.user.name
                                }}<span
                                    class="block text-xs font-normal text-muted-foreground"
                                    >{{ grant.user.email }}</span
                                >
                            </td>
                            <td class="px-4 py-3">{{ grant.role.name }}</td>
                            <td class="px-4 py-3">
                                {{ grant.organizational_unit.name }}
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ date(grant.starts_at) }}<br />até
                                {{ date(grant.ends_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        grant.revoked_at
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    >{{
                                        grant.revoked_at
                                            ? 'Revogada'
                                            : 'Vigente'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="!grant.revoked_at"
                                    size="sm"
                                    variant="ghost"
                                    @click="revoke(grant)"
                                    ><ShieldX class="size-4" /> Revogar</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!accessGrants.data.length">
                            <td
                                colspan="6"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Nenhuma concessão encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="accessGrants"
        /></Card>
    </main>
</template>
