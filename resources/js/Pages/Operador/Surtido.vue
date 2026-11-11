<script setup lang="ts">
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Resultado from '@/Components/Resultado.vue';
import PisoLayout from '@/Layouts/PisoLayout.vue';
import { fecha, n } from '@/lib/formato';
import type { Pedimento, Producto, UbicacionCorta } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Minus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    pedido: { id: number; folio: string; estado: string };
    tarea: null | { id: number; orden: number; producto: Producto; pedimento: Pedimento; ubicacion: UbicacionCorta; cantidad: number };
    total: number;
    hechas: number;
    siguiente: UbicacionCorta | null;
    zonaPreparacion: string;
}>();

const form = useForm({ tomada: props.tarea?.cantidad ?? 0 });
const faltan = ref(false);
watch(
    () => props.tarea?.id,
    () => {
        form.tomada = props.tarea?.cantidad ?? 0;
        faltan.value = false;
    },
);
const avance = computed(() => (props.total ? (props.hechas / props.total) * 100 : 0));

function confirmar() {
    if (!props.tarea) return;
    if (!faltan.value) form.tomada = props.tarea.cantidad;
    form.post(`/operador/tareas/${props.tarea.id}`, { preserveScroll: false });
}
const ajustar = (d: number) => (form.tomada = Math.max(0, Math.min(props.tarea?.cantidad ?? 0, form.tomada + d)));
</script>

<template>
    <Head :title="`Surtido ${pedido.folio}`" />
    <PisoLayout :titulo="`Toma · ${pedido.folio}`" volver="/operador">
        <div class="mb-4">
            <div class="flex justify-between text-sm text-ink-2">
                <span>Instrucción {{ Math.min(hechas + 1, total) }} de {{ total }}</span>
                <span>{{ hechas }} confirmadas</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full bg-primary transition-[width] duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]" :style="{ width: avance + '%' }" />
            </div>
        </div>

        <template v-if="tarea">
            <article class="panel overflow-hidden">
                <div class="bg-surface-2 px-5 py-5">
                    <p class="text-sm font-semibold text-ink-2">Ve a</p>
                    <IdSegmentado :valor="tarea.ubicacion.codigo" tipo="ubicacion" tam="lg" class="mt-1" />
                </div>
                <div class="space-y-4 px-5 py-5">
                    <div>
                        <p class="text-sm font-semibold text-ink-2">Toma</p>
                        <p class="flex items-baseline gap-3">
                            <span class="num font-display text-6xl leading-none font-bold">{{ n(tarea.cantidad) }}</span>
                            <span class="text-xl text-ink-2">piezas</span>
                        </p>
                    </div>
                    <div>
                        <IdSegmentado :valor="tarea.producto.sku" tipo="sku" tam="lg" />
                        <p class="text-lg text-ink-2">{{ tarea.producto.descripcion }}</p>
                    </div>
                    <div class="rounded-ui border border-line px-4 py-3">
                        <p class="text-sm font-semibold text-ink-2">Solo del pedimento</p>
                        <IdSegmentado :valor="tarea.pedimento.numero" tipo="pedimento" class="mt-0.5 !text-lg" />
                        <p class="text-sm text-ink-3">Pagado {{ fecha(tarea.pedimento.fecha_pago) }}</p>
                    </div>
                </div>
            </article>

            <div v-if="faltan" class="panel mt-4 px-5 py-4">
                <p class="mb-3 font-semibold">¿Cuántas piezas tomaste?</p>
                <div class="flex items-center gap-3">
                    <button type="button" class="btn btn-secundario btn-grande w-16 px-0" aria-label="Una menos" @click="ajustar(-1)"><Minus class="size-6" /></button>
                    <input v-model.number="form.tomada" type="number" inputmode="numeric" min="0" :max="tarea.cantidad" class="campo num h-16 flex-1 text-center text-3xl font-bold" aria-label="Piezas tomadas" />
                    <button type="button" class="btn btn-secundario btn-grande w-16 px-0" aria-label="Una más" @click="ajustar(1)"><Plus class="size-6" /></button>
                </div>
                <p v-if="form.errors.tomada" class="mt-2 text-bad">{{ form.errors.tomada }}</p>
                <p class="mt-2 text-sm text-ink-2">Lo que falte queda como incidencia para la responsable.</p>
            </div>

            <div class="mt-5 grid gap-3">
                <button type="button" class="btn btn-primario btn-grande w-full" :disabled="form.processing" @click="confirmar">
                    {{ faltan ? `Confirmar ${form.tomada} piezas` : `Tomé ${n(tarea.cantidad)} piezas` }}
                </button>
                <button type="button" class="btn btn-secundario btn-grande w-full" @click="faltan = !faltan">
                    {{ faltan ? 'Sí están completas' : 'No están completas' }}
                </button>
            </div>
            <p class="mt-4 text-center text-ink-2">
                Lleva las piezas a <IdSegmentado :valor="zonaPreparacion" tipo="ubicacion" tam="sm" />.
                <template v-if="siguiente"> Después: <IdSegmentado :valor="siguiente.codigo" tipo="ubicacion" tam="sm" /></template>
            </p>
        </template>

        <template v-else>
            <Resultado estado="cuadra" titulo="Toma terminada" grande>Todas las piezas están en {{ zonaPreparacion }}. Sigue la separación por destino.</Resultado>
            <Link :href="`/operador/pedidos/${pedido.id}/separacion`" class="btn btn-primario btn-grande mt-5 w-full">Separar por destino</Link>
        </template>
    </PisoLayout>
</template>
