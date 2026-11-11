<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { contenedorValido } from '@/lib/contenedor';
import { n } from '@/lib/formato';
import type { Pedimento, Producto } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, reactive } from 'vue';

const props = defineProps<{ productos: Producto[]; pedimentos: Pedimento[]; hoy: string }>();

interface Renglon {
    clave: number;
    sku: string;
    producto_id: number | null;
    cajas: number | null;
    piezas_por_caja: number | null;
}

let siguiente = 1;
const nuevo = (): Renglon => ({ clave: siguiente++, sku: '', producto_id: null, cajas: null, piezas_por_caja: null });
const renglones = reactive<Renglon[]>([nuevo()]);

const form = useForm({ pedimento: '', contenedor: '', fecha_esperada: props.hoy });

const porSku = computed(() => new Map(props.productos.map((p) => [p.sku.toUpperCase(), p])));
const producto = (r: Renglon) => props.productos.find((p) => p.id === r.producto_id) ?? null;

function elegirSku(r: Renglon) {
    const p = porSku.value.get(r.sku.trim().toUpperCase()) ?? null;
    r.producto_id = p?.id ?? null;
    if (p) {
        r.sku = p.sku;
        if (!r.piezas_por_caja) r.piezas_por_caja = p.piezas_por_caja;
    }
}
const desconocido = (r: Renglon) => r.sku.trim() !== '' && r.producto_id === null;
const repetido = (r: Renglon) => r.producto_id !== null && renglones.filter((x) => x.producto_id === r.producto_id).length > 1;

async function agregar() {
    renglones.push(nuevo());
    await nextTick();
    document.getElementById(`sku-${renglones.at(-1)!.clave}`)?.focus();
}
function quitar(i: number) {
    renglones.splice(i, 1);
    if (!renglones.length) renglones.push(nuevo());
}

// Pedimento existente: completa el contenedor
function elegirPedimento() {
    const digitos = form.pedimento.replace(/\D/g, '');
    const p = props.pedimentos.find((x) => x.numero.replace(/\D/g, '') === digitos);
    if (p && !form.contenedor) form.contenedor = p.contenedor ?? '';
}
const pedimentoExistente = computed(() => {
    const d = form.pedimento.replace(/\D/g, '');
    return props.pedimentos.find((x) => x.numero.replace(/\D/g, '') === d) ?? null;
});
const pedimentoFormateado = computed(() => {
    const d = form.pedimento.replace(/\D/g, '');
    return d.length === 15 ? `${d.slice(0, 2)} ${d.slice(2, 4)} ${d.slice(4, 8)} ${d.slice(8)}` : null;
});
const contenedorOk = computed(() => (form.contenedor ? contenedorValido(form.contenedor) : null));

const totales = computed(() => ({
    skus: renglones.filter((r) => r.producto_id).length,
    cajas: renglones.reduce((s, r) => s + (r.cajas ?? 0), 0),
    piezas: renglones.reduce((s, r) => s + (r.cajas ?? 0) * (r.piezas_por_caja ?? 0), 0),
}));
const listo = computed(
    () =>
        pedimentoFormateado.value !== null &&
        contenedorOk.value !== false &&
        renglones.length > 0 &&
        renglones.every((r) => r.producto_id && r.cajas && r.piezas_por_caja && !repetido(r)),
);

const errorGeneral = computed(() => (form.errors as Record<string, string>).lineas);
const errorDe = (i: number, campo: string) => (form.errors as Record<string, string>)[`lineas.${i}.${campo}`];

function guardar() {
    form.transform((d) => ({
        ...d,
        lineas: renglones.map((r) => ({ producto_id: r.producto_id, cajas: r.cajas, piezas_por_caja: r.piezas_por_caja })),
    })).post('/recepciones');
}
</script>

