<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosPedido, estadosRecepcion, tiposIncidencia, tiposMovimiento } from '@/lib/etiquetas';
import { fecha, fechaYHora, n } from '@/lib/formato';
import type { FilaMovimiento, Pedimento, Producto } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    existencias: { disponible: number; en_preparacion: number; incidencia: number };
    porPedimento: { pedimento: Pedimento; piezas: number; skus: number }[];
    recepciones: { id: number; folio: string; estado: string; pedimento: Pedimento; piezas: number }[];
    pedidos: { id: number; folio: string; estado: string; destinos: number; piezas: number; fecha_compromiso: string }[];
    incidencias: { id: number; folio: string; tipo: string; cantidad: number; origen_folio: string | null; producto: Producto }[];
    ultimosMovimientos: FilaMovimiento[];
}>();

const total = computed(() => props.existencias.disponible + props.existencias.en_preparacion + props.existencias.incidencia);
const pct = (v: number) => (total.value ? (v / total.value) * 100 : 0);

const etapas = computed(() => {
    const p = props.pedidos;
    return [
        { nombre: 'Recepción', detalle: 'Esperado contra recibido', valor: props.recepciones.filter((r) => r.estado !== 'cerrada').length, unidad: 'abiertas', href: '/recepciones' },
        { nombre: 'Ubicación', detalle: 'Rack y pedimento', valor: props.recepciones.filter((r) => r.estado === 'cerrada').length, unidad: 'por ubicar', href: '/recepciones' },
        { nombre: 'Surtido', detalle: 'Toma y separación por destino', valor: p.filter((x) => ['nuevo', 'en_surtido', 'en_separacion'].includes(x.estado)).length, unidad: 'pedidos', href: '/pedidos' },
        { nombre: 'Revisión', detalle: 'Lectura RFID por destino', valor: p.filter((x) => x.estado === 'en_revision').length, unidad: 'pedidos', href: '/pedidos' },
        { nombre: 'Salida', detalle: 'Autorización e historial', valor: p.filter((x) => x.estado === 'listo').length, unidad: 'listos', href: '/pedidos' },
    ];
});
</script>

