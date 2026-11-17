<script setup lang="ts">
import BarraCuadre from '@/Components/BarraCuadre.vue';
import BarridoRfid from '@/Components/BarridoRfid.vue';
import Contador from '@/Components/Contador.vue';
import Girando from '@/Components/Girando.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Resultado from '@/Components/Resultado.vue';
import { confirmar } from '@/composables/useConfirmar';
import { useLectura } from '@/composables/useLectura';
import { useOcupado } from '@/composables/useOcupado';
import PisoLayout from '@/Layouts/PisoLayout.vue';
import { conSigno, fechaYHora, n } from '@/lib/formato';
import Evidencias from '@/Components/Evidencias.vue';
import type { Evidencia, Pedimento, Producto } from '@/types';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Ban, Barcode, CheckCheck, Hand, Printer, RotateCcw, ScanLine, Undo2, X } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface CajaLinea {
    id: number;
    producto: Producto;
    leidas: number;
    sin_etiqueta: number;
    sin_accesorio: number;
    danadas: number;
    esperadas_por_caja: number | null;
}
interface Caja {
    id: number;
    numero: number;
    estado: 'leida' | 'anulada';
    origen: 'rfid' | 'codigo' | 'manual';
    usuario: string | null;
    hora: string;
    lineas: CajaLinea[];
    evidencias: Evidencia[];
}
interface Linea {
    id: number;
    producto: Producto;
    cajas_esperadas: number;
    piezas_por_caja: number;
    esperadas: number;
    leidas: number;
    confirmadas: number;
    cajas_leidas: number;
}

const props = defineProps<{
    recepcion: {
        id: number;
        folio: string;
        estado: string;
        pedimento: Pedimento;
        conteo_terminado_at: string | null;
        termino: string | null;
        cajas_esperadas: number;
        piezas_esperadas: number;
        lineas: Linea[];
        cajas: Caja[];
        evidencias: Evidencia[];
    };
    catalogo: { id: number; sku: string; upc: string | null; descripcion: string; piezas_por_caja: number; accesorio: string | null }[];
}>();

const { ocupado, con, es } = useOcupado();
const lectura = useLectura();
const cerrada = computed(() => props.recepcion.estado === 'cerrada');
const opciones = { preserveScroll: true };

const cajasLeidas = computed(() => props.recepcion.cajas.filter((c) => c.estado === 'leida').length);
const piezasContadas = computed(() => props.recepcion.lineas.reduce((s, l) => s + l.confirmadas, 0));
const avance = computed(() => (props.recepcion.cajas_esperadas ? Math.min(100, (cajasLeidas.value / props.recepcion.cajas_esperadas) * 100) : 0));

// Caja abierta: la última leída, o la que el auxiliar elija de la lista
const elegidaId = ref<number | null>(null);
const caja = computed(() => props.recepcion.cajas.find((c) => c.id === elegidaId.value) ?? props.recepcion.cajas[0] ?? null);
const marcas = reactive<Record<number, { sin_etiqueta: number; sin_accesorio: number; danadas: number }>>({});
watch(
    caja,
    (c) => {
        for (const l of c?.lineas ?? []) marcas[l.id] = { sin_etiqueta: l.sin_etiqueta, sin_accesorio: l.sin_accesorio, danadas: l.danadas };
    },
    { immediate: true },
);
const cambiada = computed(
    () =>
        !!caja.value &&
        caja.value.lineas.some((l) => {
            const m = marcas[l.id];
            return m && (m.sin_etiqueta !== l.sin_etiqueta || m.sin_accesorio !== l.sin_accesorio || m.danadas !== l.danadas);
        }),
);

