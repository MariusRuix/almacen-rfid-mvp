<script setup lang="ts">
import { estadoConfirmacion as e, responderConfirmacion } from '@/composables/useConfirmar';
import { CircleAlert, CircleHelp } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

const aceptarBtn = ref<HTMLButtonElement | null>(null);
const cancelarBtn = ref<HTMLButtonElement | null>(null);
let previo: HTMLElement | null = null;

watch(
    () => e.abierto,
    async (abierto) => {
        if (abierto) {
            previo = document.activeElement as HTMLElement | null;
            await nextTick();
            // En acciones de riesgo el foco empieza en Cancelar.
            (e.tono === 'peligro' ? cancelarBtn.value : aceptarBtn.value)?.focus();
        } else {
            previo?.focus?.();
        }
    },
);

function teclado(ev: KeyboardEvent) {
    if (ev.key === 'Escape') responderConfirmacion(false);
    if (ev.key === 'Tab') {
        // Mantiene el foco dentro del diálogo.
        const [a, b] = [cancelarBtn.value, aceptarBtn.value];
        if (ev.shiftKey && document.activeElement === a) {
            ev.preventDefault();
            b?.focus();
        } else if (!ev.shiftKey && document.activeElement === b) {
            ev.preventDefault();
            a?.focus();
        }
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="dialogo">
            <div v-if="e.abierto" class="fondo fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" @mousedown.self="responderConfirmacion(false)" @keydown="teclado">
                <div class="caja w-full max-w-md rounded-ui border border-line bg-surface" role="alertdialog" aria-modal="true" aria-labelledby="dlg-titulo" aria-describedby="dlg-cuerpo">
                    <div class="flex gap-3 px-5 pt-5">
                        <component :is="e.tono === 'peligro' ? CircleAlert : CircleHelp" class="mt-0.5 size-6 flex-none" :class="e.tono === 'peligro' ? 'text-bad' : 'text-primary'" aria-hidden="true" />
                        <div id="dlg-cuerpo" class="min-w-0">
                            <h2 id="dlg-titulo" class="text-lg leading-snug font-bold">{{ e.titulo }}</h2>
                            <p v-if="e.mensaje" class="mt-1 text-ink-2">{{ e.mensaje }}</p>
                            <ul v-if="e.detalle?.length" class="mt-3 space-y-1.5 text-sm">
                                <li v-for="(d, i) in e.detalle" :key="i" class="flex gap-2"><span class="mt-[0.45rem] size-1.5 flex-none rounded-full bg-ink-3" aria-hidden="true" />{{ d }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-line bg-surface-2 px-5 py-3 sm:flex-row sm:justify-end">
                        <button ref="cancelarBtn" type="button" class="btn btn-secundario" @click="responderConfirmacion(false)">{{ e.cancelar }}</button>
                        <button ref="aceptarBtn" type="button" class="btn" :class="e.tono === 'peligro' ? 'btn-peligro' : 'btn-primario'" @click="responderConfirmacion(true)">{{ e.aceptar }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fondo {
    background: rgb(15 22 30 / 0.45);
}
.caja {
    box-shadow: 0 18px 48px rgb(15 22 30 / 0.28);
}
.dialogo-enter-active,
.dialogo-leave-active {
    transition: opacity 200ms cubic-bezier(0.16, 1, 0.3, 1);
}
.dialogo-enter-active .caja,
.dialogo-leave-active .caja {
    transition: transform 240ms cubic-bezier(0.16, 1, 0.3, 1);
}
.dialogo-enter-from,
.dialogo-leave-to {
    opacity: 0;
}
.dialogo-enter-from .caja,
.dialogo-leave-to .caja {
    transform: translateY(10px) scale(0.98);
}
@media (prefers-reduced-motion: reduce) {
    .dialogo-enter-from .caja,
    .dialogo-leave-to .caja {
        transform: none;
    }
}
</style>
