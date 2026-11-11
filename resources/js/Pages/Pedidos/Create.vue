<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { n } from '@/lib/formato';
import type { Producto } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, nextTick, reactive } from 'vue';

interface ProductoDisp extends Producto {
    disponible: number;
}
interface DestinoCat {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    requiere_etiqueta_comercial: boolean;
    requiere_sensor: boolean;
}

const props = defineProps<{ productos: ProductoDisp[]; destinos: DestinoCat[]; propietarios: string[]; manana: string }>();

interface Renglon {
    clave: number;
    sku: string;
    producto_id: number | null;
    cantidades: Record<number, number | null>; // destino_id → piezas
}

let siguiente = 1;
const nuevo = (): Renglon => ({ clave: siguiente++, sku: '', producto_id: null, cantidades: {} });
const renglones = reactive<Renglon[]>([nuevo()]);
const activos = reactive<Set<number>>(new Set(props.destinos.map((d) => d.id)));
const columnas = computed(() => props.destinos.filter((d) => activos.has(d.id)));

const form = useForm({ propietario: props.propietarios[0] ?? '', fecha_compromiso: props.manana });

const porSku = computed(() => new Map(props.productos.map((p) => [p.sku.toUpperCase(), p])));
const producto = (r: Renglon) => props.productos.find((p) => p.id === r.producto_id) ?? null;

function elegirSku(r: Renglon) {
    const p = porSku.value.get(r.sku.trim().toUpperCase()) ?? null;
    r.producto_id = p?.id ?? null;
    if (p) r.sku = p.sku;
}
const desconocido = (r: Renglon) => r.sku.trim() !== '' && r.producto_id === null;
const repetido = (r: Renglon) => r.producto_id !== null && renglones.filter((x) => x.producto_id === r.producto_id).length > 1;

function alternarDestino(id: number) {
    if (activos.has(id)) {
        if (activos.size > 1) activos.delete(id);
    } else {
        activos.add(id);
    }
}

async function agregar() {
    renglones.push(nuevo());
    await nextTick();
    document.getElementById(`sku-${renglones.at(-1)!.clave}`)?.focus();
}
function quitar(i: number) {
    renglones.splice(i, 1);
    if (!renglones.length) renglones.push(nuevo());
}

const totalRenglon = (r: Renglon) => columnas.value.reduce((s, d) => s + (r.cantidades[d.id] || 0), 0);
const totalDestino = (id: number) => renglones.reduce((s, r) => s + (r.cantidades[id] || 0), 0);
const totalGeneral = computed(() => renglones.reduce((s, r) => s + totalRenglon(r), 0));
const excede = (r: Renglon) => {
    const p = producto(r);
    return p ? Math.max(0, totalRenglon(r) - p.disponible) : 0;
};
const conExceso = computed(() => renglones.filter((r) => excede(r) > 0).length);

const celdas = computed(() =>
    renglones.flatMap((r) =>
        r.producto_id
            ? columnas.value
                  .filter((d) => (r.cantidades[d.id] || 0) > 0)
                  .map((d) => ({ producto_id: r.producto_id, destino_id: d.id, cantidad: r.cantidades[d.id] }))
            : [],
    ),
);
const destinosConPiezas = computed(() => columnas.value.filter((d) => totalDestino(d.id) > 0).length);
const listo = computed(
    () => form.propietario.trim() !== '' && celdas.value.length > 0 && !renglones.some((r) => desconocido(r) || repetido(r)),
);
const errorGeneral = computed(() => {
    const e = form.errors as Record<string, string>;
    return e.celdas ?? Object.entries(e).find(([k]) => k.startsWith('celdas.'))?.[1];
});

function guardar() {
    form.transform((d) => ({ ...d, celdas: celdas.value })).post('/pedidos');
}
</script>

