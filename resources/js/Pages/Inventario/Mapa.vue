<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import Girando from '@/Components/Girando.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import { useCompartido } from '@/composables/useCompartido';
import { confirmar } from '@/composables/useConfirmar';
import { useOcupado } from '@/composables/useOcupado';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosExistencia, tiposMovimiento, zonas } from '@/lib/etiquetas';
import { fecha, fechaYHora, n } from '@/lib/formato';
import type { FilaMovimiento } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, LayoutGrid, MapPinPlus, Search, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface Contenido {
    existencia_id: number;
    sku: string;
    descripcion: string;
    pedimento: string;
    fecha_pago: string;
    estado: string;
    cantidad: number;
}
interface U {
    id: number;
    codigo: string;
    zona: string;
    capacidad: number;
    activa: boolean;
    piezas: number;
    pedimentos: number;
    incidencia: boolean;
    usada: boolean;
    contenido: Contenido[];
    movimientos: FilaMovimiento[];
}
const props = defineProps<{ ubicaciones: U[] }>();
const { esResponsable } = useCompartido();
const { ocupado, con, es } = useOcupado();
const opciones = { preserveScroll: true };

const ALMACENAJE = ['rack', 'estanteria', 'piso', 'adicional'];
const OPERATIVAS = ['recepcion', 'preparacion', 'incidencia'];

// ---------- Búsqueda: resalta dónde está un SKU o un pedimento ----------
const buscar = ref('');
const coincide = (u: U) => {
    const q = buscar.value.trim().toLowerCase().replace(/\s+/g, ' ');
    if (!q) return true;
    const qd = q.replace(/\D/g, '');
    return u.contenido.some(
        (c) =>
            c.sku.toLowerCase().includes(q) ||
            c.descripcion.toLowerCase().includes(q) ||
            (qd.length >= 4 && c.pedimento.replace(/\D/g, '').includes(qd)),
    );
};
const resumenBusqueda = computed(() => {
    if (!buscar.value.trim()) return null;
    const q = buscar.value.trim().toLowerCase();
    const qd = q.replace(/\D/g, '');
    const hallados = props.ubicaciones.filter(coincide);
    const piezas = hallados.reduce(
        (s, u) =>
            s +
            u.contenido
                .filter((c) => c.sku.toLowerCase().includes(q) || c.descripcion.toLowerCase().includes(q) || (qd.length >= 4 && c.pedimento.replace(/\D/g, '').includes(qd)))
                .reduce((t, c) => t + c.cantidad, 0),
        0,
    );
    return { ubicaciones: hallados.length, piezas };
});

// ---------- Ocupación con significado ----------
type Nivel = 'vacia' | 'baja' | 'media' | 'alta' | 'excedida';
const nivel = (u: U): Nivel => {
    if (!u.piezas) return 'vacia';
    const p = u.piezas / u.capacidad;
    return p > 1 ? 'excedida' : p >= 0.85 ? 'alta' : p >= 0.5 ? 'media' : 'baja';
};
const leyenda: { nivel: Nivel; texto: string }[] = [
    { nivel: 'vacia', texto: 'Vacía' },
    { nivel: 'baja', texto: 'Menos de la mitad' },
    { nivel: 'media', texto: '50 a 85 %' },
    { nivel: 'alta', texto: 'Casi llena' },
    { nivel: 'excedida', texto: 'Excedida' },
];
const pct = (u: U) => Math.round((u.piezas / u.capacidad) * 100);

