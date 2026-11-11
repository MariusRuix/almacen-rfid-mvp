<script setup lang="ts">
import Chip from '@/Components/Chip.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PisoLayout from '@/Layouts/PisoLayout.vue';
import { estadosPedido, estadosRecepcion } from '@/lib/etiquetas';
import { n } from '@/lib/formato';
import type { Pedimento } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownToLine, ChevronRight, ClipboardList } from 'lucide-vue-next';

defineProps<{
    recepciones: { id: number; folio: string; estado: string; por_ubicar: number; pedimento: Pedimento; piezas: number }[];
    pedidos: { id: number; folio: string; estado: string; tareas: number; pendientes: number; destinos: number }[];
}>();

function hrefPedido(p: { id: number; estado: string }) {
    if (p.estado === 'en_surtido') return `/operador/pedidos/${p.id}/surtido`;
    if (p.estado === 'en_separacion') return `/operador/pedidos/${p.id}/separacion`;
    return `/pedidos/${p.id}`;
}
function accionPedido(p: { estado: string; pendientes: number; tareas: number; destinos: number }) {
    if (p.estado === 'en_surtido') return `Tomar piezas · faltan ${p.pendientes} de ${p.tareas}`;
    if (p.estado === 'en_separacion') return `Separar en ${p.destinos} destinos`;
    return 'Revisar bultos con el lector';
}
</script>

<template>
    <Head title="Vista del operador" />
    <PisoLayout titulo="Mi trabajo">
        <section class="mb-6">
            <h2 class="mb-3 flex items-center gap-2 text-lg font-bold"><ClipboardList class="size-5" aria-hidden="true" />Pedidos</h2>
            <ul class="space-y-3">
                <li v-for="p in pedidos" :key="p.id">
                    <Link :href="hrefPedido(p)" class="panel flex min-h-20 items-center gap-4 px-5 py-4 hover:border-line-strong">
                        <div class="flex-1">
                            <p class="flex items-center gap-3"><span class="font-id text-lg font-bold">{{ p.folio }}</span><Chip :valor="p.estado" :mapa="estadosPedido" /></p>
                            <p class="mt-1 text-ink-2">{{ accionPedido(p) }}</p>
                        </div>
                        <ChevronRight class="size-7 text-ink-3" aria-hidden="true" />
                    </Link>
                </li>
                <li v-if="!pedidos.length" class="panel px-5 py-6 text-ink-2">No hay pedidos liberados. La responsable los libera desde el panel.</li>
            </ul>
        </section>

        <section>
            <h2 class="mb-3 flex items-center gap-2 text-lg font-bold"><ArrowDownToLine class="size-5" aria-hidden="true" />Recepciones</h2>
            <ul class="space-y-3">
                <li v-for="r in recepciones" :key="r.id">
                    <Link :href="`/recepciones/${r.id}`" class="panel flex min-h-20 items-center gap-4 px-5 py-4 hover:border-line-strong">
                        <div class="flex-1">
                            <p class="flex items-center gap-3"><span class="font-id text-lg font-bold">{{ r.folio }}</span><Chip :valor="r.estado" :mapa="estadosRecepcion" /></p>
                            <p class="mt-1 text-ink-2">
                                <template v-if="r.estado === 'cerrada'">Ubicar {{ r.por_ubicar }} líneas</template>
                                <template v-else>Leer y confirmar <span class="num">{{ n(r.piezas) }}</span> piezas</template>
                            </p>
                            <IdSegmentado :valor="r.pedimento.numero" tipo="pedimento" tam="sm" class="mt-1" />
                        </div>
                        <ChevronRight class="size-7 text-ink-3" aria-hidden="true" />
                    </Link>
                </li>
                <li v-if="!recepciones.length" class="panel px-5 py-6 text-ink-2">Sin recepciones pendientes.</li>
            </ul>
        </section>
    </PisoLayout>
</template>
