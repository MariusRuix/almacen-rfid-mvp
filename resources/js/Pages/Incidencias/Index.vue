<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import Evidencias from '@/Components/Evidencias.vue';
import Girando from '@/Components/Girando.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Vacio from '@/Components/Vacio.vue';
import { useCompartido } from '@/composables/useCompartido';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosIncidencia, tiposIncidencia } from '@/lib/etiquetas';
import { fechaYHora } from '@/lib/formato';
import { reducirImagen } from '@/lib/imagenes';
import type { Evidencia, Producto } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Camera, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface Inc {
    id: number; folio: string; tipo: string; estado: string; cantidad: number; descripcion: string | null; resolucion: string | null;
    origen_folio: string | null; origen_type: string | null; origen_id: number | null; producto: Producto; pedimento: string | null;
    destino: string | null; reporto: string | null; resolvio: string | null; fecha: string; puede_liberar: boolean;
    evidencias: Evidencia[];
}
defineProps<{ incidencias: Inc[]; ubicaciones: { id: number; codigo: string }[] }>();

const { esResponsable } = useCompartido();
const abierta = ref<number | null>(null);
const form = useForm<{ resolucion: string; liberar_a: number | null; archivos: File[] }>({ resolucion: '', liberar_a: null, archivos: [] });

function abrir(i: Inc) {
    abierta.value = abierta.value === i.id ? null : i.id;
    form.reset();
    form.clearErrors();
    if (i.puede_liberar) form.resolucion = `Se completó el accesorio (${i.producto.accesorio}) y se liberan las piezas.`;
}

// Fotos que acompañan la resolución: se suben junto con "Marcar resuelta", en la misma petición.
function elegirFotos(ev: Event) {
    const nuevas = Array.from((ev.target as HTMLInputElement).files ?? []);
    form.archivos = [...form.archivos, ...nuevas].slice(0, 8);
    (ev.target as HTMLInputElement).value = '';
}
const quitarFoto = (k: number) => (form.archivos = form.archivos.filter((_, j) => j !== k));
const vistas = ref<string[]>([]);
watch(
    () => form.archivos,
    (archivos) => {
        vistas.value.forEach((u) => URL.revokeObjectURL(u));
        vistas.value = archivos.map((f) => (f.type.startsWith('image/') ? URL.createObjectURL(f) : ''));
    },
);
onBeforeUnmount(() => vistas.value.forEach((u) => URL.revokeObjectURL(u)));
const errorArchivos = computed(() => Object.entries(form.errors).find(([k]) => k.startsWith('archivos'))?.[1] ?? null);

async function resolver(i: Inc) {
    form.archivos = await Promise.all(form.archivos.map((f) => reducirImagen(f)));
    form.post(`/incidencias/${i.id}/resolver`, { preserveScroll: true, forceFormData: true, onSuccess: () => (abierta.value = null) });
}
const origenHref = (i: Inc) => (i.origen_type === 'recepcion' ? `/recepciones/${i.origen_id}` : i.origen_type === 'pedido' ? `/pedidos/${i.origen_id}` : null);
</script>

