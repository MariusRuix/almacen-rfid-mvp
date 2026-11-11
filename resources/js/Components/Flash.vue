<script setup lang="ts">
import type { PropsCompartidas } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<PropsCompartidas>();
const aviso = ref<{ tipo: 'ok' | 'error'; texto: string } | null>(null);
let temporizador: ReturnType<typeof setTimeout> | undefined;

watch(
    () => [page.props.flash.ok, page.props.flash.error, page.url],
    () => {
        const { ok, error } = page.props.flash;
        if (!ok && !error) return;
        aviso.value = error ? { tipo: 'error', texto: error } : { tipo: 'ok', texto: ok! };
        clearTimeout(temporizador);
        temporizador = setTimeout(() => (aviso.value = null), error ? 8000 : 4500);
    },
    { immediate: true },
);
</script>

<template>
    <Transition name="aviso">
        <div
            v-if="aviso"
            class="fixed right-4 bottom-4 left-4 z-50 mx-auto flex max-w-md items-start gap-3 rounded-ui border px-4 py-3 shadow-[0_8px_24px_rgb(20_33_48/0.18)] sm:left-auto"
            :class="aviso.tipo === 'error' ? 'border-bad/30 bg-bad-bg text-bad' : 'border-ok/30 bg-ok-bg text-ok'"
            role="status"
            aria-live="polite"
        >
            <component :is="aviso.tipo === 'error' ? CircleAlert : CircleCheck" class="mt-0.5 size-5 flex-none" aria-hidden="true" />
            <p class="flex-1 font-medium">{{ aviso.texto }}</p>
            <button type="button" class="-m-1 p-1 opacity-70 hover:opacity-100" aria-label="Cerrar aviso" @click="aviso = null">
                <X class="size-4" />
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.aviso-enter-active,
.aviso-leave-active {
    transition:
        opacity 220ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
}
.aviso-enter-from,
.aviso-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
