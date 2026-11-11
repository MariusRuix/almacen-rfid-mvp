<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosExistencia } from '@/lib/etiquetas';
import { fecha, n } from '@/lib/formato';
import type { Pedimento, Producto, UbicacionCorta } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Search } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface Grupo {
    producto: Producto;
    total: number;
    por_estado: { disponible: number; en_preparacion: number; incidencia: number };
    pedimentos: number;
    detalle: { id: number; pedimento: Pedimento; ubicacion: UbicacionCorta; estado: string; cantidad: number }[];
}

const props = defineProps<{
    grupos: Grupo[];
    filtros: { buscar?: string; pedimento_id?: string; ubicacion_id?: string; estado?: string };
    totales: { piezas: number; skus: number; ubicaciones: number };
    pedimentos: Pedimento[];
    ubicaciones: UbicacionCorta[];
}>();

const f = reactive({
    buscar: props.filtros.buscar ?? '',
    pedimento_id: props.filtros.pedimento_id ?? '',
    ubicacion_id: props.filtros.ubicacion_id ?? '',
    estado: props.filtros.estado ?? '',
});

let espera: ReturnType<typeof setTimeout> | undefined;
watch(f, () => {
    clearTimeout(espera);
    espera = setTimeout(() => {
        const q = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''));
        router.get('/inventario', q, { preserveState: true, preserveScroll: true, replace: true });
    }, 250);
});

const abiertos = ref<Set<number>>(new Set(props.grupos.filter((g) => g.pedimentos > 1).map((g) => g.producto.id)));
function alternar(id: number) {
    const s = new Set(abiertos.value);
    s.has(id) ? s.delete(id) : s.add(id);
    abiertos.value = s;
}
const hayFiltros = () => Object.values(f).some((v) => v !== '');
function limpiar() {
    Object.assign(f, { buscar: '', pedimento_id: '', ubicacion_id: '', estado: '' });
}
</script>

<template>
    <Head title="Inventario" />
    <AppLayout>
        <Encabezado titulo="Inventario" descripcion="Cada existencia es un SKU, en un pedimento, en una ubicación y en un estado.">
            <template #meta>
                <p class="mt-2 text-sm text-ink-2">
                    <b class="num text-ink">{{ n(totales.piezas) }}</b> piezas · <b class="num text-ink">{{ totales.skus }}</b> SKU ·
                    <b class="num text-ink">{{ totales.ubicaciones }}</b> ubicaciones
                </p>
            </template>
            <Link href="/inventario/mapa" class="btn btn-secundario">Ver mapa</Link>
        </Encabezado>

        <div class="panel mb-4 grid gap-3 p-4 md:grid-cols-[1.5fr_1.3fr_1fr_1fr_auto] md:items-end">
            <div>
                <label for="buscar" class="etiqueta">SKU, descripción o referencia</label>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-3" aria-hidden="true" />
                    <input id="buscar" v-model="f.buscar" type="search" class="campo pl-9" placeholder="CAM-OXF, R-102…" />
                </div>
            </div>
            <div>
                <label for="pedimento" class="etiqueta">Pedimento</label>
                <select id="pedimento" v-model="f.pedimento_id" class="campo font-id text-sm">
                    <option value="">Todos</option>
                    <option v-for="p in pedimentos" :key="p.id" :value="String(p.id)">{{ p.numero }} · {{ fecha(p.fecha_pago) }}</option>
                </select>
            </div>
            <div>
                <label for="ubicacion" class="etiqueta">Ubicación</label>
                <select id="ubicacion" v-model="f.ubicacion_id" class="campo font-id text-sm">
                    <option value="">Todas</option>
                    <option v-for="u in ubicaciones" :key="u.id" :value="String(u.id)">{{ u.codigo }}</option>
                </select>
            </div>
            <div>
                <label for="estado" class="etiqueta">Estado</label>
                <select id="estado" v-model="f.estado" class="campo">
                    <option value="">Todos</option>
                    <option value="disponible">Almacenado</option>
                    <option value="en_preparacion">En preparación</option>
                    <option value="incidencia">Con incidencia</option>
                </select>
            </div>
            <button type="button" class="btn btn-secundario" :disabled="!hayFiltros()" @click="limpiar">Limpiar</button>
        </div>

        <section class="panel overflow-x-auto">
            <table v-if="grupos.length" class="tabla">
                <thead>
                    <tr>
                        <th class="w-10"><span class="sr-only">Desglose</span></th>
                        <th>SKU</th>
                        <th class="der">Almacenado</th>
                        <th class="der">En preparación</th>
                        <th class="der">Con incidencia</th>
                        <th class="der">Total</th>
                        <th class="der">Pedimentos</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="g in grupos" :key="g.producto.id">
                        <tr class="cursor-pointer" @click="alternar(g.producto.id)">
                            <td>
                                <button type="button" class="rounded p-1 text-ink-3 hover:text-ink" :aria-expanded="abiertos.has(g.producto.id)" :aria-label="`Desglose de ${g.producto.sku}`" @click.stop="alternar(g.producto.id)">
                                    <ChevronDown class="size-4 transition-transform duration-200" :class="{ '-rotate-90': !abiertos.has(g.producto.id) }" />
                                </button>
                            </td>
                            <td>
                                <IdSegmentado :valor="g.producto.sku" tipo="sku" />
                                <p class="text-xs text-ink-2">{{ g.producto.descripcion }} · ref. {{ g.producto.referencia }}</p>
                            </td>
                            <td class="der">{{ n(g.por_estado.disponible) }}</td>
                            <td class="der" :class="g.por_estado.en_preparacion ? 'text-info' : 'text-ink-3'">{{ n(g.por_estado.en_preparacion) }}</td>
                            <td class="der" :class="g.por_estado.incidencia ? 'font-semibold text-bad' : 'text-ink-3'">{{ n(g.por_estado.incidencia) }}</td>
                            <td class="der font-bold">{{ n(g.total) }}</td>
                            <td class="der">{{ g.pedimentos }}</td>
                        </tr>
                        <tr v-if="abiertos.has(g.producto.id)" class="bg-surface-2">
                            <td />
                            <td colspan="6" class="!py-2">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-xs text-ink-3">
                                        <tr><th class="py-1 font-semibold">Pedimento</th><th class="py-1 font-semibold">Pago</th><th class="py-1 font-semibold">Ubicación</th><th class="py-1 font-semibold">Estado</th><th class="py-1 text-right font-semibold">Piezas</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="d in g.detalle" :key="d.id" class="border-t border-line">
                                            <td class="py-1.5"><IdSegmentado :valor="d.pedimento.numero" tipo="pedimento" tam="sm" /></td>
                                            <td class="py-1.5 text-ink-2">{{ fecha(d.pedimento.fecha_pago) }}</td>
                                            <td class="py-1.5"><IdSegmentado :valor="d.ubicacion.codigo" tipo="ubicacion" tam="sm" /></td>
                                            <td class="py-1.5"><Chip :valor="d.estado" :mapa="estadosExistencia" /></td>
                                            <td class="num py-1.5 text-right font-semibold">{{ n(d.cantidad) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <Vacio v-else titulo="Nada coincide con los filtros">Prueba con otro pedimento o quita algún filtro.</Vacio>
        </section>

        <PreguntaAbierta class="mt-5">Cuando un SKU existe en varios pedimentos, ¿de cuál surten primero? ¿Lo decide la fecha de pago, el propietario o el registro externo?</PreguntaAbierta>
    </AppLayout>
</template>
