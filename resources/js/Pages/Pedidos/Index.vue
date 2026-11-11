<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import Encabezado from '@/Components/Encabezado.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosPedido } from '@/lib/etiquetas';
import { fecha, n } from '@/lib/formato';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    pedidos: { id: number; folio: string; propietario: string; estado: string; fecha_compromiso: string; destinos: number; piezas: number }[];
}>();
</script>

<template>
    <Head title="Pedidos" />
    <AppLayout>
        <Encabezado titulo="Pedidos y salida" descripcion="Pedidos del propietario repartidos por destino: toma, separación, revisión y salida." />
        <section class="panel overflow-x-auto">
            <table v-if="pedidos.length" class="tabla">
                <thead><tr><th>Folio</th><th>Propietario</th><th>Compromiso</th><th class="der">Destinos</th><th class="der">Piezas</th><th>Estado</th></tr></thead>
                <tbody>
                    <tr v-for="p in pedidos" :key="p.id">
                        <td><Link :href="`/pedidos/${p.id}`" class="font-id font-semibold text-primary hover:underline">{{ p.folio }}</Link></td>
                        <td>{{ p.propietario }}</td>
                        <td class="text-ink-2">{{ fecha(p.fecha_compromiso) }}</td>
                        <td class="der">{{ p.destinos }}</td>
                        <td class="der font-semibold">{{ n(p.piezas) }}</td>
                        <td><Chip :valor="p.estado" :mapa="estadosPedido" /></td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else titulo="Sin pedidos">Los pedidos del propietario aparecerán aquí.</Vacio>
        </section>
    </AppLayout>
</template>