function leerCaja() {
    const siguiente = props.recepcion.lineas.find((l) => l.cajas_leidas < l.cajas_esperadas);
    lectura.iniciar(siguiente ? Math.round(siguiente.esperadas / Math.max(1, siguiente.cajas_esperadas)) : 12);
    router.post(`/operador/recepciones/${props.recepcion.id}/cajas`, {}, {
        ...opciones,
        ...con('leer'),
        onSuccess: () => {
            elegidaId.value = null;
            const nueva = props.recepcion.cajas[0];
            lectura.terminar(nueva ? nueva.lineas.reduce((s, l) => s + l.leidas, 0) : 0, nueva?.lineas.length ?? 0);
        },
        onError: lectura.cancelar,
    });
}
function guardar(c: Caja) {
    const lineas = Object.fromEntries(c.lineas.map((l) => [l.id, marcas[l.id]]));
    router.put(`/operador/cajas/${c.id}`, { lineas }, { ...opciones, ...con('guardar') });
}
function releer(c: Caja) {
    lectura.iniciar(c.lineas.reduce((s, l) => s + (l.esperadas_por_caja ?? l.leidas), 0));
    router.post(`/operador/cajas/${c.id}/releer`, {}, {
        ...opciones,
        ...con('releer'),
        onSuccess: () => {
            const actual = props.recepcion.cajas.find((x) => x.id === c.id);
            lectura.terminar(actual ? actual.lineas.reduce((s, l) => s + l.leidas, 0) : 0, actual?.lineas.length ?? 0);
        },
        onError: lectura.cancelar,
    });
}
async function anular(c: Caja) {
    if (c.estado === 'leida') {
        const ok = await confirmar({
            titulo: `¿Anular la caja ${c.numero}?`,
            mensaje: 'Sus piezas dejan de contar en el total. Úsalo si la caja se leyó dos veces o con mercancía de otra recepción.',
            aceptar: 'Anular caja',
            tono: 'peligro',
        });
        if (!ok) return;
    }
    router.post(`/operador/cajas/${c.id}/anular`, {}, { ...opciones, ...con('anular') });
}
async function terminar() {
    const faltan = props.recepcion.lineas.filter((l) => l.confirmadas !== l.esperadas);
    const ok = await confirmar({
        titulo: `¿Terminar el conteo de ${props.recepcion.folio}?`,
        detalle: [
            `${cajasLeidas.value} cajas leídas de ${props.recepcion.cajas_esperadas} esperadas.`,
            `${n(piezasContadas.value)} piezas contadas de ${n(props.recepcion.piezas_esperadas)} esperadas.`,
            faltan.length ? `${faltan.length} SKU no cuadran: la responsable decide al cerrar.` : 'Todos los SKU cuadran.',
        ],
        aceptar: 'Terminar conteo',
    });
    if (ok) router.post(`/operador/recepciones/${props.recepcion.id}/terminar`, {}, con('terminar'));
}

// Captura sin lector RFID: a mano o con escáner de código de barras (el escáner "teclea" el código y un Enter)
const manual = reactive({ abierto: false, codigo: '', productoId: null as number | null, origen: 'manual' as 'manual' | 'codigo', piezas: 0, aviso: '' });
const pendientes = computed(() => props.recepcion.lineas.filter((l) => l.cajas_leidas < l.cajas_esperadas));
const productoManual = computed(() => props.catalogo.find((p) => p.id === manual.productoId) ?? null);
const piezasPorCaja = computed(() => {
    const l = props.recepcion.lineas.find((x) => x.producto.id === manual.productoId);
    return l?.piezas_por_caja ?? productoManual.value?.piezas_por_caja ?? 0;
});
function abrirManual() {
    Object.assign(manual, { abierto: true, codigo: '', productoId: pendientes.value[0]?.producto.id ?? null, origen: 'manual', aviso: '' });
    manual.piezas = piezasPorCaja.value;
    setTimeout(() => document.getElementById('codigo-manual')?.focus(), 50);
}
function elegirProducto(id: number, origen: 'manual' | 'codigo') {
    manual.productoId = id;
    manual.origen = origen;
    manual.piezas = piezasPorCaja.value;
    manual.aviso = '';
}
function buscarCodigo() {
    const c = manual.codigo.trim().toUpperCase();
    if (!c) return;
    const porCodigo = props.catalogo.find((p) => p.upc === c);
    const porSku = props.catalogo.find((p) => p.sku === c);
    const p = porCodigo ?? porSku;
    if (p) {
        elegirProducto(p.id, porCodigo ? 'codigo' : 'manual');
        manual.codigo = '';
        if (!props.recepcion.lineas.some((l) => l.producto.id === p.id)) manual.aviso = `${p.sku} no viene en esta recepción: quedará como no esperado.`;
    } else {
        manual.aviso = `No hay ningún producto con el código ${c}.`;
    }
}
function registrarManual(piezas: number) {
    if (!manual.productoId || piezas < 1) return;
    router.post(
        `/operador/recepciones/${props.recepcion.id}/cajas-manuales`,
        { producto_id: manual.productoId, piezas, origen: manual.origen },
        { ...opciones, ...con('manual'), onSuccess: () => { elegidaId.value = null; manual.abierto = false; } },
    );
}
const origenTexto = { rfid: 'RFID', codigo: 'código de barras', manual: 'a mano' } as const;

