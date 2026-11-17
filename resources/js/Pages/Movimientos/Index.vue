<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import Girando from '@/Components/Girando.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosExistencia, tiposMovimiento } from '@/lib/etiquetas';
import { fechaYHora, n } from '@/lib/formato';
import type { Evidencia, FilaMovimiento } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Camera, FileText, X } from 'lucide-vue-next';
import { onBeforeUnmount, reactive, ref, watch } from 'vue';

const props = defineProps<{
    movimientos: { data: FilaMovimiento[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filtros: { tipo?: string; buscar?: string; folio?: string; fotos?: string };
}>();

const f = reactive({ tipo: props.filtros.tipo ?? '', buscar: props.filtros.buscar ?? '', folio: props.filtros.folio ?? '', fotos: !!props.filtros.fotos });
let espera: ReturnType<typeof setTimeout> | undefined;
watch(f, () => {
    clearTimeout(espera);
    espera = setTimeout(() => {
        const q = Object.fromEntries(Object.entries(f).filter(([, v]) => v).map(([k, v]) => [k, v === true ? '1' : v]));
        router.get('/movimientos', q, { preserveState: true, replace: true });
    }, 250);
});
const estado = (e: string | null) => (e ? (estadosExistencia[e]?.[0] ?? e) : '');
const etiquetaPagina = (l: string) => l.replace('&laquo;', '‹').replace('&raquo;', '›').replace('Previous', 'Anterior').replace('Next', 'Siguiente');

// ---------- Panel de fotos del documento ----------
interface Expediente {
    documento: { tipo: string; folio: string; href: string } | null;
    grupos: { titulo: string; evidencias: Evidencia[] }[];
}
const visto = ref<FilaMovimiento | null>(null);
const expediente = ref<Expediente | null>(null);
const cargando = ref(false);
const falla = ref(false);

async function verFotos(m: FilaMovimiento) {
    visto.value = m;
    expediente.value = null;
    falla.value = false;
    cargando.value = true;
    try {
        const r = await fetch(`/movimientos/${m.id}/evidencias`, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error(String(r.status));
        expediente.value = await r.json();
    } catch {
        falla.value = true;
    } finally {
        cargando.value = false;
    }
}
const cerrar = () => (visto.value = null);
const alTeclear = (e: KeyboardEvent) => e.key === 'Escape' && cerrar();
watch(visto, (v) => (v ? addEventListener('keydown', alTeclear) : removeEventListener('keydown', alTeclear)));
onBeforeUnmount(() => removeEventListener('keydown', alTeclear));
</script>

<template>
    <Head title="Historial" />
    <AppLayout>
        <Encabezado titulo="Historial de movimientos" descripcion="Registro que no se edita ni se borra. El inventario se calcula a partir de aquí." />

        <div class="panel mb-4 grid gap-3 p-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
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
            <label for="fotos" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-ui border border-line-strong px-3" :class="f.fotos ? 'bg-info-bg' : 'bg-surface'">
                <input id="fotos" v-model="f.fotos" type="checkbox" class="size-4 accent-[var(--primary)]" />
                <Camera class="size-4 text-ink-2" aria-hidden="true" /><span class="text-sm font-semibold whitespace-nowrap">Solo con fotos</span>
            </label>
        </div>

        <section class="panel overflow-x-auto">
            <table v-if="movimientos.data.length" class="tabla">
                <thead><tr><th>Cuándo</th><th>Movimiento</th><th>SKU</th><th>Pedimento</th><th>De</th><th>A</th><th class="der">Piezas</th><th>Quién</th><th>Fotos</th></tr></thead>
                <tbody>
                    <tr v-for="m in movimientos.data" :key="m.id" :class="{ 'bg-info-bg/60': visto?.id === m.id }">
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
                        <td>
                            <button
                                v-if="m.fotos"
                                type="button"
                                class="inline-flex min-h-8 items-center gap-1.5 rounded-ui border border-line px-2 text-sm font-semibold text-primary hover:border-primary"
                                :aria-label="`Ver ${m.fotos} fotos de ${m.folio}`"
                                @click="verFotos(m)"
                            >
                                <Camera class="size-4" aria-hidden="true" /><span class="num">{{ m.fotos }}</span>
                            </button>
                            <span v-else class="text-ink-3">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else :titulo="f.fotos ? 'Ningún movimiento con fotos' : 'Sin movimientos con estos filtros'">
                {{ f.fotos ? 'Las fotos se toman en recepción, incidencias, bultos y salida.' : 'Quita algún filtro para ver más.' }}
            </Vacio>
        </section>

        <nav v-if="movimientos.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Páginas">
            <template v-for="(l, i) in movimientos.links" :key="i">
                <Link v-if="l.url" :href="l.url" preserve-scroll class="rounded-ui border px-3 py-1.5 text-sm" :class="l.active ? 'border-primary bg-primary text-primary-ink' : 'border-line bg-surface hover:border-line-strong'">{{ etiquetaPagina(l.label) }}</Link>
                <span v-else class="rounded-ui border border-line px-3 py-1.5 text-sm text-ink-3">{{ etiquetaPagina(l.label) }}</span>
            </template>
        </nav>

        <!-- Panel lateral: fotos del documento del movimiento -->
        <Teleport to="body">
            <Transition name="panel">
                <div v-if="visto" class="fixed inset-0 z-40 flex justify-end">
                    <div class="absolute inset-0 bg-black/25" aria-hidden="true" @click="cerrar" />
                    <aside class="cajon relative flex h-full w-full max-w-md flex-col bg-surface" role="dialog" aria-modal="true" :aria-label="`Fotos de ${visto.folio}`">
                        <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-ink-3">{{ tiposMovimiento[visto.tipo] ?? visto.tipo }} · {{ fechaYHora(visto.fecha) }}</p>
                                <p class="mt-0.5 font-display text-xl font-bold">
                                    Fotos de <span class="font-id">{{ expediente?.documento?.folio ?? visto.folio }}</span>
                                </p>
                                <p v-if="expediente?.documento" class="mt-1 text-sm text-ink-2">
                                    Todo lo que se fotografió en {{ expediente.documento.tipo.toLowerCase() === 'pedido' ? 'el pedido' : expediente.documento.tipo.toLowerCase() === 'recepción' ? 'la recepción' : 'la incidencia' }}, aunque no sea de este SKU.
                                    <Link :href="expediente.documento.href" class="font-semibold text-primary hover:underline">Abrir {{ expediente.documento.folio }}</Link>
                                </p>
                            </div>
                            <button type="button" class="flex size-10 flex-none items-center justify-center rounded-ui text-ink-2 hover:bg-surface-2" aria-label="Cerrar" @click="cerrar"><X class="size-5" /></button>
                        </header>

                        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                            <p v-if="cargando" class="flex items-center gap-2 text-ink-2"><Girando />Cargando fotos…</p>
                            <p v-else-if="falla" class="text-bad">No se pudieron cargar las fotos. Vuelve a intentarlo.</p>
                            <p v-else-if="expediente && !expediente.grupos.length" class="text-ink-3">Este documento no tiene fotos.</p>
                            <section v-for="g in expediente?.grupos ?? []" :key="g.titulo">
                                <h3 class="mb-2 font-bold">{{ g.titulo }} <span class="num text-sm font-normal text-ink-3">· {{ g.evidencias.length }}</span></h3>
                                <ul class="grid grid-cols-3 gap-2">
                                    <li v-for="e in g.evidencias" :key="e.id">
                                        <a :href="e.url" target="_blank" class="block aspect-square overflow-hidden rounded-ui border border-line bg-surface-2 hover:border-ink-3">
                                            <img v-if="e.es_imagen" :src="e.url" :alt="e.nombre" class="size-full object-cover" loading="lazy" />
                                            <span v-else class="flex size-full flex-col items-center justify-center gap-1 px-1 text-center text-xs text-ink-2"><FileText class="size-7" />{{ e.nombre.slice(0, 22) }}</span>
                                        </a>
                                        <p class="mt-1 truncate text-xs text-ink-3" :title="`${e.usuario ?? ''} · ${fechaYHora(e.fecha)}${e.nota ? ' · ' + e.nota : ''}`">
                                            {{ e.usuario ?? '—' }} · {{ fechaYHora(e.fecha) }}<template v-if="e.nota"> · {{ e.nota }}</template>
                                        </p>
                                    </li>
                                </ul>
                            </section>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.cajon {
    box-shadow: -12px 0 32px rgb(15 22 30 / 0.18);
}
.panel-enter-active,
.panel-leave-active {
    transition: opacity 220ms cubic-bezier(0.16, 1, 0.3, 1);
}
.panel-enter-active .cajon,
.panel-leave-active .cajon {
    transition: transform 260ms cubic-bezier(0.16, 1, 0.3, 1);
}
.panel-enter-from,
.panel-leave-to {
    opacity: 0;
}
.panel-enter-from .cajon,
.panel-leave-to .cajon {
    transform: translateX(24px);
}
@media (prefers-reduced-motion: reduce) {
    .panel-enter-active,
    .panel-leave-active,
    .panel-enter-active .cajon,
    .panel-leave-active .cajon {
        transition: none;
    }
}
</style>
