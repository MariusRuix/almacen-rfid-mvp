<script setup lang="ts">
import { fecha, fechaYHora } from '@/lib/formato';
import { Check, X } from 'lucide-vue-next';
import { computed } from 'vue';

export interface DatosAvance {
    estado: string;
    tareas: number;
    tareas_hechas: number;
    destinos: number;
    separados: number;
    verificados: number;
    con_diferencia: number;
    /** códigos de destinos con diferencia, para explicar el bloqueo */
    diferencias?: string[];
    creado?: string | null;
    salida?: { folio: string; fecha: string } | null;
}

/** Avance del pedido de recibido a salida, con el dato real de cada paso. */
const props = withDefaults(defineProps<{ avance: DatosAvance; compacto?: boolean }>(), { compacto: false });

const orden = ['nuevo', 'en_surtido', 'en_separacion', 'en_revision', 'listo', 'despachado'];
const actual = computed(() => Math.max(0, orden.indexOf(props.avance.estado)));
const terminado = computed(() => props.avance.estado === 'despachado');
const bloqueado = computed(() => actual.value === 3 && props.avance.con_diferencia > 0);

const fraccion = (h: number, t: number) => (t ? Math.min(1, h / t) : 0);
const pasos = computed(() => {
    const a = props.avance;
    const de = (h: number, t: number, cosa: string) => `${h} de ${t} ${cosa}`;
    return [
        { nombre: 'Recibido', dato: a.creado ? fecha(a.creado) : 'Pedido registrado', parcial: 1 },
        { nombre: 'Toma', dato: actual.value < 1 ? 'Por liberar' : de(a.tareas_hechas, a.tareas, 'tomas'), parcial: fraccion(a.tareas_hechas, a.tareas) },
        { nombre: 'Separación', dato: actual.value < 2 ? '—' : de(a.separados, a.destinos, 'destinos'), parcial: fraccion(a.separados, a.destinos) },
        {
            nombre: 'Revisión',
            dato:
                actual.value < 3
                    ? '—'
                    : bloqueado.value
                      ? `${a.diferencias?.length ? a.diferencias.join(', ') : a.con_diferencia} con diferencia`
                      : de(a.verificados, a.destinos, 'verificados'),
            parcial: fraccion(a.verificados, a.destinos),
        },
        { nombre: 'Listo', dato: actual.value === 4 ? 'Para salir' : actual.value > 4 ? 'Verificado' : '—', parcial: 1 },
        { nombre: 'Salida', dato: a.salida ? `${a.salida.folio} · ${fechaYHora(a.salida.fecha)}` : terminado.value ? 'Despachado' : 'Pendiente', parcial: 1 },
    ];
});

const estadoPaso = (i: number) => {
    if (terminado.value || i < actual.value) return 'hecho';
    if (i === actual.value) return bloqueado.value ? 'bloqueado' : 'actual';
    return 'futuro';
};

// Relleno de la línea: pasos completos + avance dentro del paso actual
const relleno = computed(() => {
    if (terminado.value) return 100;
    const parcial = [1, 2, 3].includes(actual.value) ? pasos.value[actual.value].parcial : 0;
    return Math.min(100, ((actual.value + parcial) / (pasos.value.length - 1)) * 100);
});
</script>

<template>
    <!-- Compacto: para listas -->
    <div v-if="compacto" class="min-w-44" :aria-label="`Avance: ${pasos[actual].nombre}, ${pasos[actual].dato}`">
        <div class="flex gap-0.5" aria-hidden="true">
            <span
                v-for="(p, i) in pasos"
                :key="p.nombre"
                class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2"
                :title="p.nombre"
            >
                <span
                    class="absolute inset-y-0 left-0 rounded-full"
                    :class="estadoPaso(i) === 'bloqueado' ? 'bg-bad' : estadoPaso(i) === 'hecho' ? 'bg-primary' : 'bg-marca'"
                    :style="{ width: estadoPaso(i) === 'hecho' ? '100%' : estadoPaso(i) === 'futuro' ? '0%' : `${Math.max(15, p.parcial * 100)}%` }"
                />
            </span>
        </div>
        <p class="mt-1 text-xs" :class="bloqueado ? 'font-semibold text-bad' : 'text-ink-2'">
            <b class="text-ink" :class="{ 'text-bad': bloqueado }">{{ pasos[actual].nombre }}</b> · {{ pasos[actual].dato }}
        </p>
    </div>

    <!-- Completo -->
    <div v-else class="overflow-x-auto">
        <ol class="relative grid min-w-[36rem] grid-cols-6" aria-label="Avance del pedido">
            <span class="pista" aria-hidden="true"><span class="lleno" :class="{ rojo: bloqueado }" :style="{ width: `${relleno}%` }" /></span>
            <li v-for="(p, i) in pasos" :key="p.nombre" class="relative flex flex-col items-center px-1 text-center" :aria-current="estadoPaso(i) === 'actual' || estadoPaso(i) === 'bloqueado' ? 'step' : undefined">
                <span class="nodo" :class="estadoPaso(i)">
                    <Check v-if="estadoPaso(i) === 'hecho'" class="size-4" aria-hidden="true" />
                    <X v-else-if="estadoPaso(i) === 'bloqueado'" class="size-4" aria-hidden="true" />
                    <span v-else class="num text-xs font-bold">{{ i + 1 }}</span>
                </span>
                <span class="mt-2 text-sm" :class="estadoPaso(i) === 'futuro' ? 'text-ink-3' : 'font-semibold text-ink'">{{ p.nombre }}</span>
                <span class="mt-0.5 text-xs leading-snug" :class="estadoPaso(i) === 'bloqueado' ? 'font-semibold text-bad' : estadoPaso(i) === 'futuro' ? 'text-ink-3' : 'text-ink-2'">
                    {{ p.dato }}
                </span>
            </li>
        </ol>
    </div>
</template>

<style scoped>
.pista {
    position: absolute;
    top: 0.95rem;
    left: calc(100% / 12);
    right: calc(100% / 12);
    height: 4px;
    border-radius: 999px;
    background: var(--line);
}
.lleno {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: var(--primary);
    transition: width 600ms cubic-bezier(0.16, 1, 0.3, 1);
}
.lleno.rojo {
    background: linear-gradient(90deg, var(--primary) 80%, var(--bad));
}
.nodo {
    position: relative;
    z-index: 1;
    display: flex;
    width: 2rem;
    height: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: var(--surface);
    border: 2px solid var(--line-strong);
    color: var(--ink-3);
}
.nodo.hecho {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.nodo.actual {
    border-color: var(--marca);
    color: var(--marca);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--marca) 18%, transparent);
}
.nodo.bloqueado {
    background: var(--bad);
    border-color: var(--bad);
    color: #fff;
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--bad) 18%, transparent);
}
@media (prefers-reduced-motion: reduce) {
    .lleno {
        transition: none;
    }
}
</style>