// ---------- Estructura: pasillos (rack y estantería) y otras zonas ----------
const pasillos = computed(() => {
    const porPasillo: Record<string, U[]> = {};
    for (const u of props.ubicaciones) {
        if (!['rack', 'estanteria'].includes(u.zona)) continue;
        const partes = u.codigo.split('-');
        if (partes.length !== 3) continue;
        (porPasillo[partes[0]] ??= []).push(u);
    }
    return Object.entries(porPasillo).map(([pasillo, us]) => {
        const modulos = [...new Set(us.map((u) => u.codigo.split('-')[1]))].sort();
        const niveles = [...new Set(us.map((u) => u.codigo.split('-')[2]))].sort().reverse();
        const mapa = new Map(us.map((u) => [u.codigo, u]));
        return {
            pasillo,
            tipo: us.every((u) => u.zona === 'estanteria') ? 'Estantería' : 'Rack',
            modulos,
            niveles,
            celda: (m: string, nv: string) => mapa.get(`${pasillo}-${m}-${nv}`),
            piezas: us.reduce((s, u) => s + u.piezas, 0),
            capacidad: us.reduce((s, u) => s + (u.activa ? u.capacidad : 0), 0),
        };
    });
});
const enPasillo = computed(() => new Set(pasillos.value.flatMap((p) => p.modulos.flatMap((m) => p.niveles.map((nv) => p.celda(m, nv)?.id)))));
const otras = computed(() => {
    const grupos: Record<string, U[]> = {};
    for (const u of props.ubicaciones) if (!enPasillo.value.has(u.id)) (grupos[u.zona] ??= []).push(u);
    const orden = ['piso', 'adicional', 'rack', 'estanteria', 'recepcion', 'preparacion', 'incidencia'];
    return Object.entries(grupos).sort((a, b) => orden.indexOf(a[0]) - orden.indexOf(b[0]));
});

// ---------- Panel lateral ----------
const elegidaId = ref<number | null>(null);
const elegida = computed(() => props.ubicaciones.find((u) => u.id === elegidaId.value) ?? null);
const edicion = reactive({ capacidad: 0, zona: '', activa: true });
watch(
    elegida,
    (u) => {
        if (u) Object.assign(edicion, { capacidad: u.capacidad, zona: u.zona, activa: u.activa });
    },
    { immediate: true },
);
const editada = computed(() => !!elegida.value && (edicion.capacidad !== elegida.value.capacidad || edicion.zona !== elegida.value.zona || edicion.activa !== elegida.value.activa));
function guardarUbicacion() {
    if (!elegida.value) return;
    router.put(`/ubicaciones/${elegida.value.id}`, { ...edicion }, { ...opciones, ...con('editar') });
}
async function borrarUbicacion() {
    const u = elegida.value;
    if (!u) return;
    const ok = await confirmar({ titulo: `¿Borrar la ubicación ${u.codigo}?`, mensaje: 'Nunca ha tenido mercancía, así que se puede borrar sin perder historial.', aceptar: 'Borrar', tono: 'peligro' });
    if (ok) router.delete(`/ubicaciones/${u.id}`, { ...opciones, ...con('borrar'), onSuccess: () => (elegidaId.value = null) });
}

const alTeclado = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && elegidaId.value !== null && !document.querySelector('[role=alertdialog]')) elegidaId.value = null;
};
onMounted(() => window.addEventListener('keydown', alTeclado));
onBeforeUnmount(() => window.removeEventListener('keydown', alTeclado));

// Reubicar desde el panel
const reubicando = ref<number | null>(null);
const mover = reactive({ destino_id: null as number | null, cantidad: 0 });
const destinos = computed(() =>
    props.ubicaciones.filter((u) => u.activa && ALMACENAJE.includes(u.zona) && u.id !== elegida.value?.id).sort((a, b) => a.codigo.localeCompare(b.codigo)),
);
function abrirReubicar(c: Contenido) {
    reubicando.value = c.existencia_id;
    mover.cantidad = c.cantidad;
    mover.destino_id = null;
}
function reubicar(c: Contenido) {
    router.post(
        '/ubicaciones/reubicar',
        { existencia_id: c.existencia_id, destino_id: mover.destino_id, cantidad: mover.cantidad },
        { ...opciones, ...con('mover'), onSuccess: () => (reubicando.value = null) },
    );
}
const puedeReubicar = (u: U, c: Contenido) => c.estado === 'disponible' && ALMACENAJE.includes(u.zona);