<template>
    <Head title="Tablero" />
    <AppLayout>
        <Encabezado titulo="Tablero" descripcion="Dónde está el producto, a qué pedimento pertenece y qué falta por confirmar." />

        <!-- Flujo del almacén -->
        <ol class="panel mb-6 grid overflow-hidden sm:grid-cols-5" aria-label="Flujo del almacén">
            <li v-for="(e, i) in etapas" :key="e.nombre" class="relative border-line not-last:border-b sm:not-last:border-r sm:not-last:border-b-0">
                <Link :href="e.href" class="flex h-full flex-col gap-1 px-4 py-4 hover:bg-surface-2">
                    <span class="flex items-center gap-2 text-sm font-semibold text-ink-2">
                        <span class="font-id text-xs text-ink-3">{{ i + 1 }}</span>{{ e.nombre }}
                    </span>
                    <span class="flex items-baseline gap-1.5">
                        <span class="num font-display text-3xl font-bold" :class="e.valor ? 'text-ink' : 'text-ink-3'">{{ e.valor }}</span>
                        <span class="text-sm text-ink-2">{{ e.unidad }}</span>
                    </span>
                    <span class="text-xs text-ink-3">{{ e.detalle }}</span>
                </Link>
            </li>
        </ol>

        <div class="grid gap-6 xl:grid-cols-[1.35fr_1fr]">
            <section class="panel min-w-0">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
                    <h2 class="text-lg font-bold">Existencias por pedimento</h2>
                    <p class="num text-sm text-ink-2">{{ n(total) }} piezas en almacén</p>
                </div>
                <div class="px-5 pt-4">
                    <div class="flex h-2.5 overflow-hidden rounded-full bg-surface-2" role="img" :aria-label="`Almacenado ${n(existencias.disponible)}, en preparación ${n(existencias.en_preparacion)}, con incidencia ${n(existencias.incidencia)}`">
                        <span class="bg-ok" :style="{ width: pct(existencias.disponible) + '%' }" />
                        <span class="bg-info" :style="{ width: pct(existencias.en_preparacion) + '%' }" />
                        <span class="bg-bad" :style="{ width: pct(existencias.incidencia) + '%' }" />
                    </div>
                    <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                        <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-ok" /><dt class="text-ink-2">Almacenado</dt><dd class="num font-semibold">{{ n(existencias.disponible) }}</dd></div>
                        <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-info" /><dt class="text-ink-2">En preparación</dt><dd class="num font-semibold">{{ n(existencias.en_preparacion) }}</dd></div>
                        <div class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-bad" /><dt class="text-ink-2">Con incidencia</dt><dd class="num font-semibold">{{ n(existencias.incidencia) }}</dd></div>
                    </dl>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="tabla">
                        <thead>
                            <tr><th>Pedimento</th><th>Contenedor</th><th>Pago</th><th class="der">SKU</th><th class="der">Piezas</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in porPedimento" :key="p.pedimento.id">
                                <td><Link :href="`/inventario?pedimento_id=${p.pedimento.id}`" class="hover:underline"><IdSegmentado :valor="p.pedimento.numero" tipo="pedimento" /></Link></td>
                                <td><IdSegmentado :valor="p.pedimento.contenedor" tipo="contenedor" tam="sm" /></td>
                                <td class="text-ink-2">{{ fecha(p.pedimento.fecha_pago) }}</td>
                                <td class="der">{{ p.skus }}</td>
                                <td class="der font-semibold">{{ n(p.piezas) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="min-w-0 space-y-6">
                <section class="panel">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="text-lg font-bold">En curso</h2>
                    </div>
                    <ul class="divide-y divide-line">
                        <li v-for="r in recepciones" :key="'r' + r.id">
                            <Link :href="`/recepciones/${r.id}`" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-2">
                                <span class="w-24 font-id text-sm font-semibold">{{ r.folio }}</span>
                                <span class="flex-1 text-sm text-ink-2">Recepción · <span class="num">{{ n(r.piezas) }}</span> pz esperadas</span>
                                <Chip :valor="r.estado" :mapa="estadosRecepcion" />
                            </Link>
                        </li>
                        <li v-for="p in pedidos" :key="'p' + p.id">
                            <Link :href="`/pedidos/${p.id}`" class="flex items-center gap-3 px-5 py-3 hover:bg-surface-2">
                                <span class="w-24 font-id text-sm font-semibold">{{ p.folio }}</span>
                                <span class="flex-1 text-sm text-ink-2">{{ p.destinos }} destinos · <span class="num">{{ n(p.piezas) }}</span> pz · {{ fecha(p.fecha_compromiso) }}</span>
                                <Chip :valor="p.estado" :mapa="estadosPedido" />
                            </Link>
                        </li>
                    </ul>
                    <Vacio v-if="!recepciones.length && !pedidos.length" titulo="Sin trabajo en curso">Las recepciones y pedidos abiertos aparecerán aquí.</Vacio>
                </section>

                <section class="panel">
                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="text-lg font-bold">Incidencias abiertas</h2>
                        <Link href="/incidencias" class="flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Ver todas <ArrowRight class="size-4" /></Link>
                    </div>
                    <ul v-if="incidencias.length" class="divide-y divide-line">
                        <li v-for="i in incidencias" :key="i.id" class="flex items-center gap-3 px-5 py-3 text-sm">
                            <span class="w-20 font-id font-semibold">{{ i.folio }}</span>
                            <span class="flex-1"><span class="font-semibold text-bad">{{ tiposIncidencia[i.tipo] }}</span> · <span class="num">{{ i.cantidad }}</span> pz de <IdSegmentado :valor="i.producto.sku" tipo="sku" tam="sm" /></span>
                            <span class="font-id text-xs text-ink-3">{{ i.origen_folio }}</span>
                        </li>
                    </ul>
                    <Vacio v-else titulo="Sin incidencias abiertas">Las diferencias de recepción, surtido y revisión se registran aquí.</Vacio>
                </section>
            </div>
        </div>

        <section class="panel mt-6">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 class="text-lg font-bold">Últimos movimientos</h2>
                <Link href="/movimientos" class="flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Historial completo <ArrowRight class="size-4" /></Link>
            </div>
            <div class="overflow-x-auto">
                <table class="tabla">
                    <thead><tr><th>Cuándo</th><th>Movimiento</th><th>SKU</th><th>De</th><th>A</th><th class="der">Piezas</th><th>Quién</th></tr></thead>
                    <tbody>
                        <tr v-for="m in ultimosMovimientos" :key="m.id">
                            <td class="whitespace-nowrap text-ink-2">{{ fechaYHora(m.fecha) }}</td>
                            <td class="font-semibold">{{ tiposMovimiento[m.tipo] ?? m.tipo }} <span class="font-id text-xs font-normal text-ink-3">{{ m.folio }}</span></td>
                            <td><IdSegmentado :valor="m.producto.sku" tipo="sku" tam="sm" /></td>
                            <td><IdSegmentado v-if="m.origen" :valor="m.origen" tipo="ubicacion" tam="sm" /><span v-else class="text-ink-3">Entrada</span></td>
                            <td><IdSegmentado v-if="m.destino" :valor="m.destino" tipo="ubicacion" tam="sm" /><span v-else class="text-ink-3">Salida</span></td>
                            <td class="der font-semibold">{{ n(m.cantidad) }}</td>
                            <td class="text-ink-2">{{ m.usuario ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
