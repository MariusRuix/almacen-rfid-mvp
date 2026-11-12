<script setup lang="ts">
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
import type { Pedimento, Producto } from '@/types';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { Ban, CheckCheck, RotateCcw, ScanLine, Undo2 } from 'lucide-vue-next';
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
    usuario: string | null;
    hora: string;
    lineas: CajaLinea[];
}
interface Linea {
    id: number;
    producto: Producto;
    cajas_esperadas: number;
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
    };
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

        <Resultado v-if="cerrada" estado="cuadra" titulo="Recepción cerrada" class="mb-4">La responsable ya cerró esta recepción.</Resultado>
        <Resultado v-else-if="recepcion.conteo_terminado_at" estado="parcial" titulo="Conteo terminado" class="mb-4">
            {{ recepcion.termino }} lo terminó {{ fechaYHora(recepcion.conteo_terminado_at) }}. Todavía puedes corregir cajas hasta que la responsable cierre.
        </Resultado>

        <!-- Leer -->
        <BarridoRfid v-if="lectura.fase.value !== 'inactivo'" :contador="lectura.contador.value" titulo="Leyendo la caja" grande class="mb-4" />
        <button v-else-if="!cerrada" type="button" class="btn btn-primario btn-grande mb-4 w-full" :disabled="bloqueado" @click="leerCaja">
            <ScanLine class="size-6" />Leer caja {{ (recepcion.cajas[0]?.numero ?? 0) + 1 }}
        </button>

        <!-- Caja abierta -->
        <article v-if="caja" class="panel mb-4" :class="{ 'opacity-60': caja.estado === 'anulada' }">
            <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <p class="font-display text-2xl font-bold">
                        Caja {{ caja.numero }}
                        <span v-if="caja.estado === 'anulada'" class="ml-2 rounded bg-bad-bg px-2 py-0.5 align-middle text-sm text-bad">Anulada</span>
                    </p>
                    <p class="text-sm text-ink-2">{{ caja.usuario }} · {{ fechaYHora(caja.hora) }}</p>
                </div>
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
                            leídas<template v-if="l.esperadas_por_caja"> · caja de {{ l.esperadas_por_caja }}</template>
                        </p>
                        <p v-if="l.esperadas_por_caja && l.leidas !== l.esperadas_por_caja" class="text-sm font-semibold text-bad">{{ conSigno(l.leidas - l.esperadas_por_caja) }} contra la caja</p>
                    </div>
                </div>

                <div v-if="marcas[l.id] && !cerrada" class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div>
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

            <footer v-if="!cerrada" class="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                <button v-if="cambiada" type="button" class="btn btn-primario btn-grande flex-1" :disabled="bloqueado" @click="guardar(caja)">
                    <Girando v-if="es('guardar')" />Guardar caja {{ caja.numero }}
                </button>
                <button v-if="caja.estado === 'leida'" type="button" class="btn btn-secundario btn-grande flex-1" :disabled="bloqueado" @click="releer(caja)">
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
                <li v-for="l in recepcion.lineas" :key="l.id" class="flex items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <IdSegmentado :valor="l.producto.sku" tipo="sku" />
                        <p class="text-xs text-ink-3">{{ l.cajas_leidas }} de {{ l.cajas_esperadas }} cajas</p>
                    </div>
                    <p class="num text-right">
                        <b>{{ n(l.confirmadas) }}</b> <span class="text-ink-3">/ {{ n(l.esperadas) }}</span>
                    </p>
                    <p class="w-28 text-right text-sm font-semibold" :class="estadoSku(l).clase">{{ estadoSku(l).texto }}</p>
                </li>
            </ul>
        </section>

        <!-- Cajas leídas -->
        <section v-if="recepcion.cajas.length" class="panel mb-4 px-5 py-4">
            <h2 class="mb-3 text-lg font-bold">Cajas</h2>
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

        <button v-if="!cerrada && cajasLeidas" type="button" class="btn btn-secundario btn-grande w-full" :disabled="bloqueado" @click="terminar">
            <Girando v-if="es('terminar')" /><CheckCheck v-else class="size-5" />{{ recepcion.conteo_terminado_at ? 'Volver a marcar como terminado' : 'Terminar conteo' }}
        </button>
    </PisoLayout>
</template>
