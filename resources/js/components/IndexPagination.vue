<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Pick<
        Paginated<unknown>,
        | 'current_page'
        | 'last_page'
        | 'total'
        | 'from'
        | 'to'
        | 'prev_page_url'
        | 'next_page_url'
    >;
}>();
</script>

<template>
    <nav
        class="flex flex-col gap-3 border-t px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
        aria-label="Paginação"
    >
        <p class="text-muted-foreground" aria-live="polite">
            <template v-if="paginator.total">
                Exibindo {{ paginator.from }}–{{ paginator.to }} de
                {{ paginator.total }}
            </template>
            <template v-else>Nenhum registro</template>
        </p>
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                as-child
                :disabled="!paginator.prev_page_url"
            >
                <Link
                    :href="paginator.prev_page_url ?? '# '"
                    preserve-scroll
                    :aria-disabled="!paginator.prev_page_url"
                >
                    <ChevronLeft class="size-4" />
                    Anterior
                </Link>
            </Button>
            <span class="px-2 text-muted-foreground">
                {{ paginator.current_page }} / {{ paginator.last_page }}
            </span>
            <Button
                variant="outline"
                size="sm"
                as-child
                :disabled="!paginator.next_page_url"
            >
                <Link
                    :href="paginator.next_page_url ?? '# '"
                    preserve-scroll
                    :aria-disabled="!paginator.next_page_url"
                >
                    Próxima
                    <ChevronRight class="size-4" />
                </Link>
            </Button>
        </div>
    </nav>
</template>
