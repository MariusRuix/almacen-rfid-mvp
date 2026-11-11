<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import Vacio from '@/Components/Vacio.vue';
import { useCompartido } from '@/composables/useCompartido';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

interface Destino {
    id: number;
    codigo: string;
    nombre: string;
    tipo: 'tienda' | 'almacen';
    requiere_etiqueta_comercial: boolean;
    requiere_sensor: boolean;
    pedidos: number;
    abiertos: number;
}

defineProps<{ destinos: Destino[] }>();
const { esResponsable } = useCompartido();

const editando = ref<Destino | null>(null);
const abierto = ref(false);
const form = useForm({ codigo: '', nombre: '', tipo: 'tienda' as 'tienda' | 'almacen', requiere_etiqueta_comercial: true, requiere_sensor: false });

async function abrir(d: Destino | null) {
    editando.value = d;
    form.clearErrors();
    if (d) {
        form.defaults({ codigo: d.codigo, nombre: d.nombre, tipo: d.tipo, requiere_etiqueta_comercial: d.requiere_etiqueta_comercial, requiere_sensor: d.requiere_sensor });
    } else {
        form.defaults({ codigo: '', nombre: '', tipo: 'tienda', requiere_etiqueta_comercial: true, requiere_sensor: false });
    }
    form.reset();
    abierto.value = true;
    await nextTick();
    document.getElementById('codigo')?.focus();
}
function cerrar() {
    abierto.value = false;
    editando.value = null;
}
function guardar() {
    const opciones = { preserveScroll: true, onSuccess: cerrar };
    if (editando.value) form.put(`/destinos/${editando.value.id}`, opciones);
    else form.post('/destinos', opciones);
}
function borrar(d: Destino) {
    if (window.confirm(`¿Borrar el destino ${d.codigo} · ${d.nombre}?`)) {
        router.delete(`/destinos/${d.id}`, { preserveScroll: true });
    }
}
// Un almacén normalmente no lleva etiqueta comercial; se propone al cambiar el tipo.
function cambiarTipo() {
    if (form.tipo === 'almacen') form.requiere_etiqueta_comercial = false;
}
</script>

<template>
    <Head title="Destinos" />
    <AppLayout>
        <Encabezado titulo="Destinos" descripcion="Tiendas y almacenes a los que se reparte. Cada destino define qué preparación lleva su mercancía.">
            <button v-if="esResponsable && !abierto" type="button" class="btn btn-primario" @click="abrir(null)"><Plus class="size-4" />Nuevo destino</button>
        </Encabezado>

        <form v-if="abierto" class="panel mb-6 p-5" @submit.prevent="guardar">
            <h2 class="mb-4 text-lg font-bold">{{ editando ? `Editar ${editando.codigo}` : 'Nuevo destino' }}</h2>
            <div class="grid gap-5 md:grid-cols-[0.6fr_1.4fr_0.8fr]">
                <div>
                    <label for="codigo" class="etiqueta">Código</label>
                    <input id="codigo" v-model="form.codigo" class="campo font-id uppercase placeholder:normal-case" placeholder="T-045" maxlength="12" autocomplete="off" required />
                    <p v-if="form.errors.codigo" class="mt-1.5 text-sm text-bad">{{ form.errors.codigo }}</p>
                </div>
                <div>
                    <label for="nombre" class="etiqueta">Nombre</label>
                    <input id="nombre" v-model="form.nombre" class="campo" placeholder="Tienda Puebla Angelópolis" maxlength="120" autocomplete="off" required />
                    <p v-if="form.errors.nombre" class="mt-1.5 text-sm text-bad">{{ form.errors.nombre }}</p>
                </div>
                <div>
                    <label for="tipo" class="etiqueta">Tipo</label>
                    <select id="tipo" v-model="form.tipo" class="campo" @change="cambiarTipo">
                        <option value="tienda">Tienda</option>
                        <option value="almacen">Almacén</option>
                    </select>
                    <p v-if="form.errors.tipo" class="mt-1.5 text-sm text-bad">{{ form.errors.tipo }}</p>
                </div>
            </div>

            <fieldset class="mt-5">
                <legend class="etiqueta">Preparación que lleva</legend>
                <div class="flex flex-wrap gap-3">
                    <span class="flex min-h-11 items-center gap-3 rounded-ui border border-line bg-surface-2 px-4 text-ink-2">
                        <Check class="size-4 text-ok" aria-hidden="true" />Etiqueta de importación <span class="text-xs text-ink-3">(siempre)</span>
                    </span>
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-ui border border-line px-4" :class="form.requiere_etiqueta_comercial ? 'bg-ok-bg' : 'bg-surface'">
                        <input v-model="form.requiere_etiqueta_comercial" type="checkbox" class="size-5 accent-[var(--ok)]" />Etiqueta comercial
                    </label>
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-ui border border-line px-4" :class="form.requiere_sensor ? 'bg-ok-bg' : 'bg-surface'">
                        <input v-model="form.requiere_sensor" type="checkbox" class="size-5 accent-[var(--ok)]" />Sensor
                    </label>
                </div>
                <p v-if="editando?.abiertos" class="mt-2 text-sm text-warn">
                    {{ editando.codigo }} tiene {{ editando.abiertos }} {{ editando.abiertos === 1 ? 'pedido abierto' : 'pedidos abiertos' }}: el cambio de preparación también les aplica.
                </p>
            </fieldset>

            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <button type="button" class="btn btn-secundario" @click="cerrar">Cancelar</button>
                <button type="submit" class="btn btn-primario" :disabled="form.processing">{{ editando ? 'Guardar cambios' : 'Crear destino' }}</button>
            </div>
        </form>

        <section class="panel overflow-x-auto">
            <table v-if="destinos.length" class="tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Preparación</th>
                        <th class="der">Pedidos</th>
                        <th v-if="esResponsable"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in destinos" :key="d.id" :class="{ 'bg-surface-2': editando?.id === d.id }">
                        <td class="font-id font-semibold">{{ d.codigo }}</td>
                        <td>{{ d.nombre }}</td>
                        <td class="text-ink-2">{{ d.tipo === 'tienda' ? 'Tienda' : 'Almacén' }}</td>
                        <td class="text-sm text-ink-2">
                            Importación<template v-if="d.requiere_etiqueta_comercial"> · comercial</template><template v-if="d.requiere_sensor"> · sensor</template>
                        </td>
                        <td class="der">
                            {{ d.pedidos }}
                            <span v-if="d.abiertos" class="ml-1 text-xs text-ink-3">({{ d.abiertos }} {{ d.abiertos === 1 ? 'abierto' : 'abiertos' }})</span>
                        </td>
                        <td v-if="esResponsable" class="whitespace-nowrap text-right">
                            <button type="button" class="rounded-ui p-2 text-ink-2 hover:bg-surface-2 hover:text-ink" :aria-label="`Editar ${d.codigo}`" title="Editar" @click="abrir(d)">
                                <Pencil class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-ui p-2 text-ink-2 hover:bg-surface-2 hover:text-bad disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-ink-2"
                                :disabled="d.pedidos > 0"
                                :aria-label="`Borrar ${d.codigo}`"
                                :title="d.pedidos > 0 ? 'Tiene pedidos: no se puede borrar' : 'Borrar'"
                                @click="borrar(d)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else titulo="Sin destinos">Da de alta las tiendas y almacenes a los que se reparte la mercancía.</Vacio>
        </section>
    </AppLayout>
</template>