// ---------- Altas ----------
const panelAlta = ref<'pasillo' | 'ubicacion' | null>(null);
const pasillo = useForm({ pasillo: '', modulos: 4, niveles: 3, capacidad: 600, zona: 'rack' as 'rack' | 'estanteria' });
const vistaPrevia = computed(() => {
    const p = pasillo.pasillo.trim().toUpperCase() || '?';
    const total = (pasillo.modulos || 0) * (pasillo.niveles || 0);
    return total ? `${p}-01-1 … ${p}-${String(pasillo.modulos).padStart(2, '0')}-${pasillo.niveles} · ${total} posiciones` : '';
});
function crearPasillo() {
    pasillo.post('/ubicaciones/pasillo', { ...opciones, onSuccess: () => { panelAlta.value = null; pasillo.reset(); } });
}
const suelta = useForm({ codigo: '', zona: 'adicional', capacidad: 900 });
function crearUbicacion() {
    suelta.post('/ubicaciones', { ...opciones, onSuccess: () => { panelAlta.value = null; suelta.reset(); } });
}
</script>

<template>
    <Head title="Mapa de ubicaciones" />
    <AppLayout>
        <Encabezado titulo="Mapa de ubicaciones" descripcion="Dónde está cada producto y qué tan llena está cada posición. Toca una ubicación para ver su contenido o reubicar.">
            <template v-if="esResponsable">
                <button type="button" class="btn btn-secundario" @click="panelAlta = panelAlta === 'ubicacion' ? null : 'ubicacion'"><MapPinPlus class="size-4" />Nueva ubicación</button>
                <button type="button" class="btn btn-primario" @click="panelAlta = panelAlta === 'pasillo' ? null : 'pasillo'"><LayoutGrid class="size-4" />Nuevo pasillo</button>
            </template>
        </Encabezado>

        <!-- Alta de pasillo -->
        <form v-if="panelAlta === 'pasillo'" class="panel mb-6 p-5" @submit.prevent="crearPasillo">
            <h2 class="mb-4 text-lg font-bold">Nuevo pasillo</h2>
            <div class="grid gap-4 sm:grid-cols-5">
                <div>
                    <label for="p-letra" class="etiqueta">Pasillo</label>
                    <input id="p-letra" v-model="pasillo.pasillo" class="campo font-id uppercase placeholder:normal-case" maxlength="2" placeholder="C" required />
                </div>
                <div>
                    <label for="p-mod" class="etiqueta">Módulos</label>
                    <input id="p-mod" v-model.number="pasillo.modulos" type="number" min="1" max="40" class="campo num text-right" required />
                </div>
                <div>
                    <label for="p-niv" class="etiqueta">Niveles</label>
                    <input id="p-niv" v-model.number="pasillo.niveles" type="number" min="1" max="9" class="campo num text-right" required />
                </div>
                <div>
                    <label for="p-cap" class="etiqueta">Piezas por posición</label>
                    <input id="p-cap" v-model.number="pasillo.capacidad" type="number" min="1" class="campo num text-right" required />
                </div>
                <div>
                    <label for="p-tipo" class="etiqueta">Tipo</label>
                    <select id="p-tipo" v-model="pasillo.zona" class="campo">
                        <option value="rack">Rack</option>
                        <option value="estanteria">Estantería</option>
                    </select>
                </div>
            </div>
            <p v-if="pasillo.errors.pasillo" class="mt-2 text-sm text-bad">{{ pasillo.errors.pasillo }}</p>
            <p v-for="k in ['modulos', 'niveles', 'capacidad'] as const" v-show="pasillo.errors[k]" :key="k" class="mt-2 text-sm text-bad">{{ pasillo.errors[k] }}</p>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="font-id text-sm text-ink-2">{{ vistaPrevia }}</p>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secundario" @click="panelAlta = null">Cancelar</button>
                    <button type="submit" class="btn btn-primario" :disabled="pasillo.processing"><Girando v-if="pasillo.processing" />Crear pasillo</button>
                </div>
            </div>
        </form>

        <!-- Alta de ubicación suelta -->
        <form v-if="panelAlta === 'ubicacion'" class="panel mb-6 p-5" @submit.prevent="crearUbicacion">
            <h2 class="mb-4 text-lg font-bold">Nueva ubicación</h2>
            <div class="grid gap-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                <div>
                    <label for="u-cod" class="etiqueta">Código</label>
                    <input id="u-cod" v-model="suelta.codigo" class="campo font-id uppercase placeholder:normal-case" placeholder="AD-03 o PISO-01" maxlength="20" required />
                    <p v-if="suelta.errors.codigo" class="mt-1 text-sm text-bad">{{ suelta.errors.codigo }}</p>
                </div>
                <div>
                    <label for="u-tipo" class="etiqueta">Tipo</label>
                    <select id="u-tipo" v-model="suelta.zona" class="campo">
                        <option v-for="z in [...ALMACENAJE, ...OPERATIVAS]" :key="z" :value="z">{{ zonas[z] }}</option>
                    </select>
                </div>
                <div>
                    <label for="u-cap" class="etiqueta">Capacidad (piezas)</label>
                    <input id="u-cap" v-model.number="suelta.capacidad" type="number" min="1" class="campo num text-right" required />
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-secundario" @click="panelAlta = null">Cancelar</button>
                    <button type="submit" class="btn btn-primario" :disabled="suelta.processing"><Girando v-if="suelta.processing" />Crear</button>
                </div>
            </div>
        </form>

        <!-- Búsqueda y leyenda -->
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div class="w-full max-w-md">
                <label for="buscar-mapa" class="etiqueta">¿Dónde está?</label>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-3" aria-hidden="true" />
                    <input id="buscar-mapa" v-model="buscar" type="search" class="campo pl-9" placeholder="SKU, descripción o número de pedimento" />
                </div>
                <p v-if="resumenBusqueda" class="mt-1.5 text-sm" :class="resumenBusqueda.ubicaciones ? 'text-ink-2' : 'text-bad'">
                    <template v-if="resumenBusqueda.ubicaciones">
                        En <b class="text-ink">{{ resumenBusqueda.ubicaciones }}</b> {{ resumenBusqueda.ubicaciones === 1 ? 'ubicación' : 'ubicaciones' }} · <b class="num text-ink">{{ n(resumenBusqueda.piezas) }}</b> piezas
                    </template>
                    <template v-else>No está en ninguna ubicación.</template>
                </p>
            </div>
            <ul class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-2" aria-label="Leyenda de ocupación">
                <li v-for="l in leyenda" :key="l.nivel" class="flex items-center gap-1.5"><span class="muestra" :class="`oc-${l.nivel}`" />{{ l.texto }}</li>
                <li class="flex items-center gap-1.5"><span class="rounded bg-warn-bg px-1 font-semibold text-warn">2 ped.</span>mezcla de pedimentos</li>
                <li class="flex items-center gap-1.5"><span class="muestra inactiva" />Desactivada</li>
            </ul>
        </div>

        <!-- Pasillos -->
        <div class="grid gap-6 xl:grid-cols-2">
            <section v-for="p in pasillos" :key="p.pasillo" class="panel p-5">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-bold">Pasillo {{ p.pasillo }} <span class="text-sm font-normal text-ink-3">· {{ p.tipo }}, vista de frente</span></h2>
                    <p class="num text-sm text-ink-2">{{ n(p.piezas) }} de {{ n(p.capacidad) }} piezas</p>
                </div>
                <div class="-m-1 overflow-x-auto p-1">
                    <div class="grid min-w-[22rem] gap-2" :style="{ gridTemplateColumns: `3.25rem repeat(${p.modulos.length}, minmax(4.25rem, 1fr))` }">
                        <span />
                        <span v-for="m in p.modulos" :key="m" class="text-center text-xs font-semibold text-ink-3">Mód. {{ m }}</span>
                        <template v-for="nv in p.niveles" :key="nv">
                            <span class="self-center text-xs font-semibold text-ink-3">Nivel {{ nv }}</span>
                            <template v-for="m in p.modulos" :key="m + nv">
                                <button
                                    v-if="p.celda(m, nv)"
                                    type="button"
                                    class="celda"
                                    :class="[
                                        `oc-${nivel(p.celda(m, nv)!)}`,
                                        { inactiva: !p.celda(m, nv)!.activa, apagada: !coincide(p.celda(m, nv)!), hallada: buscar && coincide(p.celda(m, nv)!), elegida: elegidaId === p.celda(m, nv)!.id },
                                    ]"
                                    :aria-label="`${p.celda(m, nv)!.codigo}: ${p.celda(m, nv)!.piezas} de ${p.celda(m, nv)!.capacidad} piezas`"
                                    @click="elegidaId = p.celda(m, nv)!.id"
                                >
                                    <span class="font-id text-xs font-semibold">{{ p.celda(m, nv)!.codigo }}</span>
                                    <span class="flex items-baseline justify-between gap-1 text-xs">
                                        <span class="num font-semibold">{{ p.celda(m, nv)!.activa ? n(p.celda(m, nv)!.piezas) : 'inactiva' }}</span>
                                        <span v-if="p.celda(m, nv)!.pedimentos > 1" class="rounded bg-warn-bg px-1 font-semibold text-warn">{{ p.celda(m, nv)!.pedimentos }} ped.</span>
                                    </span>
                                </button>
                                <span v-else class="rounded-ui border border-dashed border-line" aria-hidden="true" />
                            </template>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- Otras ubicaciones por zona -->
        <section v-for="[zona, us] in otras" :key="zona" class="mt-6">
            <h2 class="mb-2 text-sm font-semibold text-ink-2">{{ zonas[zona] ?? zona }}</h2>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                <button
                    v-for="u in us"
                    :key="u.id"
                    type="button"
                    class="celda"
                    :class="[`oc-${nivel(u)}`, { inactiva: !u.activa, apagada: !coincide(u), hallada: buscar && coincide(u), elegida: elegidaId === u.id }]"
                    @click="elegidaId = u.id"
                >
                    <span class="font-id text-xs font-semibold">{{ u.codigo }}</span>
                    <span class="flex items-baseline justify-between gap-1 text-xs">
                        <span class="num font-semibold">{{ u.activa ? n(u.piezas) : 'inactiva' }}</span>
                        <span class="text-ink-3">de {{ n(u.capacidad) }}</span>
                    </span>
                </button>
            </div>
        </section>

        <PreguntaAbierta class="mt-6">¿Cómo nombran hoy sus ubicaciones? ¿La capacidad la piensan en piezas, cajas o tarimas? ¿Se puede mezclar más de un pedimento en una misma posición?</PreguntaAbierta>

        <!-- Panel lateral -->
        <Teleport to="body">
            <Transition name="panel">
                <div v-if="elegida" class="fixed inset-0 z-40 flex justify-end">
                    <div class="absolute inset-0 bg-black/25" aria-hidden="true" @click="elegidaId = null" />
                    <aside class="cajon relative flex h-full w-full max-w-md flex-col bg-surface" role="dialog" aria-modal="true" :aria-label="`Ubicación ${elegida.codigo}`">
                        <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                            <div>
                                <IdSegmentado :valor="elegida.codigo" tipo="ubicacion" tam="lg" />
                                <p class="mt-1 text-sm text-ink-2">{{ zonas[elegida.zona] ?? elegida.zona }}<template v-if="!elegida.activa"> · desactivada</template></p>
                            </div>
                            <button type="button" class="flex size-10 items-center justify-center rounded-ui text-ink-2 hover:bg-surface-2" aria-label="Cerrar" @click="elegidaId = null"><X class="size-5" /></button>
                        </header>

                        <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                            <section>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span><b class="num font-display text-2xl">{{ n(elegida.piezas) }}</b> <span class="text-ink-2">de {{ n(elegida.capacidad) }} piezas</span></span>
                                    <span class="font-semibold" :class="nivel(elegida) === 'excedida' ? 'text-bad' : 'text-ink-2'">{{ pct(elegida) }} %</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2">
                                    <div class="h-full" :class="nivel(elegida) === 'excedida' ? 'bg-bad' : 'bg-primary'" :style="{ width: Math.min(100, pct(elegida)) + '%' }" />
                                </div>
                            </section>

                            <section>
                                <h3 class="mb-2 font-bold">Contenido</h3>
                                <p v-if="!elegida.contenido.length" class="text-sm text-ink-3">Vacía.</p>
                                <ul v-else class="divide-y divide-line rounded-ui border border-line">
                                    <li v-for="c in elegida.contenido" :key="c.existencia_id" class="px-3 py-2.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <Link :href="`/inventario?buscar=${encodeURIComponent(c.sku)}`" class="hover:underline"><IdSegmentado :valor="c.sku" tipo="sku" /></Link>
                                                <p class="mt-0.5"><IdSegmentado :valor="c.pedimento" tipo="pedimento" tam="sm" /> <span class="text-xs text-ink-3">{{ fecha(c.fecha_pago) }}</span></p>
                                                <Chip :valor="c.estado" :mapa="estadosExistencia" class="mt-1" />
                                            </div>
                                            <div class="text-right">
                                                <p class="num text-lg font-bold">{{ n(c.cantidad) }}</p>
                                                <button v-if="puedeReubicar(elegida, c) && reubicando !== c.existencia_id" type="button" class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline" @click="abrirReubicar(c)">
                                                    <ArrowRightLeft class="size-3.5" />Reubicar
                                                </button>
                                            </div>
                                        </div>
                                        <form v-if="reubicando === c.existencia_id" class="mt-3 grid gap-2 rounded-ui bg-surface-2 p-3 sm:grid-cols-[1fr_6rem]" @submit.prevent="reubicar(c)">
                                            <div>
                                                <label :for="`dest-${c.existencia_id}`" class="etiqueta">Mover a</label>
                                                <select :id="`dest-${c.existencia_id}`" v-model="mover.destino_id" class="campo font-id text-sm" required>
                                                    <option :value="null" disabled>Elige ubicación</option>
                                                    <option v-for="d in destinos" :key="d.id" :value="d.id">{{ d.codigo }} · {{ n(d.capacidad - d.piezas) }} libres</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label :for="`cant-${c.existencia_id}`" class="etiqueta">Piezas</label>
                                                <input :id="`cant-${c.existencia_id}`" v-model.number="mover.cantidad" type="number" min="1" :max="c.cantidad" class="campo num text-right" required />
                                            </div>
                                            <div class="flex gap-2 sm:col-span-2">
                                                <button type="submit" class="btn btn-primario" :disabled="!mover.destino_id || !!ocupado"><Girando v-if="es('mover')" />Mover</button>
                                                <button type="button" class="btn btn-secundario" @click="reubicando = null">Cancelar</button>
                                            </div>
                                        </form>
                                    </li>
                                </ul>
                            </section>

                            <section>
                                <h3 class="mb-2 font-bold">Últimos movimientos</h3>
                                <p v-if="!elegida.movimientos.length" class="text-sm text-ink-3">Sin movimientos recientes.</p>
                                <ul v-else class="space-y-2 text-sm">
                                    <li v-for="m in elegida.movimientos" :key="m.id" class="flex items-baseline justify-between gap-2">
                                        <span class="min-w-0">
                                            <b>{{ tiposMovimiento[m.tipo] ?? m.tipo }}</b>
                                            <span class="text-ink-2"> · {{ m.producto.sku }} · {{ m.destino === elegida.codigo ? 'entra' : 'sale' }}</span>
                                            <span class="block text-xs text-ink-3">{{ fechaYHora(m.fecha) }} · {{ m.usuario ?? '—' }}</span>
                                        </span>
                                        <span class="num font-semibold" :class="m.destino === elegida.codigo ? 'text-ok' : 'text-ink-2'">{{ m.destino === elegida.codigo ? '+' : '−' }}{{ n(m.cantidad) }}</span>
                                    </li>
                                </ul>
                            </section>

                            <section v-if="esResponsable" class="rounded-ui border border-line p-4">
                                <h3 class="mb-3 font-bold">Configuración</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="e-cap" class="etiqueta">Capacidad (piezas)</label>
                                        <input id="e-cap" v-model.number="edicion.capacidad" type="number" min="1" class="campo num text-right" />
                                    </div>
                                    <div>
                                        <label for="e-tipo" class="etiqueta">Tipo</label>
                                        <select id="e-tipo" v-model="edicion.zona" class="campo" :disabled="OPERATIVAS.includes(elegida.zona)">
                                            <option v-for="z in OPERATIVAS.includes(elegida.zona) ? [elegida.zona] : ALMACENAJE" :key="z" :value="z">{{ zonas[z] }}</option>
                                        </select>
                                    </div>
                                </div>
                                <label v-if="!OPERATIVAS.includes(elegida.zona)" class="mt-3 flex min-h-11 cursor-pointer items-center gap-3">
                                    <input v-model="edicion.activa" type="checkbox" class="size-5 accent-[var(--primary)]" />
                                    <span>Activa <span class="text-sm text-ink-3">(se sugiere y recibe mercancía)</span></span>
                                </label>
                                <p v-if="!edicion.activa && elegida.piezas" class="mt-1 text-sm text-warn">Tiene {{ n(elegida.piezas) }} piezas: reubícalas antes de desactivarla.</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-primario" :disabled="!editada || !!ocupado" @click="guardarUbicacion"><Girando v-if="es('editar')" />Guardar</button>
                                    <button v-if="!elegida.usada && !OPERATIVAS.includes(elegida.zona)" type="button" class="btn btn-secundario" :disabled="!!ocupado" @click="borrarUbicacion">
                                        <Girando v-if="es('borrar')" /><Trash2 v-else class="size-4" />Borrar
                                    </button>
                                </div>
                            </section>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.celda {
    position: relative;
    display: flex;
    min-height: 4.5rem;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.5rem;
    border-radius: var(--radius);
    border: 1px solid var(--line);
    text-align: left;
    color: var(--ink);
    cursor: pointer;
    transition:
        opacity 200ms cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 200ms cubic-bezier(0.16, 1, 0.3, 1),
        border-color 160ms;
}
.celda:hover {
    border-color: var(--line-strong);
}
.muestra {
    display: inline-block;
    width: 0.9rem;
    height: 0.9rem;
    border-radius: 3px;
    border: 1px solid var(--line);
}
.oc-vacia {
    background: var(--surface);
}
.oc-baja {
    background: color-mix(in srgb, var(--primary) 9%, var(--surface));
}
.oc-media {
    background: color-mix(in srgb, var(--primary) 20%, var(--surface));
}
.oc-alta {
    background: color-mix(in srgb, var(--primary) 34%, var(--surface));
}
.oc-excedida {
    background: var(--bad-bg);
    border-color: color-mix(in srgb, var(--bad) 45%, transparent);
}
.inactiva {
    background: repeating-linear-gradient(-45deg, var(--surface-2), var(--surface-2) 6px, var(--surface) 6px, var(--surface) 12px);
    color: var(--ink-3);
}
.apagada {
    opacity: 0.28;
}
.hallada {
    box-shadow: 0 0 0 2px var(--marca);
}
.elegida {
    box-shadow: 0 0 0 2px var(--primary);
}
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
    .panel-enter-from .cajon,
    .panel-leave-to .cajon {
        transform: none;
    }
}
</style>
