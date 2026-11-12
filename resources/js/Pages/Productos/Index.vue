<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Vacio from '@/Components/Vacio.vue';
import Girando from '@/Components/Girando.vue';
import { useCompartido } from '@/composables/useCompartido';
import { confirmar } from '@/composables/useConfirmar';
import AppLayout from '@/Layouts/AppLayout.vue';
import { n } from '@/lib/formato';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

interface Producto {
    id: number;
    sku: string;
    referencia: string | null;
    descripcion: string;
    upc: string | null;
    epc_prefijo: string;
    piezas_por_caja: number;
    accesorio: string | null;
    existencia: number;
    en_uso: boolean;
}

const props = defineProps<{ productos: Producto[] }>();
const { esResponsable } = useCompartido();

const buscar = ref('');
const visibles = computed(() => {
    const q = buscar.value.trim().toLowerCase();
    if (!q) return props.productos;
    return props.productos.filter((p) => [p.sku, p.descripcion, p.referencia ?? '', p.upc ?? ''].some((v) => v.toLowerCase().includes(q)));
});

const editando = ref<Producto | null>(null);
const abierto = ref(false);
const vacio = { sku: '', referencia_interna: '', descripcion: '', upc: '', piezas_por_caja: 12 as number | null, accesorio: '' };
const form = useForm({ ...vacio });

async function abrir(p: Producto | null) {
    editando.value = p;
    form.clearErrors();
    form.defaults(
        p
            ? { sku: p.sku, referencia_interna: p.referencia ?? '', descripcion: p.descripcion, upc: p.upc ?? '', piezas_por_caja: p.piezas_por_caja, accesorio: p.accesorio ?? '' }
            : { ...vacio },
    );
    form.reset();
    abierto.value = true;
    await nextTick();
    document.getElementById(p ? 'descripcion' : 'sku')?.focus();
}
function cerrar() {
    abierto.value = false;
    editando.value = null;
}
function guardar() {
    const opciones = { preserveScroll: true, onSuccess: cerrar };
    if (editando.value) form.put(`/productos/${editando.value.id}`, opciones);
    else form.post('/productos', opciones);
}
const borrando = ref<number | null>(null);
async function borrar(p: Producto) {
    const ok = await confirmar({ titulo: `¿Borrar el producto ${p.sku}?`, mensaje: `${p.descripcion} deja de estar en el catálogo.`, aceptar: 'Borrar producto', tono: 'peligro' });
    if (ok) router.delete(`/productos/${p.id}`, { preserveScroll: true, onStart: () => (borrando.value = p.id), onFinish: () => (borrando.value = null) });
}
</script>

