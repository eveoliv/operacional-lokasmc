<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, UserCheck, UserX } from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import IndexPagination from '@/components/IndexPagination.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/users';
import type { ManagedUser, Paginated } from '@/types';
const props = defineProps<{
    users: Paginated<ManagedUser>;
    filters: { search?: string };
}>();
const search = ref(props.filters.search ?? '');
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});
const filter = () =>
    router.get(
        index.url(),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
const create = () =>
    form.post(UserController.store.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
const toggle = (user: ManagedUser) => {
    if (user.disabled_at) {
        router.patch(
            UserController.reactivate.url(user.id),
            {},
            { preserveScroll: true },
        );
    } else {
        const reason = window.prompt(`Motivo para desativar ${user.name}:`);

        if (reason) {
            router.patch(
                UserController.disable.url(user.id),
                { reason },
                { preserveScroll: true },
            );
        }
    }
};
defineOptions({
    layout: { breadcrumbs: [{ title: 'Usuários', href: index() }] },
});
</script>
<template>
    <Head title="Usuários" />
    <main class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold">Usuários</h1>
            <p class="text-sm text-muted-foreground">
                Contas de acesso ao ambiente operacional.
            </p>
        </header>
        <Card
            ><CardHeader
                ><CardTitle class="flex items-center gap-2 text-base"
                    ><Plus class="size-4" /> Novo usuário</CardTitle
                ></CardHeader
            ><CardContent
                ><form
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="create"
                >
                    <div class="grid gap-2">
                        <Label for="user-name">Nome</Label
                        ><Input
                            id="user-name"
                            v-model="form.name"
                            required
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="user-email">E-mail</Label
                        ><Input
                            id="user-email"
                            v-model="form.email"
                            type="email"
                            required
                        /><InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="user-password">Senha</Label
                        ><Input
                            id="user-password"
                            v-model="form.password"
                            type="password"
                            required
                        /><InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="user-confirm">Confirmar senha</Label
                        ><Input
                            id="user-confirm"
                            v-model="form.password_confirmation"
                            type="password"
                            required
                        /><Button :disabled="form.processing"
                            ><Plus class="size-4" /> Criar</Button
                        >
                    </div>
                </form></CardContent
            ></Card
        >
        <Card class="overflow-hidden"
            ><CardHeader
                ><form class="flex gap-2" @submit.prevent="filter">
                    <div class="relative flex-1">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            class="pl-9"
                            aria-label="Buscar usuários"
                            placeholder="Nome ou e-mail"
                        />
                    </div>
                    <Button variant="secondary">Filtrar</Button>
                </form></CardHeader
            >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-y bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3">Usuário</th>
                            <th class="px-4 py-3">Pessoa / unidade</th>
                            <th class="px-4 py-3">Situação</th>
                            <th class="px-4 py-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ user.name
                                }}<span
                                    class="block text-xs font-normal text-muted-foreground"
                                    >{{ user.email }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ user.person?.name || 'Não vinculada'
                                }}<span
                                    v-if="user.person"
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        user.person.organizational_unit?.name
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        user.disabled_at
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    >{{
                                        user.disabled_at
                                            ? 'Desativado'
                                            : 'Ativo'
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    size="sm"
                                    :variant="
                                        user.disabled_at ? 'outline' : 'ghost'
                                    "
                                    @click="toggle(user)"
                                    ><UserCheck
                                        v-if="user.disabled_at"
                                        class="size-4"
                                    /><UserX v-else class="size-4" />{{
                                        user.disabled_at
                                            ? 'Reativar'
                                            : 'Desativar'
                                    }}</Button
                                >
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td
                                colspan="4"
                                class="p-12 text-center text-muted-foreground"
                            >
                                Nenhum usuário encontrado.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <IndexPagination :paginator="users"
        /></Card>
    </main>
</template>
