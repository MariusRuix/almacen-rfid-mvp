<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import Vacio from '@/Components/Vacio.vue';
import { useCompartido } from '@/composables/useCompartido';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosRecepcion } from '@/lib/etiquetas';
import { conSigno, fecha, n } from '@/lib/formato';
import type { Pedimento } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FileSpreadsheet, Upload } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    recepciones: {
        id: number; folio: string; estado: string; origen: string; fecha_esperada: string;
        pedimento: Pedimento; esperadas: number; confirmadas: number | null; por_ubicar: number;
    }[];
}>();

const { esResponsable } = useCompartido();
const mostrarImportar = ref(false);
const form = useForm<{ archivo: File | null; pedimento: string; contenedor: string; fecha_esperada: string }>({
    archivo: null,
    pedimento: '',
    contenedor: '',
    fecha_esperada: new Date().toISOString().slice(0, 10),
});

function importar() {
    form.post('/recepciones/importar', { forceFormData: true });
}
</script>

<template>
    <Head title="Recepción" />
    <AppLayout>
        <Encabezado titulo="Recepción" descripcion="Lo que el propietario dijo que llegaría contra lo que se leyó y se confirmó en piso.">
            <template v-if="esResponsable">
                <a href="/recepciones/plantilla" class="btn btn-secundario"><FileSpreadsheet class="size-4" />Plantilla Excel</a>
                <button type="button" class="btn btn-primario" @click="mostrarImportar = !mostrarImportar"><Upload class="size-4" />Importar Excel</button>
            </template>
        </Encabezado>

        <form v-if="mostrarImportar" class="panel mb-6 grid gap-4 p-5 md:grid-cols-[1.4fr_1fr_1fr_0.8fr_auto] md:items-end" @submit.prevent="importar">
            <div>
                <label class="etiqueta" for="archivo">Archivo del propietario (SKU, cajas, piezas por caja)</label>
                <input id="archivo" type="file" accept=".xlsx,.xls,.csv" class="campo py-1.5" required @input="form.archivo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                <p v-if="form.errors.archivo" class="mt-1 text-sm text-bad">{{ form.errors.archivo }}</p>
            </div>
            <div>
                <label class="etiqueta" for="pedimento">Pedimento</label>
                <input id="pedimento" v-model="form.pedimento" class="campo font-id" placeholder="26 16 3156 6000000" required />
            </div>
            <div>
                <label class="etiqueta" for="contenedor">Contenedor</label>
                <input id="contenedor" v-model="form.contenedor" class="campo font-id" placeholder="TXAU1234567" />
            </div>
            <div>
                <label class="etiqueta" for="fecha">Llega</label>
                <input id="fecha" v-model="form.fecha_esperada" type="date" class="campo" required />
            </div>
            <button type="submit" class="btn btn-primario" :disabled="form.processing">Crear recepción</button>
        </form>

        <section class="panel overflow-x-auto">
            <table v-if="recepciones.length" class="tabla">
                <thead>
                    <tr><th>Folio</th><th>Pedimento</th><th>Llega</th><th>Origen</th><th class="der">Esperadas</th><th class="der">Confirmadas</th><th class="der">Diferencia</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    <tr v-for="r in recepciones" :key="r.id">
                        <td><Link :href="`/recepciones/${r.id}`" class="font-id font-semibold text-primary hover:underline">{{ r.folio }}</Link></td>
                        <td><IdSegmentado :valor="r.pedimento.numero" tipo="pedimento" /></td>
                        <td class="text-ink-2">{{ fecha(r.fecha_esperada) }}</td>
                        <td class="text-ink-2">{{ r.origen }}</td>
                        <td class="der">{{ n(r.esperadas) }}</td>
                        <td class="der">{{ n(r.confirmadas) }}</td>
                        <td class="der font-semibold" :class="r.confirmadas === null ? 'text-ink-3' : r.confirmadas === r.esperadas ? 'text-ok' : 'text-bad'">
                            {{ r.confirmadas === null ? '—' : conSigno(r.confirmadas - r.esperadas) }}
                        </td>
                        <td>
                            <Chip :valor="r.estado" :mapa="estadosRecepcion" />
                            <span v-if="r.estado === 'cerrada' && r.por_ubicar" class="ml-2 text-xs font-semibold text-warn">{{ r.por_ubicar }} por ubicar</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else titulo="Sin recepciones">Importa el Excel del propietario para registrar lo que va a llegar.</Vacio>
        </section>
    </AppLayout>
</template>