<template>
    <Head title="Productos" />
    <AppLayout>
        <Encabezado titulo="Productos" descripcion="Catálogo de SKU que se reciben, almacenan y surten. Recepciones y pedidos solo aceptan SKU de esta lista.">
            <button v-if="esResponsable && !abierto" type="button" class="btn btn-primario" @click="abrir(null)"><Plus class="size-4" />Nuevo producto</button>
        </Encabezado>

        <form v-if="abierto" class="panel mb-6 p-5" @submit.prevent="guardar">
            <h2 class="mb-4 text-lg font-bold">{{ editando ? `Editar ${editando.sku}` : 'Nuevo producto' }}</h2>
            <div class="grid gap-5 md:grid-cols-[1fr_0.7fr_2fr]">
                <div>
                    <label for="sku" class="etiqueta">SKU</label>
                    <input id="sku" v-model="form.sku" class="campo font-id uppercase placeholder:normal-case" placeholder="CAM-OXF-L" maxlength="30" autocomplete="off" required />
                    <p v-if="form.errors.sku" class="mt-1.5 text-sm text-bad">{{ form.errors.sku }}</p>
                    <p v-else-if="editando?.en_uso" class="mt-1.5 text-xs text-ink-3">Ya tiene movimientos: cambiar el SKU lo cambia también en el historial.</p>
                </div>
                <div>
                    <label for="referencia" class="etiqueta">Referencia interna <span class="font-normal text-ink-3">(opcional)</span></label>
                    <input id="referencia" v-model="form.referencia_interna" class="campo font-id" placeholder="R-109" maxlength="20" autocomplete="off" />
                    <p v-if="form.errors.referencia_interna" class="mt-1.5 text-sm text-bad">{{ form.errors.referencia_interna }}</p>
                </div>
                <div>
                    <label for="descripcion" class="etiqueta">Descripción</label>
                    <input id="descripcion" v-model="form.descripcion" class="campo" placeholder="Camisa oxford manga larga L" maxlength="160" required />
                    <p v-if="form.errors.descripcion" class="mt-1.5 text-sm text-bad">{{ form.errors.descripcion }}</p>
                </div>
                <div>
                    <label for="upc" class="etiqueta">Código de barras <span class="font-normal text-ink-3">(opcional)</span></label>
                    <input id="upc" v-model="form.upc" class="campo font-id" inputmode="numeric" placeholder="7501000000109" maxlength="14" autocomplete="off" />
                    <p v-if="form.errors.upc" class="mt-1.5 text-sm text-bad">{{ form.errors.upc }}</p>
                </div>
                <div>
                    <label for="ppc" class="etiqueta">Piezas por caja</label>
                    <input id="ppc" v-model.number="form.piezas_por_caja" type="number" min="1" class="campo num text-right" required />
                    <p v-if="form.errors.piezas_por_caja" class="mt-1.5 text-sm text-bad">{{ form.errors.piezas_por_caja }}</p>
                </div>
                <div>
                    <label for="accesorio" class="etiqueta">Accesorio que debe traer <span class="font-normal text-ink-3">(opcional)</span></label>
                    <input id="accesorio" v-model="form.accesorio" class="campo" placeholder="cinturón, correa, funda…" maxlength="60" />
                    <p class="mt-1.5 text-xs text-ink-3">Si lo lleva, en la recepción se pueden marcar piezas que llegaron sin él.</p>
                </div>
            </div>
            <p v-if="editando" class="mt-4 text-sm text-ink-2">Prefijo RFID: <span class="font-id">{{ editando.epc_prefijo }}</span> (asignado por el sistema)</p>
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <button type="button" class="btn btn-secundario" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primario" :disabled="form.processing"><Girando v-if="form.processing" />{{ editando ? 'Guardar cambios' : 'Crear producto' }}</button>
            </div>
        </form>

        <div class="relative mb-4 max-w-sm">
            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-3" aria-hidden="true" />
            <input v-model="buscar" type="search" class="campo pl-9" placeholder="Buscar SKU, descripción, referencia…" aria-label="Buscar producto" />
        </div>

        <section class="panel overflow-x-auto">
            <table v-if="visibles.length" class="tabla">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Descripción</th>
                        <th>Código de barras</th>
                        <th class="der">Pz/caja</th>
                        <th>Accesorio</th>
                        <th class="der">Existencia</th>
                        <th v-if="esResponsable"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in visibles" :key="p.id" :class="{ 'bg-surface-2': editando?.id === p.id }">
                        <td>
                            <IdSegmentado :valor="p.sku" tipo="sku" />
                            <p v-if="p.referencia" class="font-id text-xs text-ink-3">{{ p.referencia }}</p>
                        </td>
                        <td>{{ p.descripcion }}</td>
                        <td class="font-id text-sm text-ink-2">{{ p.upc ?? '—' }}</td>
                        <td class="der">{{ p.piezas_por_caja }}</td>
                        <td class="text-ink-2">{{ p.accesorio ?? '—' }}</td>
                        <td class="der">
                            <Link v-if="p.existencia" :href="`/inventario?buscar=${encodeURIComponent(p.sku)}`" class="font-semibold text-primary hover:underline">{{ n(p.existencia) }}</Link>
                            <span v-else class="text-ink-3">0</span>
                        </td>
                        <td v-if="esResponsable" class="whitespace-nowrap text-right">
                            <button type="button" class="rounded-ui p-2 text-ink-2 hover:bg-surface-2 hover:text-ink" :aria-label="`Editar ${p.sku}`" title="Editar" @click="abrir(p)">
                                <Pencil class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-ui p-2 text-ink-2 hover:bg-surface-2 hover:text-bad disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-ink-2"
                                :disabled="p.en_uso"
                                :aria-label="`Borrar ${p.sku}`"
                                :title="p.en_uso ? 'Ya tiene recepciones, pedidos o movimientos: no se puede borrar' : 'Borrar'"
                                @click="borrar(p)"
                            >
                                <Girando v-if="borrando === p.id" /><Trash2 v-else class="size-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else-if="buscar" titulo="Ningún producto coincide">Prueba con otra parte del SKU o de la descripción.</Vacio>
            <Vacio v-else titulo="Sin productos">Da de alta los SKU que maneja el almacén.</Vacio>
        </section>
    </AppLayout>
</template>