<template>
    <Head title="Captura manual" />
    <AppLayout>
        <Link href="/recepciones" class="mb-2 inline-block text-sm text-ink-2 hover:underline">Recepción</Link>
        <Encabezado titulo="Captura manual de recepción" descripcion="Para recepciones pequeñas o urgentes. Lo que llega en volumen conviene importarlo del Excel del propietario." />

        <form class="space-y-6" @submit.prevent="guardar">
            <section class="panel grid gap-5 p-5 md:grid-cols-[1.3fr_1fr_0.7fr]">
                <div>
                    <label for="pedimento" class="etiqueta">Pedimento</label>
                    <input
                        id="pedimento"
                        v-model="form.pedimento"
                        list="lista-pedimentos"
                        class="campo font-id"
                        placeholder="26 16 3156 6000000"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                        @change="elegirPedimento"
                    />
                    <datalist id="lista-pedimentos">
                        <option v-for="p in pedimentos" :key="p.id" :value="p.numero">{{ p.contenedor }}</option>
                    </datalist>
                    <p v-if="form.errors.pedimento" class="mt-1.5 text-sm text-bad">{{ form.errors.pedimento }}</p>
                    <p v-else-if="pedimentoFormateado" class="mt-1.5 flex flex-wrap items-center gap-2 text-sm text-ink-2">
                        <IdSegmentado :valor="pedimentoFormateado" tipo="pedimento" tam="sm" />
                        <span>{{ pedimentoExistente ? 'ya registrado' : 'se registrará como nuevo' }}</span>
                    </p>
                    <p v-else class="mt-1.5 text-sm text-ink-3">15 dígitos: año, aduana, patente y número.</p>
                </div>
                <div>
                    <label for="contenedor" class="etiqueta">Contenedor <span class="font-normal text-ink-3">(opcional)</span></label>
                    <input id="contenedor" v-model="form.contenedor" class="campo font-id uppercase placeholder:normal-case" placeholder="TXAU1047728" autocomplete="off" />
                    <p v-if="form.errors.contenedor" class="mt-1.5 text-sm text-bad">{{ form.errors.contenedor }}</p>
                    <p v-else-if="contenedorOk === true" class="mt-1.5 flex items-center gap-1.5 text-sm text-ok"><CircleCheck class="size-4" aria-hidden="true" />Dígito verificador correcto</p>
                    <p v-else-if="contenedorOk === false" class="mt-1.5 flex items-center gap-1.5 text-sm text-bad"><CircleAlert class="size-4" aria-hidden="true" />El dígito verificador no coincide</p>
                    <p v-else class="mt-1.5 text-sm text-ink-3">4 letras y 7 dígitos.</p>
                </div>
                <div>
                    <label for="fecha" class="etiqueta">Llega</label>
                    <input id="fecha" v-model="form.fecha_esperada" type="date" class="campo" required />
                    <p v-if="form.errors.fecha_esperada" class="mt-1.5 text-sm text-bad">{{ form.errors.fecha_esperada }}</p>
                </div>
            </section>

            <section class="panel">
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
                    <h2 class="text-lg font-bold">Lo que se espera recibir</h2>
                    <p class="num text-sm text-ink-2">
                        {{ totales.skus }} SKU · {{ n(totales.cajas) }} cajas · <b class="text-ink">{{ n(totales.piezas) }}</b> piezas
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th class="min-w-56">SKU</th>
                                <th class="der w-32">Cajas</th>
                                <th class="der w-36">Piezas por caja</th>
                                <th class="der w-28">Piezas</th>
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
                                        class="campo font-id uppercase placeholder:normal-case"
                                        :class="{ 'border-bad': desconocido(r) || repetido(r) }"
                                        placeholder="Busca por SKU"
                                        autocomplete="off"
                                        :aria-label="`SKU del renglón ${i + 1}`"
                                        @change="elegirSku(r)"
                                        @blur="elegirSku(r)"
                                    />
                                    <p v-if="desconocido(r)" class="mt-1 text-sm text-bad">No está en el catálogo.</p>
                                    <p v-else-if="repetido(r)" class="mt-1 text-sm text-bad">Este SKU ya está en otro renglón.</p>
                                    <p v-else-if="errorDe(i, 'producto_id')" class="mt-1 text-sm text-bad">{{ errorDe(i, 'producto_id') }}</p>
                                    <p v-else-if="producto(r)" class="mt-1 text-xs text-ink-2">{{ producto(r)!.descripcion }}<template v-if="producto(r)!.accesorio"> · lleva {{ producto(r)!.accesorio }}</template></p>
                                </td>
                                <td class="der">
                                    <input v-model.number="r.cajas" type="number" min="1" inputmode="numeric" class="campo num text-right" :aria-label="`Cajas del renglón ${i + 1}`" />
                                    <p v-if="errorDe(i, 'cajas')" class="mt-1 text-left text-sm text-bad">{{ errorDe(i, 'cajas') }}</p>
                                </td>
                                <td class="der">
                                    <input v-model.number="r.piezas_por_caja" type="number" min="1" inputmode="numeric" class="campo num text-right" :aria-label="`Piezas por caja del renglón ${i + 1}`" />
                                    <p v-if="errorDe(i, 'piezas_por_caja')" class="mt-1 text-left text-sm text-bad">{{ errorDe(i, 'piezas_por_caja') }}</p>
                                </td>
                                <td class="der pt-5 font-semibold">{{ r.cajas && r.piezas_por_caja ? n(r.cajas * r.piezas_por_caja) : '—' }}</td>
                                <td>
                                    <button type="button" class="mt-1 rounded-ui p-2 text-ink-3 hover:bg-surface-2 hover:text-bad" :aria-label="`Quitar renglón ${i + 1}`" @click="quitar(i)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <datalist id="lista-skus">
                        <option v-for="p in productos" :key="p.id" :value="p.sku">{{ p.descripcion }}</option>
                    </datalist>
                </div>
                <div class="border-t border-line px-5 py-3">
                    <button type="button" class="btn btn-secundario" @click="agregar"><Plus class="size-4" />Agregar SKU</button>
                </div>
            </section>

            <p v-if="errorGeneral" class="text-bad">{{ errorGeneral }}</p>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <Link href="/recepciones" class="btn btn-secundario">Cancelar</Link>
                <button type="submit" class="btn btn-primario" :disabled="!listo || form.processing">
                    {{ form.processing ? 'Guardando…' : `Crear recepción · ${n(totales.piezas)} piezas` }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
