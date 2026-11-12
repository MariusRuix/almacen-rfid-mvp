<script setup lang="ts">
import AvancePedido from '@/Components/AvancePedido.vue';
import Encabezado from '@/Components/Encabezado.vue';
import Vacio from '@/Components/Vacio.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fecha, n } from '@/lib/formato';
import { useCompartido } from '@/composables/useCompartido';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

defineProps<{
    pedidos: {
        id: number; folio: string; propietario: string; estado: string; fecha_compromiso: string; destinos: number; piezas: number;
        avance: { tareas: number; tareas_hechas: number; separados: number; verificados: number; con_diferencia: number };
    }[];
}>();

const { esResponsable } = useCompartido();
</script>

<template>
    <Head title="Pedidos" />
    <AppLayout>
        <Encabezado titulo="Pedidos y salida" descripcion="Pedidos del propietario repartidos por destino: toma, separación, revisión y salida.">
            <Link v-if="esResponsable" href="/pedidos/nuevo" class="btn btn-primario"><Plus class="size-4" />Nuevo pedido</Link>
        </Encabezado>
        <section class="panel overflow-x-auto">
            <table v-if="pedidos.length" class="tabla">
                <thead><tr><th>Folio</th><th>Propietario</th><th>Compromiso</th><th class="der">Destinos</th><th class="der">Piezas</th><th class="min-w-56">Avance</th></tr></thead>
                <tbody>
                    <tr v-for="p in pedidos" :key="p.id">
                        <td><Link :href="`/pedidos/${p.id}`" class="font-id font-semibold text-primary hover:underline">{{ p.folio }}</Link></td>
                        <td>{{ p.propietario }}</td>
                        <td class="text-ink-2">{{ fecha(p.fecha_compromiso) }}</td>
                        <td class="der">{{ p.destinos }}</td>
                        <td class="der font-semibold">{{ n(p.piezas) }}</td>
                        <td><AvancePedido :avance="{ estado: p.estado, destinos: p.destinos, ...p.avance }" compacto /></td>
                    </tr>
                </tbody>
            </table>
            <Vacio v-else titulo="Sin pedidos">Los pedidos del propietario aparecerán aquí.</Vacio>
        </section>
    </AppLayout>
</template>
