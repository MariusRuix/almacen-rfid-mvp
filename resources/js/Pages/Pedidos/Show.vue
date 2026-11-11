<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Resultado from '@/Components/Resultado.vue';
import { useCompartido } from '@/composables/useCompartido';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosDestino, estadosPedido, estadosTarea } from '@/lib/etiquetas';
import { conSigno, fecha, fechaYHora, n } from '@/lib/formato';
import type { DestinoPedido, PedidoDetalle } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Circle, ExternalLink, Play, ScanLine, Truck, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ pedido: PedidoDetalle; regla: string }>();
const { esResponsable } = useCompartido();
const opciones = { preserveScroll: true };

const pasos = [
    { estado: 'nuevo', nombre: 'Recibido' },
    { estado: 'en_surtido', nombre: 'Toma' },
    { estado: 'en_separacion', nombre: 'Separación' },
    { estado: 'en_revision', nombre: 'Revisión' },
    { estado: 'listo', nombre: 'Listo' },
    { estado: 'despachado', nombre: 'Salida' },
];
const pasoActual = computed(() => pasos.findIndex((p) => p.estado === props.pedido.estado));

const totalPiezas = computed(() => props.pedido.destinos.reduce((s, d) => s + d.lineas.reduce((t, l) => t + l.cantidad, 0), 0));
const enRevision = computed(() => ['en_revision', 'listo'].includes(props.pedido.estado));

function resultadoDestino(d: DestinoPedido) {
    if (d.estado === 'verificado') return { estado: 'cuadra' as const, titulo: 'Cuadra' };
    if (d.estado === 'con_diferencia') return { estado: 'diferencia' as const, titulo: 'No cuadra' };
    return { estado: 'pendiente' as const, titulo: d.estado === 'pendiente' ? 'Por separar' : 'Por leer' };
}
const dif = (l: { leida: number | null; separada: number | null }) => (l.leida === null || l.separada === null ? null : l.leida - l.separada);

function liberar() {
    router.post(`/pedidos/${props.pedido.id}/liberar`, {}, opciones);
}
function leer(d: DestinoPedido) {
    router.post(`/pedido-destinos/${d.id}/leer`, {}, opciones);
}
function corregir(d: DestinoPedido) {
    router.post(`/pedido-destinos/${d.id}/corregir`, {}, { ...opciones, onSuccess: () => leer(d) });
}
function aceptar(d: DestinoPedido) {
    if (window.confirm(`Se tomará lo leído como cantidad final de ${d.codigo} y la diferencia quedará como incidencia. ¿Continuar?`)) {
        router.post(`/pedido-destinos/${d.id}/aceptar`, {}, opciones);
    }
}

const salida = useForm({ transportista: 'Transportes del Bajío (ficticio)', placas: '', sello: '' });
function despachar() {
    salida.post(`/pedidos/${props.pedido.id}/despachar`, opciones);
}
</script>

