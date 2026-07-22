<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, Building2, Move, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/organization';
import type { OrganizationUnit, OrganizationUnitType } from '@/types';

const props = defineProps<{
    units: OrganizationUnit[];
    types: OrganizationUnitType[];
    filters: { archived?: boolean };
    manageableUnitIds: number[];
}>();
const archived = ref(Boolean(props.filters.archived));
const form = useForm({
    organizational_unit_type_id: '',
    parent_id: '',
    code: '',
    name: '',
});
const create = () =>
    form.post(OrganizationController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const filter = () =>
    router.get(
        index.url(),
        { archived: archived.value ? 1 : undefined },
        { preserveState: true, replace: true },
    );
const canManage = (unit: OrganizationUnit) =>
    props.manageableUnitIds.includes(unit.id);
const edit = (unit: OrganizationUnit) => {
    const name = window.prompt('Nome da unidade', unit.name);

    if (name === null) {
        return;
    }

    const code = window.prompt('Código da unidade', unit.code);

    if (code === null) {
        return;
    }

    router.put(
        OrganizationController.update.url(unit.id),
        {
            organizational_unit_type_id: unit.organizational_unit_type_id,
            name,
            code,
        },
        { preserveScroll: true },
    );
};
const move = (unit: OrganizationUnit) => {
    const value = window.prompt(
        'ID da nova unidade pai (vazio para raiz)',
        unit.parent_id?.toString() ?? '',
    );

    if (value === null) {
        return;
    }

    router.patch(
        OrganizationController.move.url(unit.id),
        { parent_id: value || null },
        { preserveScroll: true },
    );
};
const archive = (unit: OrganizationUnit) => {
    if (window.confirm(`Arquivar ${unit.name} e seus descendentes?`)) {
        router.patch(
            OrganizationController.archive.url(unit.id),
            {},
            { preserveScroll: true },
        );
    }
};
defineOptions({
    layout: { breadcrumbs: [{ title: 'Organização', href: index() }] },
});
</script>

<template>
    <Head title="Organização" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1
                class="flex items-center gap-2 text-2xl font-semibold tracking-tight"
            >
                <Building2 class="size-6" /> Organização
            </h1>
            <p class="text-sm text-muted-foreground">
                Unidades visíveis no seu escopo organizacional.
            </p>
        </header>
        <Card v-if="manageableUnitIds.length"
            ><CardHeader
                ><CardTitle class="flex items-center gap-2 text-base"
                    ><Plus class="size-4" /> Nova unidade</CardTitle
                ></CardHeader
            ><CardContent>
                <form
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-5"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2">
                        <Label for="unit-name">Nome</Label
                        ><Input
                            id="unit-name"
                            v-model="form.name"
                            required
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-code">Código</Label
                        ><Input
                            id="unit-code"
                            v-model="form.code"
                            required
                        /><InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-type">Tipo</Label
                        ><select
                            id="unit-type"
                            v-model="form.organizational_unit_type_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="" disabled>Selecione</option>
                            <option
                                v-for="type in types"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }}
                            </option></select
                        ><InputError
                            :message="form.errors.organizational_unit_type_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-parent">Unidade pai</Label
                        ><select
                            id="unit-parent"
                            v-model="form.parent_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">Raiz</option>
                            <option
                                v-for="unit in units.filter(canManage)"
                                :key="unit.id"
                                :value="unit.id"
                            >
                                {{ unit.name }}
                            </option></select
                        ><InputError :message="form.errors.parent_id" />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><Plus class="size-4" /> Criar</Button
                        >
                    </div>
                </form>
            </CardContent></Card
        >
        <Card class="overflow-hidden"
            ><CardHeader
                ><label class="flex items-center gap-2 text-sm"
                    ><input
                        v-model="archived"
                        type="checkbox"
                        class="size-4"
                        @change="filter"
                    />
                    Exibir arquivadas</label
                ></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Pai</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="unit in units"
                            :key="unit.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ unit.name
                                }}<span
                                    class="block text-xs font-normal text-muted-foreground"
                                    >{{ unit.code }}</span
                                >
                            </td>
                            <td class="px-4 py-3">{{ unit.type.name }}</td>
                            <td class="px-4 py-3">
                                {{ unit.parent?.name ?? 'Raiz' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        unit.archived_at
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    >{{
                                        unit.archived_at ? 'Arquivada' : 'Ativa'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    v-if="canManage(unit) && !unit.archived_at"
                                    class="inline-flex gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="edit(unit)"
                                        ><Pencil class="size-4" />
                                        Editar</Button
                                    ><Button
                                        size="sm"
                                        variant="ghost"
                                        @click="move(unit)"
                                        ><Move class="size-4" /> Mover</Button
                                    ><Button
                                        size="sm"
                                        variant="ghost"
                                        @click="archive(unit)"
                                        ><Archive class="size-4" />
                                        Arquivar</Button
                                    >
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!units.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Nenhuma unidade encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </main>
</template>