const estadoSku = (l: Linea) => {
    const d = l.confirmadas - l.esperadas;
    if (l.esperadas === 0) return { texto: 'No esperado', clase: 'text-warn' };
    if (l.cajas_leidas < l.cajas_esperadas) {
        const f = l.cajas_esperadas - l.cajas_leidas;
        return { texto: f === 1 ? 'Falta 1 caja' : `Faltan ${f} cajas`, clase: 'text-ink-3' };
    }
    if (d === 0) return { texto: 'Completo', clase: 'text-ok' };
    return { texto: conSigno(d) + ' pz', clase: 'text-bad' };
};
const bloqueado = computed(() => !!ocupado.value || lectura.fase.value !== 'inactivo' || cerrada.value);
</script>

<template>
    <Head :title="`Recepción ${recepcion.folio}`" />
    <PisoLayout :titulo="`Recepción · ${recepcion.folio}`" volver="/operador">
        <!-- Avance -->
        <section class="panel mb-4 px-5 py-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <IdSegmentado :valor="recepcion.pedimento.numero" tipo="pedimento" />
                <IdSegmentado :valor="recepcion.pedimento.contenedor" tipo="contenedor" tam="sm" />
            </div>
            <div class="mt-3 flex items-baseline justify-between text-sm">
                <span><b class="num font-display text-2xl">{{ cajasLeidas }}</b> <span class="text-ink-2">de {{ recepcion.cajas_esperadas }} cajas</span></span>
                <span class="text-ink-2"><b class="num text-ink">{{ n(piezasContadas) }}</b> de {{ n(recepcion.piezas_esperadas) }} piezas</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full bg-primary transition-[width] duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]" :style="{ width: avance + '%' }" />
            </div>
        </section>

        <section class="panel mb-4 px-5 py-4">
            <Evidencias
                tipo="recepcion"
                :id="recepcion.id"
                momento="llegada"
                titulo="Llegada del contenedor"
                ayuda="Antes de abrir: foto del sello y del contenedor."
                :evidencias="recepcion.evidencias"
                grande
            />
        </section>

        <Resultado v-if="cerrada" estado="cuadra" titulo="Recepción cerrada" class="mb-4">La responsable ya cerró esta recepción.</Resultado>
        <Resultado v-else-if="recepcion.conteo_terminado_at" estado="parcial" titulo="Conteo terminado" class="mb-4">
            {{ recepcion.termino }} lo terminó {{ fechaYHora(recepcion.conteo_terminado_at) }}. Si corriges una caja, el conteo se reabre y hay que volver a terminarlo.
        </Resultado>

        <!-- Leer -->
        <BarridoRfid v-if="lectura.fase.value !== 'inactivo'" :contador="lectura.contador.value" titulo="Leyendo la caja" grande class="mb-4" />
        <div v-else-if="!cerrada && !manual.abierto" class="mb-4 grid gap-3 sm:grid-cols-[1.4fr_1fr]">
            <button type="button" class="btn btn-primario btn-grande w-full" :disabled="bloqueado" @click="leerCaja">
                <ScanLine class="size-6" />Leer caja {{ (recepcion.cajas[0]?.numero ?? 0) + 1 }}
            </button>
            <button type="button" class="btn btn-secundario btn-grande w-full" :disabled="bloqueado" @click="abrirManual">
                <Hand class="size-5" />Sin lector RFID
            </button>
        </div>

        <!-- Caja sin lector: código de barras o a mano -->
        <section v-if="manual.abierto && !cerrada" class="panel mb-4">
            <header class="flex items-center justify-between border-b border-line px-5 py-3">
                <h2 class="text-lg font-bold">Caja {{ (recepcion.cajas[0]?.numero ?? 0) + 1 }} sin lector RFID</h2>
                <button type="button" class="flex size-11 items-center justify-center rounded-ui text-ink-2 hover:bg-surface-2" aria-label="Cerrar" @click="manual.abierto = false">
                    <X class="size-5" />
                </button>
            </header>
            <div class="space-y-4 px-5 py-4">
                <div>
                    <label for="codigo-manual" class="etiqueta">Escanea el código de barras o escribe el SKU</label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Barcode class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-ink-3" aria-hidden="true" />
                            <input
                                id="codigo-manual"
                                v-model="manual.codigo"
                                class="campo h-12 pl-11 font-id text-lg uppercase placeholder:normal-case"
                                placeholder="7501000000101 o PAN-DEN-3032"
                                autocomplete="off"
                                @keydown.enter.prevent="buscarCodigo"
                            />
                        </div>
                        <button type="button" class="btn btn-secundario h-12" @click="buscarCodigo">Buscar</button>
                    </div>
                    <p v-if="manual.aviso" class="mt-1.5 text-sm text-warn">{{ manual.aviso }}</p>
                </div>

                <div v-if="pendientes.length">
                    <p class="etiqueta">O elige un SKU pendiente</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="l in pendientes"
                            :key="l.id"
                            type="button"
                            class="flex min-h-12 items-center gap-2 rounded-ui border px-3 font-id text-sm font-semibold"
                            :class="manual.productoId === l.producto.id ? 'border-primary bg-surface text-ink' : 'border-line bg-surface-2 text-ink-2'"
                            :aria-pressed="manual.productoId === l.producto.id"
                            @click="elegirProducto(l.producto.id, 'manual')"
                        >
                            {{ l.producto.sku }}
                            <span class="font-ui text-xs font-normal text-ink-3">faltan {{ l.cajas_esperadas - l.cajas_leidas }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="productoManual" class="rounded-ui border border-line bg-surface-2 p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <IdSegmentado :valor="productoManual.sku" tipo="sku" tam="lg" class="!text-xl" />
                            <p class="text-ink-2">{{ productoManual.descripcion }}</p>
                        </div>
                        <span class="flex items-center gap-1.5 text-sm text-ink-2">
                            <component :is="manual.origen === 'codigo' ? Barcode : Hand" class="size-4" aria-hidden="true" />{{ origenTexto[manual.origen] }}
                        </span>
                    </div>
                    <button type="button" class="btn btn-primario btn-grande mt-4 w-full" :disabled="bloqueado || !piezasPorCaja" @click="registrarManual(piezasPorCaja)">
                        <Girando v-if="es('manual')" />Caja completa: {{ piezasPorCaja }} piezas
                    </button>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <p class="etiqueta">¿No viene completa? Piezas contadas</p>
                            <Contador v-model="manual.piezas" etiqueta="Piezas contadas" />
                        </div>
                        <button type="button" class="btn btn-secundario h-12" :disabled="bloqueado || manual.piezas < 1" @click="registrarManual(manual.piezas)">
                            Registrar {{ manual.piezas }} piezas
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Caja abierta -->
        <article v-if="caja" class="panel mb-4" :class="{ 'opacity-60': caja.estado === 'anulada' }">
            <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <p class="font-display text-2xl font-bold">
                        Caja {{ caja.numero }}
                        <span v-if="caja.estado === 'anulada'" class="ml-2 rounded bg-bad-bg px-2 py-0.5 align-middle text-sm text-bad">Anulada</span>
                    </p>
                    <p class="flex items-center gap-1.5 text-sm text-ink-2">
                        <component :is="caja.origen === 'rfid' ? ScanLine : caja.origen === 'codigo' ? Barcode : Hand" class="size-4" aria-hidden="true" />
                        {{ origenTexto[caja.origen] }} · {{ caja.usuario }} · {{ fechaYHora(caja.hora) }}
                    </p>
                </div>
                <a
                    v-if="caja.estado === 'leida'"
                    :href="`/etiquetas/recepciones/${recepcion.id}?caja=${caja.id}`"
                    target="_blank"
                    class="btn btn-secundario h-12 flex-none"
                    title="Etiqueta para pegar en la caja"
                    ><Printer class="size-5" />Etiqueta</a
                >
            </header>

            <div v-for="(l, i) in caja.lineas" :key="l.id" class="border-b border-line px-5 py-4 last:border-b-0" :class="{ 'fila-recien': lectura.recien(i) }">
                <div class="flex items-baseline justify-between gap-3">
                    <div>
                        <IdSegmentado :valor="l.producto.sku" tipo="sku" tam="lg" class="!text-xl" />
                        <p class="text-ink-2">{{ l.producto.descripcion }}</p>
                    </div>
                    <div class="text-right">
                        <p class="num font-display text-4xl leading-none font-bold">{{ l.leidas }}</p>
                        <p class="text-sm text-ink-2">
                            {{ caja.origen === 'rfid' ? 'leídas' : 'contadas' }}<template v-if="l.esperadas_por_caja"> · caja de {{ l.esperadas_por_caja }}</template>
                        </p>
                        <p v-if="l.esperadas_por_caja && l.leidas !== l.esperadas_por_caja" class="text-sm font-semibold text-bad">{{ conSigno(l.leidas - l.esperadas_por_caja) }} contra la caja</p>
                    </div>
                </div>

                <div v-if="marcas[l.id] && !cerrada" class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div v-if="caja.origen === 'rfid'">
                        <p class="etiqueta">Sin etiqueta (contadas a mano)</p>
                        <Contador v-model="marcas[l.id].sin_etiqueta" etiqueta="Sin etiqueta" :deshabilitado="caja.estado === 'anulada'" />
                    </div>
                    <div v-if="l.producto.accesorio">
                        <p class="etiqueta">Sin {{ l.producto.accesorio }}</p>
                        <Contador v-model="marcas[l.id].sin_accesorio" :etiqueta="`Sin ${l.producto.accesorio}`" :deshabilitado="caja.estado === 'anulada'" />
                    </div>
                    <div>
                        <p class="etiqueta">Dañadas</p>
                        <Contador v-model="marcas[l.id].danadas" etiqueta="Dañadas" :deshabilitado="caja.estado === 'anulada'" />
                    </div>
                </div>
            </div>

            <div v-if="caja.estado === 'leida'" class="border-t border-line px-5 py-4">
                <Evidencias
                    tipo="caja"
                    :id="caja.id"
                    momento="caja"
                    titulo="Fotos de la caja"
                    ayuda="Si llegó golpeada, abierta o mojada, tómale una foto."
                    :evidencias="caja.evidencias"
                    :puede-subir="!cerrada"
                    grande
                />
            </div>

            <footer v-if="!cerrada" class="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                <button v-if="cambiada" type="button" class="btn btn-primario btn-grande flex-1" :disabled="bloqueado" @click="guardar(caja)">
                    <Girando v-if="es('guardar')" />Guardar caja {{ caja.numero }}
                </button>
                <button v-if="caja.estado === 'leida' && caja.origen === 'rfid'" type="button" class="btn btn-secundario btn-grande flex-1" :disabled="bloqueado" @click="releer(caja)">
                    <RotateCcw class="size-5" />Volver a leer
                </button>
                <button type="button" class="btn btn-secundario btn-grande flex-1" :disabled="bloqueado" @click="anular(caja)">
                    <Girando v-if="es('anular')" /><component :is="caja.estado === 'leida' ? Ban : Undo2" v-else class="size-5" />
                    {{ caja.estado === 'leida' ? 'Anular caja' : 'Volver a contarla' }}
                </button>
            </footer>
        </article>

        <!-- Avance por SKU -->
        <section class="panel mb-4">
            <h2 class="border-b border-line px-5 py-3 text-lg font-bold">Por SKU</h2>
            <ul class="divide-y divide-line">
                <li v-for="l in recepcion.lineas" :key="l.id" class="grid gap-2 px-5 py-3 sm:grid-cols-[12rem_1fr] sm:items-center">
                    <div class="min-w-0">
                        <IdSegmentado :valor="l.producto.sku" tipo="sku" />
                        <p class="text-xs" :class="estadoSku(l).clase">{{ l.cajas_leidas }} de {{ l.cajas_esperadas }} cajas · {{ estadoSku(l).texto }}</p>
                    </div>
                    <BarraCuadre :esperado="l.esperadas" :contado="l.cajas_leidas || l.confirmadas ? l.confirmadas : null" />
                </li>
            </ul>
        </section>

        <!-- Cajas leídas -->
        <section v-if="recepcion.cajas.length" class="panel mb-4 px-5 py-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-bold">Cajas</h2>
                <a :href="`/etiquetas/recepciones/${recepcion.id}`" target="_blank" class="btn btn-secundario"><Printer class="size-4" />Imprimir etiquetas</a>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="c in recepcion.cajas"
                    :key="c.id"
                    type="button"
                    class="flex h-12 min-w-12 items-center justify-center rounded-ui border px-3 font-id font-semibold"
                    :class="[
                        c.id === caja?.id ? 'border-primary bg-surface text-ink' : 'border-line bg-surface-2 text-ink-2',
                        c.estado === 'anulada' ? 'line-through opacity-60' : '',
                        c.lineas.some((l) => l.esperadas_por_caja && l.leidas !== l.esperadas_por_caja) ? 'text-bad' : '',
                    ]"
                    :aria-pressed="c.id === caja?.id"
                    @click="elegidaId = c.id"
                >
                    {{ c.numero }}
                </button>
            </div>
        </section>

        <button v-if="!cerrada && cajasLeidas && !recepcion.conteo_terminado_at" type="button" class="btn btn-secundario btn-grande w-full" :disabled="bloqueado" @click="terminar">
            <Girando v-if="es('terminar')" /><CheckCheck v-else class="size-5" />Terminar conteo
        </button>
    </PisoLayout>
</template>