<template>
    <Head :title="pedido.folio" />
    <AppLayout>
        <Link href="/pedidos" class="mb-2 inline-block text-sm text-ink-2 hover:underline">Pedidos y salida</Link>
        <Encabezado :titulo="`Pedido ${pedido.folio}`">
            <template #meta>
                <p class="mt-2 text-sm text-ink-2">
                    {{ pedido.propietario }} · {{ pedido.destinos.length }} destinos · <b class="num text-ink">{{ n(totalPiezas) }}</b> piezas · compromiso {{ fecha(pedido.fecha_compromiso) }}
                </p>
            </template>
            <Chip :valor="pedido.estado" :mapa="estadosPedido" />
            <button v-if="pedido.estado === 'nuevo' && esResponsable" type="button" class="btn btn-primario" @click="liberar"><Play class="size-4" />Liberar a surtido</button>
            <Link v-if="['en_surtido'].includes(pedido.estado)" :href="`/operador/pedidos/${pedido.id}/surtido`" class="btn btn-secundario"><ExternalLink class="size-4" />Vista del operador</Link>
            <Link v-if="pedido.estado === 'en_separacion'" :href="`/operador/pedidos/${pedido.id}/separacion`" class="btn btn-secundario"><ExternalLink class="size-4" />Separar por destino</Link>
        </Encabezado>

        <!-- Avance del pedido -->
        <ol class="mb-6 flex overflow-x-auto rounded-ui border border-line bg-surface" aria-label="Avance del pedido">
            <li
                v-for="(p, i) in pasos"
                :key="p.estado"
                class="flex min-w-32 flex-1 items-center gap-2 border-line px-4 py-3 text-sm not-last:border-r"
                :class="i < pasoActual ? 'text-ink-2' : i === pasoActual ? 'bg-surface-2 font-bold text-ink' : 'text-ink-3'"
                :aria-current="i === pasoActual ? 'step' : undefined"
            >
                <Check v-if="i < pasoActual" class="size-4 text-ok" aria-hidden="true" />
                <Circle v-else class="size-4" :class="i === pasoActual ? 'fill-marca text-marca' : ''" aria-hidden="true" />
                {{ p.nombre }}
            </li>
        </ol>

        <!-- Instrucciones de toma -->
        <section v-if="pedido.tareas.length" class="panel mb-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
                <h2 class="text-lg font-bold">Instrucciones de toma</h2>
                <p class="text-sm text-ink-2">{{ pedido.tareas.filter((t) => t.estado !== 'pendiente').length }} de {{ pedido.tareas.length }} confirmadas</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th class="der">#</th><th>Ubicación</th><th>SKU</th><th>Pedimento</th><th class="der">Indicadas</th><th class="der">Tomadas</th><th>Estado</th></tr></thead>
                    <tbody>
                        <tr v-for="t in pedido.tareas" :key="t.id">
                            <td class="der text-ink-3">{{ t.orden }}</td>
                            <td><IdSegmentado :valor="t.ubicacion.codigo" tipo="ubicacion" /></td>
                            <td><IdSegmentado :valor="t.producto.sku" tipo="sku" /></td>
                            <td><IdSegmentado :valor="t.pedimento.numero" tipo="pedimento" tam="sm" /> <span class="ml-1 text-xs text-ink-3">{{ fecha(t.pedimento.fecha_pago) }}</span></td>
                            <td class="der">{{ n(t.cantidad) }}</td>
                            <td class="der font-semibold" :class="t.tomada !== null && t.tomada < t.cantidad ? 'text-bad' : ''">{{ n(t.tomada) }}</td>
                            <td><Chip :valor="t.estado" :mapa="estadosTarea" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-5 py-3 text-sm text-ink-2"><b class="text-ink">Regla de la demo:</b> {{ regla }}</p>
        </section>

        <!-- Destinos -->
        <h2 class="mb-3 text-lg font-bold">Destinos</h2>
        <div class="grid gap-5 lg:grid-cols-2">
            <section v-for="d in pedido.destinos" :key="d.id" class="panel flex flex-col">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div>
                        <p class="flex items-center gap-2"><span class="font-id font-semibold">{{ d.codigo }}</span><Chip :valor="d.estado" :mapa="estadosDestino" /></p>
                        <p class="text-sm text-ink-2">{{ d.nombre }}<template v-if="d.bultos"> · {{ d.bultos }} {{ d.bultos === 1 ? 'bulto' : 'bultos' }}</template></p>
                    </div>
                    <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <li v-for="a in d.actividades" :key="a.campo" class="flex items-center gap-1" :class="a.hecha ? 'text-ok' : 'text-ink-3'">
                            <component :is="a.hecha ? Check : Circle" class="size-3.5" aria-hidden="true" />{{ a.etiqueta }}
                        </li>
                    </ul>
                </header>

                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>SKU</th><th class="der">Pedido</th><th class="der">Separado</th><th class="der">Leído RFID</th><th class="der">Dif.</th></tr></thead>
                        <tbody>
                            <tr v-for="l in d.lineas" :key="l.id" :class="{ 'bg-bad-bg/50': dif(l) }">
                                <td><IdSegmentado :valor="l.producto.sku" tipo="sku" /></td>
                                <td class="der">{{ n(l.cantidad) }}</td>
                                <td class="der" :class="l.separada !== null && l.separada !== l.cantidad ? 'font-semibold text-warn' : ''">{{ n(l.separada) }}</td>
                                <td class="der">{{ n(l.leida) }}</td>
                                <td class="der font-semibold" :class="dif(l) === null ? 'text-ink-3' : dif(l) === 0 ? 'text-ok' : 'text-bad'">{{ conSigno(dif(l)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="enRevision" class="mt-auto space-y-3 border-t border-line px-5 py-4">
                    <Resultado :estado="resultadoDestino(d).estado" :titulo="resultadoDestino(d).titulo">
                        <template v-if="d.estado === 'verificado'">Verificado {{ fechaYHora(d.verificado_at) }}</template>
                        <template v-else-if="d.estado === 'con_diferencia'">Lo leído en el bulto no coincide con lo que se separó.</template>
                        <template v-else>Pasa el lector por el bulto de este destino.</template>
                    </Resultado>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="d.estado === 'separado'" type="button" class="btn btn-primario" @click="leer(d)"><ScanLine class="size-4" />Leer bulto</button>
                        <template v-if="d.estado === 'con_diferencia'">
                            <button type="button" class="btn btn-primario" @click="corregir(d)"><Wrench class="size-4" />Corregí el bulto, volver a leer</button>
                            <button v-if="esResponsable" type="button" class="btn btn-secundario" @click="aceptar(d)">Aceptar lectura</button>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- Salida -->
        <section v-if="pedido.estado === 'listo' || pedido.salida" class="panel mt-6">
            <div class="border-b border-line px-5 py-4">
                <h2 class="text-lg font-bold">Salida</h2>
            </div>
            <div v-if="pedido.salida" class="space-y-4 px-5 py-5">
                <Resultado estado="cuadra" :titulo="`Salida ${pedido.salida.folio} registrada`" grande>
                    {{ n(pedido.salida.piezas) }} piezas descontadas del inventario · autorizó {{ pedido.salida.autorizo }} · {{ fechaYHora(pedido.salida.fecha) }}
                </Resultado>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><dt class="text-ink-3">Transportista</dt><dd class="font-semibold">{{ pedido.salida.transportista }}</dd></div>
                    <div><dt class="text-ink-3">Placas</dt><dd class="font-id font-semibold">{{ pedido.salida.placas }}</dd></div>
                    <div><dt class="text-ink-3">Sello</dt><dd class="font-id font-semibold">{{ pedido.salida.sello }}</dd></div>
                </dl>
                <Link :href="`/movimientos?folio=${pedido.salida.folio}`" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Ver la salida en el historial <ExternalLink class="size-4" /></Link>
            </div>
            <form v-else-if="esResponsable" class="grid gap-4 px-5 py-5 md:grid-cols-[1.5fr_1fr_1fr_auto] md:items-end" @submit.prevent="despachar">
                <div>
                    <label for="transportista" class="etiqueta">Transportista</label>
                    <input id="transportista" v-model="salida.transportista" class="campo" required />
                </div>
                <div>
                    <label for="placas" class="etiqueta">Placas</label>
                    <input id="placas" v-model="salida.placas" class="campo font-id uppercase" placeholder="ABC-123-D" required />
                    <p v-if="salida.errors.placas" class="mt-1 text-sm text-bad">{{ salida.errors.placas }}</p>
                </div>
                <div>
                    <label for="sello" class="etiqueta">Sello</label>
                    <input id="sello" v-model="salida.sello" class="campo font-id uppercase" placeholder="S-000000" required />
                </div>
                <button type="submit" class="btn btn-primario" :disabled="salida.processing"><Truck class="size-4" />Autorizar salida</button>
            </form>
            <p v-else class="px-5 py-5 text-ink-2">La responsable operativa autoriza la salida.</p>
        </section>

        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <PreguntaAbierta>Al preparar, ¿cómo reparten las tareas entre el personal? ¿Qué pasa con un destino si falta un accesorio?</PreguntaAbierta>
            <PreguntaAbierta>Al confirmar la salida, ¿quién autoriza, qué evidencia necesitan ante una reclamación y quién actualiza los registros externos?</PreguntaAbierta>
        </div>
    </AppLayout>
</template>
