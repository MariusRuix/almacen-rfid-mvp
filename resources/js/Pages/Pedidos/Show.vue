<script setup lang="ts">
import BarridoRfid from '@/Components/BarridoRfid.vue';
import Chip from '@/Components/Chip.vue';
import Girando from '@/Components/Girando.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Resultado from '@/Components/Resultado.vue';
import { useCompartido } from '@/composables/useCompartido';
import { confirmar } from '@/composables/useConfirmar';
import { useLectura } from '@/composables/useLectura';
import { useOcupado } from '@/composables/useOcupado';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosDestino, estadosPedido, estadosTarea } from '@/lib/etiquetas';
import { conSigno, fecha, fechaYHora, n } from '@/lib/formato';
import type { DestinoPedido, PedidoDetalle } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Circle, ExternalLink, Hand, Play, ScanLine, Truck, Wrench } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{ pedido: PedidoDetalle; regla: string }>();
const { esResponsable } = useCompartido();
const opciones = { preserveScroll: true };
const { ocupado, con, es } = useOcupado();
const lectura = useLectura();
const leyendoId = ref<number | null>(null);
const leyendo = (d: DestinoPedido) => leyendoId.value === d.id && lectura.fase.value !== 'inactivo';
const filaVisible = (d: DestinoPedido, i: number) => leyendoId.value !== d.id || lectura.visible(i);
const filaRecien = (d: DestinoPedido, i: number) => leyendoId.value === d.id && lectura.recien(i);
const separadas = (d: DestinoPedido) => d.lineas.reduce((s, l) => s + (l.separada ?? 0), 0);

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
    router.post(`/pedidos/${props.pedido.id}/liberar`, {}, { ...opciones, ...con('liberar') });
}
function marcar(d: DestinoPedido, campo: string, hecha: boolean) {
    router.post(`/pedido-destinos/${d.id}/actividades`, { campo, hecha }, { ...opciones, ...con(`marcar-${d.id}-${campo}`) });
}
const actividadesPendientes = computed(() =>
    props.pedido.destinos
        .map((d) => ({ codigo: d.codigo, faltan: d.actividades.filter((a) => !a.hecha).map((a) => a.etiqueta.toLowerCase()) }))
        .filter((d) => d.faltan.length),
);
function leer(d: DestinoPedido, yaIniciada = false) {
    if (!yaIniciada) {
        leyendoId.value = d.id;
        lectura.iniciar(separadas(d));
    }
    router.post(`/pedido-destinos/${d.id}/leer`, {}, {
        ...opciones,
        ...con(`leer-${d.id}`),
        onSuccess: () => {
            const actual = props.pedido.destinos.find((x) => x.id === d.id);
            lectura.terminar(actual ? actual.lineas.reduce((s, l) => s + (l.leida ?? 0), 0) : 0, actual?.lineas.length ?? 0);
        },
        onError: lectura.cancelar,
    });
}
function corregir(d: DestinoPedido) {
    leyendoId.value = d.id;
    lectura.iniciar(separadas(d));
    router.post(`/pedido-destinos/${d.id}/corregir`, {}, { ...opciones, ...con(`leer-${d.id}`), onSuccess: () => leer(d, true), onError: lectura.cancelar });
}
async function aceptar(d: DestinoPedido) {
    const difs = d.lineas
        .filter((l) => dif(l))
        .map((l) => `${l.producto.sku}: se separaron ${n(l.separada)}, se leyeron ${n(l.leida)} (${conSigno(dif(l))}).`);
    const ok = await confirmar({
        titulo: `¿Aceptar la lectura de ${d.codigo}?`,
        mensaje: 'Lo leído queda como cantidad final de este destino.',
        detalle: [...difs, `Se ${difs.length === 1 ? 'crea 1 incidencia' : `crean ${difs.length} incidencias`} para dar seguimiento.`],
        aceptar: 'Aceptar lectura',
    });
    if (ok) router.post(`/pedido-destinos/${d.id}/aceptar`, {}, { ...opciones, ...con(`aceptar-${d.id}`) });
}

// Revisión del bulto sin lector: se captura lo contado por SKU
const contandoId = ref<number | null>(null);
const contado = reactive<Record<number, number | null>>({});
function abrirConteo(d: DestinoPedido) {
    contandoId.value = d.id;
    for (const l of d.lineas) contado[l.id] = null;
}
const conteoCompleto = (d: DestinoPedido) => d.lineas.every((l) => typeof contado[l.id] === 'number' && (contado[l.id] as number) >= 0);
function registrarConteo(d: DestinoPedido) {
    const lineas = Object.fromEntries(d.lineas.map((l) => [l.id, contado[l.id]]));
    router.post(`/pedido-destinos/${d.id}/contar`, { lineas }, { ...opciones, ...con(`contar-${d.id}`), onSuccess: () => (contandoId.value = null) });
}

