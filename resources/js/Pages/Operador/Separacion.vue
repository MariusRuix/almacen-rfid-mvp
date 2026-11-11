<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Resultado from '@/Components/Resultado.vue';
import PisoLayout from '@/Layouts/PisoLayout.vue';
import { estadosDestino } from '@/lib/etiquetas';
import { n } from '@/lib/formato';
import type { DestinoPedido, PedidoDetalle } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{ pedido: PedidoDetalle; tomadoPorProducto: Record<number, number> }>();

const pendientes = computed(() => props.pedido.destinos.filter((d) => d.estado === 'pendiente'));
const actualId = ref<number | null>(pendientes.value[0]?.id ?? null);
const actual = computed(() => props.pedido.destinos.find((d) => d.id === actualId.value) ?? null);

const datos = reactive<{ lineas: Record<number, number>; actividades: Record<string, boolean>; bultos: number }>({ lineas: {}, actividades: {}, bultos: 1 });
watch(
    actual,
    (d) => {
        if (!d) return;
        datos.lineas = Object.fromEntries(d.lineas.map((l) => [l.id, l.separada ?? l.cantidad]));
        datos.actividades = Object.fromEntries(d.actividades.map((a) => [a.campo, a.hecha]));
        datos.bultos = d.bultos || 1;
    },
    { immediate: true },
);
const procesando = ref(false);
const errores = ref<Record<string, string>>({});

function confirmar(d: DestinoPedido) {
    procesando.value = true;
    router.post(`/operador/destinos/${d.id}`, datos, {
        preserveScroll: true,
        onError: (e) => (errores.value = e),
        onSuccess: () => {
            errores.value = {};
            actualId.value = props.pedido.destinos.find((x) => x.estado === 'pendiente')?.id ?? null;
        },
        onFinish: () => (procesando.value = false),
    });
}
</script>

<template>
    <Head :title="`Separación ${pedido.folio}`" />
    <PisoLayout :titulo="`Separar · ${pedido.folio}`" volver="/operador">
        <div class="mb-4 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Destinos">
            <button
                v-for="d in pedido.destinos"
                :key="d.id"
                type="button"
                role="tab"
                :aria-selected="d.id === actualId"
                class="flex min-h-14 flex-none items-center gap-2 rounded-ui border px-4 font-id font-semibold"
                :class="d.id === actualId ? 'border-primary bg-surface text-ink' : 'border-line bg-surface-2 text-ink-2'"
                @click="actualId = d.id"
            >
                <Check v-if="d.estado !== 'pendiente'" class="size-4 text-ok" aria-hidden="true" />{{ d.codigo }}
            </button>
        </div>

        <article v-if="actual" class="panel">
            <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <p class="font-id text-2xl font-bold">{{ actual.codigo }}</p>
                    <p class="text-ink-2">{{ actual.nombre }}</p>
                </div>
                <Chip :valor="actual.estado" :mapa="estadosDestino" />
            </header>

            <ul class="divide-y divide-line">
                <li v-for="l in actual.lineas" :key="l.id" class="flex items-center gap-4 px-5 py-4">
                    <div class="flex-1">
                        <IdSegmentado :valor="l.producto.sku" tipo="sku" tam="lg" class="!text-xl" />
                        <p class="text-ink-2">{{ l.producto.descripcion }}</p>
                        <p class="text-sm text-ink-3">Pedido: <span class="num">{{ n(l.cantidad) }}</span> · tomado en total: <span class="num">{{ n(tomadoPorProducto[l.producto.id]) }}</span></p>
                    </div>
                    <input
                        v-model.number="datos.lineas[l.id]"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        class="campo num h-14 w-28 text-center text-2xl font-bold"
                        :class="datos.lineas[l.id] !== l.cantidad ? 'border-warn text-warn' : ''"
                        :aria-label="`Piezas de ${l.producto.sku} en el bulto`"
                    />
                </li>
            </ul>

            <div class="space-y-3 border-t border-line px-5 py-4">
                <p class="font-semibold">Preparación del destino</p>
                <label v-for="a in actual.actividades" :key="a.campo" class="flex min-h-14 cursor-pointer items-center gap-4 rounded-ui border border-line px-4" :class="datos.actividades[a.campo] ? 'bg-ok-bg' : 'bg-surface'">
                    <input v-model="datos.actividades[a.campo]" type="checkbox" class="size-6 accent-[var(--ok)]" />
                    <span class="text-lg">{{ a.etiqueta }}</span>
                </label>
                <div class="flex items-center gap-4">
                    <label for="bultos" class="flex-1 text-lg">Bultos</label>
                    <input id="bultos" v-model.number="datos.bultos" type="number" min="1" inputmode="numeric" class="campo num h-14 w-28 text-center text-2xl font-bold" />
                </div>
                <p v-for="(e, k) in errores" :key="k" class="text-bad">{{ e }}</p>
            </div>

            <div class="border-t border-line px-5 py-4">
                <button type="button" class="btn btn-primario btn-grande w-full" :disabled="procesando" @click="confirmar(actual)">
                    {{ actual.estado === 'pendiente' ? `${actual.codigo} listo` : `Actualizar ${actual.codigo}` }}
                </button>
            </div>
        </article>

        <div v-else class="space-y-5">
            <Resultado estado="cuadra" titulo="Todos los destinos separados" grande>Sigue la revisión con el lector RFID por destino.</Resultado>
            <Link :href="`/pedidos/${pedido.id}`" class="btn btn-primario btn-grande w-full">Ir a la revisión</Link>
        </div>
    </PisoLayout>
</template>
