<script setup lang="ts">
import Girando from '@/Components/Girando.vue';
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Vacio from '@/Components/Vacio.vue';
import { useCompartido } from '@/composables/useCompartido';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosIncidencia, tiposIncidencia } from '@/lib/etiquetas';
import { fechaYHora } from '@/lib/formato';
import type { Producto } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Inc {
    id: number; folio: string; tipo: string; estado: string; cantidad: number; descripcion: string | null; resolucion: string | null;
    origen_folio: string | null; origen_type: string | null; origen_id: number | null; producto: Producto; pedimento: string | null;
    destino: string | null; reporto: string | null; resolvio: string | null; fecha: string; puede_liberar: boolean;
}
defineProps<{ incidencias: Inc[]; ubicaciones: { id: number; codigo: string }[] }>();

const { esResponsable } = useCompartido();
const abierta = ref<number | null>(null);
const form = useForm<{ resolucion: string; liberar_a: number | null }>({ resolucion: '', liberar_a: null });

function abrir(i: Inc) {
    abierta.value = abierta.value === i.id ? null : i.id;
    form.reset();
    form.clearErrors();
    if (i.puede_liberar) form.resolucion = `Se completó el accesorio (${i.producto.accesorio}) y se liberan las piezas.`;
}
function resolver(i: Inc) {
    form.post(`/incidencias/${i.id}/resolver`, { preserveScroll: true, onSuccess: () => (abierta.value = null) });
}
const origenHref = (i: Inc) => (i.origen_type === 'recepcion' ? `/recepciones/${i.origen_id}` : i.origen_type === 'pedido' ? `/pedidos/${i.origen_id}` : null);
</script>

<template>
    <Head title="Incidencias" />
    <AppLayout>
        <Encabezado titulo="Incidencias" descripcion="Toda diferencia queda registrada con su origen, quién la reportó y cómo se resolvió." />

        <section class="panel">
            <ul v-if="incidencias.length" class="divide-y divide-line">
                <li v-for="i in incidencias" :key="i.id" class="px-5 py-4">
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

                    <form v-if="abierta === i.id" class="mt-4 grid gap-3 rounded-ui bg-surface-2 p-4 md:grid-cols-[1fr_auto_auto] md:items-end" @submit.prevent="resolver(i)">
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
                        <button type="submit" class="btn btn-primario" :disabled="form.processing"><Girando v-if="form.processing" />Marcar resuelta</button>
                    </form>
                </li>
            </ul>
            <Vacio v-else titulo="Sin incidencias">Las diferencias de recepción, surtido y revisión aparecerán aquí.</Vacio>
        </section>

        <PreguntaAbierta class="mt-5">¿Quién aplica las entradas y salidas en el registro externo, y cómo se concilia una incidencia con el inventario interno?</PreguntaAbierta>
    </AppLayout>
</template>
