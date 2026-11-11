<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosExistencia, tiposMovimiento } from '@/lib/etiquetas';
import { fechaYHora, n } from '@/lib/formato';
import type { FilaMovimiento } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps<{
    movimientos: { data: FilaMovimiento[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filtros: { tipo?: string; buscar?: string; folio?: string };
}>();

const f = reactive({ tipo: props.filtros.tipo ?? '', buscar: props.filtros.buscar ?? '', folio: props.filtros.folio ?? '' });
let espera: ReturnType<typeof setTimeout> | undefined;
watch(f, () => {
    clearTimeout(espera);
    espera = setTimeout(() => {
        router.get('/movimientos', Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true, replace: true });
    }, 250);
});
const estado = (e: string | null) => (e ? (estadosExistencia[e]?.[0] ?? e) : '');
const etiquetaPagina = (l: string) => l.replace('&laquo;', '‹').replace('&raquo;', '›').replace('Previous', 'Anterior').replace('Next', 'Siguiente');
</script>

<template>
    <Head title="Historial" />
    <AppLayout>
        <Encabezado titulo="Historial de movimientos" descripcion="Registro que no se edita ni se borra. El inventario se calcula a partir de aquí." />

        <div class="panel mb-4 grid gap-3 p-4 sm:grid-cols-3">
            <div>
                <label for="tipo" class="etiqueta">Movimiento</label>
                <select id="tipo" v-model="f.tipo" class="campo">
                    <option value="">Todos</option>
                    <option v-for="(et, clave) in tiposMovimiento" :key="clave" :value="clave">{{ et }}</option>
                </select>
            </div>
            <div>
                <label for="sku" class="etiqueta">SKU</label>
                <input id="sku" v-model="f.buscar" type="search" class="campo" placeholder="PLA-BAS…" />
            </div>
            <div>
                <label for="folio" class="etiqueta">Folio</label>
                <input id="folio" v-model="f.folio" type="search" class="campo font-id" placeholder="REC-0001, PED-0001, SAL-0001" />
            </div>
        </div>

        <section class="panel overflow-x-auto">
            <table v-if="movimientos.data.length" class="tabla">
                <thead><tr><th>Cuándo</th><th>Movimiento</th><th>SKU</th><th>Pedimento</th><th>De</th><th>A</th><th class="der">Piezas</th><th>Quién</th></tr></thead>
                <tbody>
                    <tr v-for="m in movimientos.data" :key="m.id">
                        <td class="whitespace-nowrap text-ink-2">{{ fechaYHora(m.fecha) }}</td>
                        <td>
                            <p class="font-semibold">{{ tiposMovimiento[m.tipo] ?? m.tipo }}</p>
                            <p class="text-xs text-ink-3"><span class="font-id">{{ m.folio }}</span><template v-if="m.nota"> · {{ m.nota }}</template></p>
                        </td>
                        <td><IdSegmentado :valor="m.producto.sku" tipo="sku" tam="sm" /></td>
                        <td><IdSegmentado :valor="m.pedimento" tipo="pedimento" tam="sm" /></td>
                        <td>
                            <IdSegmentado v-if="m.origen" :valor="m.origen" tipo="ubicacion" tam="sm" /><span v-else class="text-ink-3">Entrada</span>
                            <p class="text-xs text-ink-3">{{ estado(m.estado_origen) }}</p>
                        </td>
                        <td>
                            <IdSegmentado v-if="m.destino" :valor="m.destino" tipo="ubicacion" tam="sm" /><span v-else class="font-semibold text-ink-2">Salió</span>
                            <p class="text-xs text-ink-3">{{ estado(m.estado_destino) }}</p>
                        </td>
                        <td class="der font-semibold">{{ n(m.cantidad) }}</td>
                        <td class="text-ink-2">{{ m.usuario ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else titulo="Sin movimientos con estos filtros">Quita algún filtro para ver más.</Vacio>
        </section>

        <nav v-if="movimientos.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Páginas">
            <template v-for="(l, i) in movimientos.links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-ui border px-3 py-1.5 text-sm" :class="l.active ? 'border-primary bg-primary text-primary-ink' : 'border-line bg-surface hover:border-line-strong'">{{ etiquetaPagina(l.label) }}</Link>
                <span v-else class="rounded-ui border border-line px-3 py-1.5 text-sm text-ink-3">{{ etiquetaPagina(l.label) }}</span>
            </template>
        </nav>
    </AppLayout>
</template>
