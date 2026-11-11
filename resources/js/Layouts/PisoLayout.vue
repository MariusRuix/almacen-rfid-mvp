<script setup lang="ts">
import Flash from '@/Components/Flash.vue';
import { useCompartido } from '@/composables/useCompartido';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, LogOut } from 'lucide-vue-next';

defineProps<{ titulo: string; volver?: string }>();
const { usuario, esResponsable } = useCompartido();
</script>

<template>
    <div class="min-h-dvh bg-ground">
        <header class="sticky top-0 z-20 bg-rail text-rail-ink">
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
                <Link v-if="volver" :href="volver" class="-ml-2 flex size-12 items-center justify-center rounded-ui hover:bg-rail-2" aria-label="Volver">
                    <ChevronLeft class="size-7" />
                </Link>
                <h1 class="flex-1 truncate font-display text-xl font-bold">{{ titulo }}</h1>
                <Link v-if="esResponsable" href="/" class="rounded-ui px-3 py-2 text-sm text-rail-muted hover:bg-rail-2 hover:text-rail-ink">Panel</Link>
                <span class="hidden text-sm text-rail-muted sm:inline">{{ usuario?.name }}</span>
                <Link href="/logout" method="post" as="button" class="flex size-12 items-center justify-center rounded-ui text-rail-muted hover:bg-rail-2" aria-label="Cerrar sesión">
                    <LogOut class="size-5" />
                </Link>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-5">
            <slot />
        </main>
        <Flash />
    </div>
</template>