const salida = useForm({ transportista: 'Transportes del Bajío (ficticio)', placas: '', sello: '' });
const piezasSalida = computed(() => props.pedido.destinos.reduce((s, d) => s + d.lineas.reduce((t, l) => t + (l.separada ?? 0), 0), 0));
async function despachar() {
    const ok = await confirmar({
        titulo: `¿Autorizar la salida de ${props.pedido.folio}?`,
        detalle: [
            `${n(piezasSalida.value)} piezas en ${props.pedido.destinos.length} destinos salen del inventario.`,
            `${salida.transportista} · placas ${salida.placas.toUpperCase()} · sello ${salida.sello.toUpperCase()}.`,
            'Queda registrada en el historial y ya no se puede deshacer.',
        ],
        aceptar: 'Autorizar salida',
    });
    if (ok) salida.post(`/pedidos/${props.pedido.id}/despachar`, opciones);
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
            <button v-if="pedido.estado === 'nuevo' && esResponsable" type="button" class="btn btn-primario" :disabled="!!ocupado" @click="liberar">
                <Girando v-if="es('liberar')" /><Play v-else class="size-4" />{{ es('liberar') ? 'Generando instrucciones…' : 'Liberar a surtido' }}
            </button>
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
                    <ul v-if="pedido.estado === 'despachado' || d.estado === 'pendiente'" class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <li v-for="a in d.actividades" :key="a.campo" class="flex items-center gap-1" :class="a.hecha ? 'text-ok' : 'text-ink-3'">
                            <component :is="a.hecha ? Check : Circle" class="size-3.5" aria-hidden="true" />{{ a.etiqueta }}
                        </li>
                    </ul>
                    <!-- Ya separado: las actividades se marcan aquí sin afectar cantidades ni la lectura RFID. -->
                    <fieldset v-else class="flex flex-wrap gap-2">
                        <legend class="sr-only">Preparación de {{ d.codigo }}</legend>
                        <label
                            v-for="a in d.actividades"
                            :key="a.campo"
                            class="flex min-h-9 cursor-pointer items-center gap-2 rounded-ui border px-2.5 text-xs font-semibold"
                            :class="a.hecha ? 'border-transparent bg-ok-bg text-ok' : 'border-warn/40 bg-warn-bg text-warn'"
                        >
                            <Girando v-if="es(`marcar-${d.id}-${a.campo}`)" />
                            <input v-else type="checkbox" class="size-4 accent-[var(--ok)]" :checked="a.hecha" :disabled="!!ocupado" @change="marcar(d, a.campo, ($event.target as HTMLInputElement).checked)" />
                            {{ a.etiqueta }}
                        </label>
                    </fieldset>
                </header>

                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead><tr><th>SKU</th><th class="der">Pedido</th><th class="der">Separado</th><th class="der">{{ d.verificacion === 'manual' ? 'Contado' : 'Leído RFID' }}</th><th class="der">Dif.</th></tr></thead>
                        <tbody>
                            <tr v-for="(l, i) in d.lineas" :key="l.id" :class="{ 'bg-bad-bg/50': dif(l) && filaVisible(d, i), 'fila-recien': filaRecien(d, i) }">
                                <td><IdSegmentado :valor="l.producto.sku" tipo="sku" /></td>
                                <td class="der">{{ n(l.cantidad) }}</td>
                                <td class="der" :class="l.separada !== null && l.separada !== l.cantidad ? 'font-semibold text-warn' : ''">{{ n(l.separada) }}</td>
                                <td class="der" :class="{ 'celda-oculta': !filaVisible(d, i) }">{{ n(l.leida) }}</td>
                                <td class="der font-semibold" :class="[dif(l) === null ? 'text-ink-3' : dif(l) === 0 ? 'text-ok' : 'text-bad', { 'celda-oculta': !filaVisible(d, i) }]">{{ conSigno(dif(l)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="enRevision" class="mt-auto space-y-3 border-t border-line px-5 py-4">
                    <BarridoRfid v-if="leyendo(d)" :contador="lectura.contador.value" :esperado="separadas(d)" titulo="Leyendo el bulto" />
                    <Resultado v-else :estado="resultadoDestino(d).estado" :titulo="resultadoDestino(d).titulo">
                        <template v-if="d.estado === 'verificado'">
                            Verificado {{ d.verificacion === 'manual' ? 'con conteo a mano' : 'con RFID' }}{{ d.verifico ? ` por ${d.verifico}` : '' }} · {{ fechaYHora(d.verificado_at) }}
                        </template>
                        <template v-else-if="d.estado === 'con_diferencia'">
                            Lo {{ d.verificacion === 'manual' ? 'contado' : 'leído' }} en el bulto no coincide con lo que se separó.
                        </template>
                        <template v-else>Pasa el lector por el bulto de este destino o cuéntalo a mano.</template>
                    </Resultado>
                    <form v-if="contandoId === d.id" class="space-y-3 rounded-ui border border-line bg-surface-2 p-4" @submit.prevent="registrarConteo(d)">
                        <p class="font-semibold">Conteo a mano del bulto</p>
                        <div v-for="l in d.lineas" :key="l.id" class="flex items-center justify-between gap-3">
                            <label :for="`contado-${l.id}`" class="min-w-0">
                                <IdSegmentado :valor="l.producto.sku" tipo="sku" />
                                <span class="block text-xs text-ink-3">se separaron {{ n(l.separada) }}</span>
                            </label>
                            <input :id="`contado-${l.id}`" v-model.number="contado[l.id]" type="number" min="0" inputmode="numeric" class="campo num w-28 text-right" placeholder="Contadas" />
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="submit" class="btn btn-primario" :disabled="!conteoCompleto(d) || !!ocupado">
                                <Girando v-if="es(`contar-${d.id}`)" />Registrar conteo
                            </button>
                            <button type="button" class="btn btn-secundario" @click="contandoId = null">Cancelar</button>
                        </div>
                    </form>
                    <div v-else class="flex flex-wrap gap-2">
                        <button v-if="d.estado === 'separado' && !leyendo(d)" type="button" class="btn btn-primario" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="leer(d)">
                            <ScanLine class="size-4" />Leer bulto
                        </button>
                        <button
                            v-if="['separado', 'con_diferencia'].includes(d.estado) && !leyendo(d)"
                            type="button"
                            class="btn btn-secundario"
                            :disabled="!!ocupado || lectura.fase.value !== 'inactivo'"
                            @click="abrirConteo(d)"
                        >
                            <Hand class="size-4" />Contar a mano
                        </button>
                        <template v-if="d.estado === 'con_diferencia' && !leyendo(d)">
                            <button type="button" class="btn btn-primario" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="corregir(d)">
                                <Wrench class="size-4" />Corregí el bulto, volver a leer
                            </button>
                            <button v-if="esResponsable" type="button" class="btn btn-secundario" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="aceptar(d)">
                                <Girando v-if="es(`aceptar-${d.id}`)" />Aceptar lectura
                            </button>
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
            <div v-else-if="actividadesPendientes.length" class="px-5 pt-5">
                <Resultado estado="parcial" titulo="Falta preparación antes de la salida">
                    <ul class="mt-1 space-y-0.5">
                        <li v-for="p in actividadesPendientes" :key="p.codigo"><b class="font-id">{{ p.codigo }}</b>: {{ p.faltan.join(', ') }}</li>
                    </ul>
                    <p class="mt-1">Márcalas en la tarjeta de cada destino, arriba.</p>
                </Resultado>
            </div>
            <form v-if="!pedido.salida && esResponsable" class="grid gap-4 px-5 py-5 md:grid-cols-[1.5fr_1fr_1fr_auto] md:items-end" @submit.prevent="despachar">
                <div>
                    <label for="transportista" class="etiqueta">Transportista</label>
                    <input id="transportista" v-model="salida.transportista" class="campo" required />
                </div>
                <div>
                    <label for="placas" class="etiqueta">Placas</label>
                    <input id="placas" v-model="salida.placas" class="campo font-id uppercase placeholder:normal-case" placeholder="ABC-123-D" required />
                    <p v-if="salida.errors.placas" class="mt-1 text-sm text-bad">{{ salida.errors.placas }}</p>
                </div>
                <div>
                    <label for="sello" class="etiqueta">Sello</label>
                    <input id="sello" v-model="salida.sello" class="campo font-id uppercase placeholder:normal-case" placeholder="S-000000" required />
                </div>
                <button type="submit" class="btn btn-primario" :disabled="salida.processing || actividadesPendientes.length > 0">
                    <Girando v-if="salida.processing" /><Truck v-else class="size-4" />{{ salida.processing ? 'Registrando salida…' : 'Autorizar salida' }}
                </button>
            </form>
            <p v-else-if="!pedido.salida" class="px-5 py-5 text-ink-2">La responsable operativa autoriza la salida.</p>
        </section>

        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <PreguntaAbierta>Al preparar, ¿cómo reparten las tareas entre el personal? ¿Qué pasa con un destino si falta un accesorio?</PreguntaAbierta>
            <PreguntaAbierta>Al confirmar la salida, ¿quién autoriza, qué evidencia necesitan ante una reclamación y quién actualiza los registros externos?</PreguntaAbierta>
        </div>
    </AppLayout>
</template>