<template>
    <Head title="Nuevo pedido" />
    <AppLayout>
        <Link href="/pedidos" class="mb-2 inline-block text-sm text-ink-2 hover:underline">Pedidos y salida</Link>
        <Encabezado titulo="Nuevo pedido" descripcion="Reparto por destino: un renglón por SKU y una columna por tienda o almacén." />

        <form class="space-y-6" @submit.prevent="guardar">
            <section class="panel grid gap-5 p-5 md:grid-cols-[1.4fr_0.6fr]">
                <div>
                    <label for="propietario" class="etiqueta">Propietario de la mercancía</label>
                    <input id="propietario" v-model="form.propietario" list="lista-propietarios" class="campo" autocomplete="off" required />
                    <datalist id="lista-propietarios">
                        <option v-for="p in propietarios" :key="p" :value="p" />
                    </datalist>
                    <p v-if="form.errors.propietario" class="mt-1.5 text-sm text-bad">{{ form.errors.propietario }}</p>
                </div>
                <div>
                    <label for="compromiso" class="etiqueta">Fecha compromiso</label>
                    <input id="compromiso" v-model="form.fecha_compromiso" type="date" class="campo" required />
                    <p v-if="form.errors.fecha_compromiso" class="mt-1.5 text-sm text-bad">{{ form.errors.fecha_compromiso }}</p>
                </div>
                <fieldset class="md:col-span-2">
                    <legend class="etiqueta">Destinos del pedido</legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="d in destinos"
                            :key="d.id"
                            type="button"
                            :aria-pressed="activos.has(d.id)"
                            class="flex min-h-10 items-center gap-2 rounded-ui border px-3 text-sm transition-colors"
                            :class="activos.has(d.id) ? 'border-primary bg-surface font-semibold text-ink' : 'border-line bg-surface-2 text-ink-3 hover:text-ink-2'"
                            @click="alternarDestino(d.id)"
                        >
                            <Check v-if="activos.has(d.id)" class="size-4 text-primary" aria-hidden="true" />
                            <span class="font-id">{{ d.codigo }}</span>
                            <span class="font-normal text-ink-2">{{ d.nombre }}</span>
                        </button>
                    </div>
                </fieldset>
            </section>

            <section class="panel">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
                    <h2 class="text-lg font-bold">Reparto por destino</h2>
                    <p class="num text-sm text-ink-2">
                        {{ renglones.filter((r) => r.producto_id).length }} SKU · {{ destinosConPiezas }} destinos ·
                        <b class="text-ink">{{ n(totalGeneral) }}</b> piezas
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th class="min-w-56">SKU</th>
                                <th class="der">Disponible</th>
                                <th v-for="d in columnas" :key="d.id" class="der min-w-28">
                                    <span class="font-id">{{ d.codigo }}</span>
                                    <span class="block text-[0.72rem] font-normal text-ink-3">{{ d.nombre }}</span>
                                </th>
                                <th class="der">Total</th>
                                <th class="w-12"><span class="sr-only">Quitar</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in renglones" :key="r.clave" class="align-top">
                                <td>
                                    <input
                                        :id="`sku-${r.clave}`"
                                        v-model="r.sku"
                                        list="lista-skus"
                                        class="campo font-id uppercase"
                                        :class="{ 'border-bad': desconocido(r) || repetido(r) }"
                                        placeholder="Busca por SKU"
                                        autocomplete="off"
                                        :aria-label="`SKU del renglón ${i + 1}`"
                                        @change="elegirSku(r)"
                                        @blur="elegirSku(r)"
                                    />
                                    <p v-if="desconocido(r)" class="mt-1 text-sm text-bad">No está en el catálogo.</p>
                                    <p v-else-if="repetido(r)" class="mt-1 text-sm text-bad">Este SKU ya está en otro renglón.</p>
                                    <p v-else-if="producto(r)" class="mt-1 text-xs text-ink-2">{{ producto(r)!.descripcion }}</p>
                                </td>
                                <td class="der pt-5 text-ink-2">{{ producto(r) ? n(producto(r)!.disponible) : '—' }}</td>
                                <td v-for="d in columnas" :key="d.id" class="der">
                                    <input
                                        v-model.number="r.cantidades[d.id]"
                                        type="number"
                                        min="0"
                                        inputmode="numeric"
                                        class="campo num text-right"
                                        :aria-label="`Piezas de ${r.sku || 'renglón ' + (i + 1)} para ${d.codigo}`"
                                    />
                                </td>
                                <td class="der pt-5">
                                    <span class="font-semibold" :class="excede(r) ? 'text-bad' : ''">{{ n(totalRenglon(r)) }}</span>
                                    <p v-if="excede(r)" class="mt-1 text-xs whitespace-nowrap text-bad">faltan {{ n(excede(r)) }}</p>
                                </td>
                                <td>
                                    <button type="button" class="mt-1 rounded-ui p-2 text-ink-3 hover:bg-surface-2 hover:text-bad" :aria-label="`Quitar renglón ${i + 1}`" @click="quitar(i)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-surface-2 font-semibold">
                                <td class="px-3 py-3">Total por destino</td>
                                <td />
                                <td v-for="d in columnas" :key="d.id" class="num px-3 py-3 text-right">{{ n(totalDestino(d.id)) }}</td>
                                <td class="num px-3 py-3 text-right">{{ n(totalGeneral) }}</td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                    <datalist id="lista-skus">
                        <option v-for="p in productos" :key="p.id" :value="p.sku">{{ p.descripcion }} · {{ n(p.disponible) }} disponibles</option>
                    </datalist>
                </div>
                <div class="border-t border-line px-5 py-3">
                    <button type="button" class="btn btn-secundario" @click="agregar"><Plus class="size-4" />Agregar SKU</button>
                </div>
            </section>

            <p v-if="conExceso" class="flex items-start gap-2 rounded-ui border border-warn/30 bg-warn-bg px-4 py-3 text-sm text-warn">
                <TriangleAlert class="mt-0.5 size-4 flex-none" aria-hidden="true" />
                <span>{{ conExceso === 1 ? 'Un SKU pide' : `${conExceso} SKU piden` }} más de lo disponible en racks. El pedido se puede guardar, pero no se podrá liberar a surtido hasta que haya existencia.</span>
            </p>
            <p v-if="errorGeneral" class="text-bad">{{ errorGeneral }}</p>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <Link href="/pedidos" class="btn btn-secundario">Cancelar</Link>
                <button type="submit" class="btn btn-primario" :disabled="!listo || form.processing">
                    {{ form.processing ? 'Guardando…' : `Crear pedido · ${n(totalGeneral)} piezas` }}
                </button>
            </div>

            <PreguntaAbierta>¿El propietario manda el pedido ya repartido por tienda, o lo reparte la responsable? ¿Qué pasa si no hay existencia suficiente: se surte parcial o se espera?</PreguntaAbierta>
        </form>
    </AppLayout>
</template>
