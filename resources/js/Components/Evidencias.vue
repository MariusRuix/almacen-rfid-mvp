<script setup lang="ts">
import Girando from '@/Components/Girando.vue';
import { useCompartido } from '@/composables/useCompartido';
import { confirmar } from '@/composables/useConfirmar';
import { fechaYHora } from '@/lib/formato';
import type { Evidencia } from '@/types';
import { router } from '@inertiajs/vue3';
import { Camera, FileText, TriangleAlert, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * Fotos o documentos que respaldan un paso. En la tablet abre la cámara; en la computadora, el explorador.
 * Las fotos se reducen en el navegador antes de subirlas (lado mayor de 1600 px), así pesan poco.
 */
const props = withDefaults(
    defineProps<{
        tipo: 'recepcion' | 'caja' | 'incidencia' | 'destino' | 'pedido';
        id: number;
        momento: string;
        evidencias: Evidencia[];
        titulo: string;
        ayuda?: string;
        /** Muestra un aviso cuando no hay ninguna (no bloquea). */
        sugerida?: string | null;
        puedeSubir?: boolean;
        grande?: boolean;
    }>(),
    { ayuda: undefined, sugerida: null, puedeSubir: true, grande: false },
);

const { usuario, esResponsable, page } = useCompartido();
const lista = computed(() => props.evidencias.filter((e) => e.momento === props.momento));
const subiendo = ref(false);
const entrada = ref<HTMLInputElement | null>(null);
const uid = `ev-${props.tipo}-${props.id}-${props.momento}`;
const error = computed(() => {
    const e = page.props.errors ?? {};
    const k = Object.keys(e).find((x) => x.startsWith('archivos'));
    return k && subidoAqui.value ? e[k] : null;
});
const subidoAqui = ref(false);

async function reducir(f: File): Promise<File> {
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return f;
    try {
        const img = await createImageBitmap(f);
        const escala = Math.min(1, 1600 / Math.max(img.width, img.height));
        if (escala === 1 && f.size < 1_500_000) return f;
        const lienzo = document.createElement('canvas');
        lienzo.width = Math.round(img.width * escala);
        lienzo.height = Math.round(img.height * escala);
        lienzo.getContext('2d')!.drawImage(img, 0, 0, lienzo.width, lienzo.height);
        const blob = await new Promise<Blob | null>((r) => lienzo.toBlob(r, 'image/jpeg', 0.82));
        return blob ? new File([blob], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f;
    } catch {
        return f;
    }
}

async function alElegir(ev: Event) {
    const archivos = Array.from((ev.target as HTMLInputElement).files ?? []);
    if (!archivos.length) return;
    subiendo.value = true;
    subidoAqui.value = true;
    const listos = await Promise.all(archivos.slice(0, 8).map(reducir));
    router.post(
        '/evidencias',
        { tipo: props.tipo, id: props.id, momento: props.momento, archivos: listos },
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                subiendo.value = false;
                if (entrada.value) entrada.value.value = '';
            },
        },
    );
}

const puedeBorrar = (e: Evidencia) => esResponsable.value || e.usuario_id === usuario.value?.id;
async function borrar(e: Evidencia) {
    const ok = await confirmar({ titulo: '¿Eliminar esta evidencia?', mensaje: `${e.nombre} · ${e.usuario ?? ''}`, aceptar: 'Eliminar', tono: 'peligro' });
    if (ok) router.delete(`/evidencias/${e.id}`, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div class="evidencias">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                <Camera class="size-4 text-ink-2" aria-hidden="true" />{{ titulo }}
                <span v-if="lista.length" class="num rounded-full bg-surface-2 px-2 text-xs text-ink-2">{{ lista.length }}</span>
            </p>
            <template v-if="puedeSubir">
                <input
                    :id="uid"
                    ref="entrada"
                    type="file"
                    accept="image/*,application/pdf"
                    capture="environment"
                    multiple
                    class="sr-only"
                    :disabled="subiendo"
                    @change="alElegir"
                />
                <label :for="uid" class="btn btn-secundario cursor-pointer" :class="grande ? 'btn-grande' : 'min-h-9 px-3 text-sm'" :aria-disabled="subiendo">
                    <Girando v-if="subiendo" /><Camera v-else :class="grande ? 'size-5' : 'size-4'" aria-hidden="true" />
                    {{ subiendo ? 'Subiendo…' : lista.length ? 'Agregar otra' : 'Agregar foto' }}
                </label>
            </template>
        </div>

        <ul v-if="lista.length" class="mt-2 flex flex-wrap gap-2">
            <li v-for="e in lista" :key="e.id" class="relative">
                <a
                    :href="e.url"
                    target="_blank"
                    class="flex size-20 items-center justify-center overflow-hidden rounded-ui border border-line bg-surface-2 hover:border-ink-3"
                    :title="`${e.nombre} · ${e.usuario ?? ''} · ${fechaYHora(e.fecha)}`"
                >
                    <img v-if="e.es_imagen" :src="e.url" :alt="e.nombre" class="size-full object-cover" loading="lazy" />
                    <span v-else class="flex flex-col items-center gap-1 px-1 text-center text-[0.65rem] leading-tight text-ink-2"><FileText class="size-6" />{{ e.nombre.slice(0, 18) }}</span>
                </a>
                <button
                    v-if="puedeBorrar(e)"
                    type="button"
                    class="absolute -top-1.5 -right-1.5 flex size-6 items-center justify-center rounded-full border border-line bg-surface text-ink-2 shadow-sm hover:text-bad"
                    :aria-label="`Eliminar ${e.nombre}`"
                    @click="borrar(e)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>
        <p v-else-if="sugerida" class="mt-1.5 flex items-start gap-1.5 text-sm text-warn"><TriangleAlert class="mt-0.5 size-4 flex-none" aria-hidden="true" />{{ sugerida }}</p>
        <p v-else-if="ayuda" class="mt-1 text-sm text-ink-3">{{ ayuda }}</p>
        <p v-if="error" class="mt-1.5 text-sm text-bad">{{ error }}</p>
    </div>
</template>