<template>
    <Head title="Incidencias" />
    <AppLayout>
        <Encabezado titulo="Incidencias" descripcion="Toda diferencia queda registrada con su origen, quién la reportó, cómo se resolvió y sus fotos." />

        <section class="panel">
            <ul v-if="incidencias.length" class="divide-y-2 divide-line">
                <li v-for="i in incidencias" :key="i.id" class="px-5 py-5">
                    <div class="flex flex-wrap items-start gap-x-5 gap-y-2">
                        <div class="w-24">
                            <p class="font-id font-semibold">{{ i.folio }}</p>
                            <Chip :valor="i.estado" :mapa="estadosIncidencia" class="mt-1" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p>
                                <span class="font-semibold" :class="i.estado === 'abierta' ? 'text-bad' : 'text-ink'">{{ tiposIncidencia[i.tipo] ?? i.tipo }}</span>
                                · <span class="num font-semibold">{{ i.cantidad }}</span> pz de <IdSegmentado :valor="i.producto.sku" tipo="sku" />
                                <template v-if="i.destino"> · destino <span class="font-id">{{ i.destino }}</span></template>
                            </p>
                            <p class="mt-0.5 text-sm text-ink-2">{{ i.descripcion }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-ink-3">
                                <span>{{ fechaYHora(i.fecha) }} · reportó {{ i.reporto ?? '—' }}</span>
                                <IdSegmentado v-if="i.pedimento" :valor="i.pedimento" tipo="pedimento" tam="sm" />
                                <Link v-if="origenHref(i)" :href="origenHref(i)!" class="font-id font-semibold text-primary hover:underline">{{ i.origen_folio }}</Link>
                            </p>
                            <p v-if="i.resolucion" class="mt-2 text-sm"><b>Resolución:</b> {{ i.resolucion }} <span class="text-ink-3">({{ i.resolvio }})</span></p>
                        </div>
                        <button v-if="i.estado === 'abierta' && esResponsable" type="button" class="btn btn-secundario" @click="abrir(i)">
                            {{ abierta === i.id ? 'Cancelar' : 'Resolver' }}
                        </button>
                    </div>

                    <!-- Fotos de esta incidencia, en su propio recuadro para que no se confundan con la de abajo -->
                    <div class="mt-3 rounded-ui border border-line bg-surface-2/60 p-3 sm:ml-[7.25rem]">
                        <Evidencias
                            tipo="incidencia"
                            :id="i.id"
                            momento="incidencia"
                            :titulo="`Fotos de ${i.folio}`"
                            ayuda="Foto de la pieza, la caja o la diferencia."
                            :sugerida="i.estado === 'abierta' ? 'Todavía sin foto. Se recomienda tener una antes de resolver, pero no lo impide.' : null"
                            :evidencias="i.evidencias"
                        />
                    </div>

                    <form v-if="abierta === i.id" class="mt-3 space-y-3 rounded-ui border border-primary/30 bg-surface-2 p-4 sm:ml-[7.25rem]" @submit.prevent="resolver(i)">
                        <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                            <div>
                                <label :for="`res-${i.id}`" class="etiqueta">Qué se hizo</label>
                                <input :id="`res-${i.id}`" v-model="form.resolucion" class="campo" required />
                                <p v-if="form.errors.resolucion" class="mt-1 text-sm text-bad">{{ form.errors.resolucion }}</p>
                            </div>
                            <div v-if="i.puede_liberar">
                                <label :for="`lib-${i.id}`" class="etiqueta">Liberar piezas a</label>
                                <select :id="`lib-${i.id}`" v-model="form.liberar_a" class="campo font-id">
                                    <option :value="null">No liberar</option>
                                    <option v-for="u in ubicaciones" :key="u.id" :value="u.id">{{ u.codigo }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <p class="etiqueta">Foto de cómo quedó (opcional)</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <div v-for="(f, k) in form.archivos" :key="k" class="relative">
                                    <img v-if="vistas[k]" :src="vistas[k]" :alt="f.name" class="size-16 rounded-ui border border-line object-cover" />
                                    <span v-else class="flex size-16 items-center justify-center rounded-ui border border-line bg-surface px-1 text-center text-[0.65rem] text-ink-2">{{ f.name.slice(0, 14) }}</span>
                                    <button type="button" class="absolute -top-1.5 -right-1.5 flex size-6 items-center justify-center rounded-full border border-line bg-surface text-ink-2 hover:text-bad" :aria-label="`Quitar ${f.name}`" @click="quitarFoto(k)">
                                        <X class="size-3.5" />
                                    </button>
                                </div>
                                <input :id="`res-fotos-${i.id}`" type="file" accept="image/*,application/pdf" capture="environment" multiple class="sr-only" @change="elegirFotos" />
                                <label :for="`res-fotos-${i.id}`" class="btn btn-secundario min-h-9 cursor-pointer px-3 text-sm"><Camera class="size-4" aria-hidden="true" />Agregar foto</label>
                            </div>
                            <p v-if="errorArchivos" class="mt-1 text-sm text-bad">{{ errorArchivos }}</p>
                        </div>

                        <button type="submit" class="btn btn-primario" :disabled="form.processing">
                            <Girando v-if="form.processing" />{{ form.archivos.length ? `Marcar resuelta y guardar ${form.archivos.length === 1 ? 'la foto' : 'las fotos'}` : 'Marcar resuelta' }}
                        </button>
                    </form>
                </li>
            </ul>
            <Vacio v-else titulo="Sin incidencias">Las diferencias de recepción, surtido y revisión aparecerán aquí.</Vacio>
        </section>

        <PreguntaAbierta class="mt-5">¿Quién aplica las entradas y salidas en el registro externo, y cómo se concilia una incidencia con el inventario interno? ¿Qué foto necesitan para dar por resuelta una incidencia?</PreguntaAbierta>
    </AppLayout>
</template>
