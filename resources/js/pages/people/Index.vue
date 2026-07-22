<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Archive, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/people';
import type { Paginated, Person } from '@/types';

const props = defineProps<{
    people: Paginated<Person>;
    filters: { search?: string; archived?: boolean };
    manageableUnitIds: number[];
}>();
const search = ref(props.filters.search ?? '');
const archived = ref(Boolean(props.filters.archived));
const form = useForm({
    organizational_unit_id: '',
    user_id: '',
    name: '',
    email: '',
    phone: '',
    document: '',
    birth_date: '',
    status: 'active',
});
const submitFilters = () =>
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            archived: archived.value ? 1 : undefined,
        },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(PersonController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const archive = (person: Person) => {
    if (window.confirm(`Arquivar ${person.name}?`)) {
        router.patch(
            PersonController.archive.url(person.id),
            {},
            { preserveScroll: true },
        );
    }
};

defineOptions({
    layout: { breadcrumbs: [{ title: 'Pessoas', href: index() }] },
});
</script>

<template>
    <Head title="Pessoas" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Pessoas</h1>
            <p class="text-sm text-muted-foreground">
                Cadastro de pessoas por unidade organizacional.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex items-center gap-2 text-base"
                    ><Plus class="size-4" /> Nova pessoa</CardTitle
                ></CardHeader
            ><CardContent>
                <form
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2 xl:col-span-2">
                        <Label for="person-name">Nome</Label
                        ><Input
                            id="person-name"
                            v-model="form.name"
                            required
                            autocomplete="name"
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-unit">ID da unidade</Label
                        ><Input
                            id="person-unit"
                            v-model="form.organizational_unit_id"
                            type="number"
                            min="1"
                            required
                        /><InputError
                            :message="form.errors.organizational_unit_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-email">E-mail</Label
                        ><Input
                            id="person-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                        /><InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-phone">Telefone</Label
                        ><Input
                            id="person-phone"
                            v-model="form.phone"
                            autocomplete="tel"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-document">Documento</Label
                        ><Input id="person-document" v-model="form.document" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="person-birth">Nascimento</Label
                        ><Input
                            id="person-birth"
                            v-model="form.birth_date"
                            type="date"
                        />
                    </div>
                    <div class="flex items-end">
                        <Button class="w-full" :disabled="form.processing"
                            ><Plus class="size-4" /> Cadastrar</Button
                        >
                    </div>
                </form>
            </CardContent></Card
        >
        <Card class="overflow-hidden"
            ><CardHeader
                ><form
                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                    @submit.prevent="submitFilters"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="people-search">Buscar</Label>
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            /><Input
                                id="people-search"
                                v-model="search"
                                class="pl-9"
                                placeholder="Nome, e-mail ou documento"
                            />
                        </div>
                    </div>
                    <label class="flex h-9 items-center gap-2 text-sm"
                        ><input
                            v-model="archived"
                            type="checkbox"
                            class="size-4"
                        />
                        Exibir arquivadas</label
                    ><Button type="submit" variant="secondary">Filtrar</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Pessoa</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Contato</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="person in people.data"
                            :key="person.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ person.name
                                }}<span
                                    v-if="person.document"
                                    class="block text-xs font-normal text-muted-foreground"
                                    >{{ person.document }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ person.organizational_unit?.name }}
                            </td>
                            <td class="px-4 py-3">
                                {{ person.email || '—'
                                }}<span
                                    v-if="person.phone"
                                    class="block text-xs text-muted-foreground"
                                    >{{ person.phone }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        person.archived_at
                                            ? 'secondary'
                                            : person.status === 'active'
                                              ? 'default'
                                              : 'outline'
                                    "
                                    >{{
                                        person.archived_at
                                            ? 'Arquivada'
                                            : person.status === 'active'
                                              ? 'Ativa'
                                              : 'Inativa'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="
                                        !person.archived_at &&
                                        manageableUnitIds.includes(
                                            person.organizational_unit_id,
                                        )
                                    "
                                    size="sm"
                                    variant="ghost"
                                    @click="archive(person)"
                                    ><Archive class="size-4" /> Arquivar</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!people.data.length">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                Nenhuma pessoa encontrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="people" />
        </Card>
    </main>
</template>
