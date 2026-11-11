<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { zonas } from '@/lib/etiquetas';
import { n } from '@/lib/formato';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface U { id: number; codigo: string; zona: string; capacidad: number; piezas: number; pedimentos: number }
const props = defineProps<{ ubicaciones: U[] }>();

const pasillos = computed(() => {
    const racks = props.ubicaciones.filter((u) => u.zona === 'rack');
    const porPasillo: Record<string, U[]> = {};
    for (const u of racks) (porPasillo[u.codigo.split('-')[0]] ??= []).push(u);
    return Object.entries(porPasillo).map(([pasillo, us]) => {
        const modulos = [...new Set(us.map((u) => u.codigo.split('-')[1]))];
        const niveles = [...new Set(us.map((u) => u.codigo.split('-')[2]))].sort().reverse();
        return { pasillo, modulos, niveles, celda: (m: string, nv: string) => us.find((u) => u.codigo === `${pasillo}-${m}-${nv}`) };
    });
});
const otras = computed(() => props.ubicaciones.filter((u) => u.zona !== 'rack'));
const ocupacion = (u: U) => Math.min(100, Math.round((u.piezas / u.capacidad) * 100));
</script>

<template>
    <Head title="Mapa de ubicaciones" />
    <AppLayout>
        <Encabezado titulo="Mapa de ubicaciones" descripcion="Ocupación por rack y nivel. Una ubicación con más de un pedimento se marca para revisarla al surtir." />

        <div class="grid gap-6 xl:grid-cols-2">
            <section v-for="p in pasillos" :key="p.pasillo" class="panel p-5">
                <h2 class="mb-4 text-lg font-bold">Pasillo {{ p.pasillo }}</h2>
                <div class="grid gap-2" :style="{ gridTemplateColumns: `2.5rem repeat(${p.modulos.length}, minmax(0, 1fr))` }">
                    <span />
                    <span v-for="m in p.modulos" :key="m" class="text-center text-xs font-semibold text-ink-3">Módulo {{ m }}</span>
                    <template v-for="nv in p.niveles" :key="nv">
                        <span class="self-center text-xs font-semibold text-ink-3">Nivel {{ nv }}</span>
                        <template v-for="m in p.modulos" :key="m + nv">
                            <Link
                                v-if="p.celda(m, nv)"
                                :href="`/inventario?ubicacion_id=${p.celda(m, nv)!.id}`"
                                class="group relative flex min-h-[4.5rem] flex-col justify-between overflow-hidden rounded-ui border border-line bg-surface-2 p-2 hover:border-line-strong"
                            >
                                <span class="absolute inset-x-0 bottom-0 bg-primary/15 transition-all" :style="{ height: ocupacion(p.celda(m, nv)!) + '%' }" aria-hidden="true" />
                                <span class="relative font-id text-xs font-semibold">{{ p.celda(m, nv)!.codigo }}</span>
                                <span class="relative flex items-baseline justify-between text-xs">
                                    <span class="num font-semibold">{{ n(p.celda(m, nv)!.piezas) }}</span>
                                    <span v-if="p.celda(m, nv)!.pedimentos > 1" class="rounded bg-warn-bg px-1 font-semibold text-warn">{{ p.celda(m, nv)!.pedimentos }} ped.</span>
                                </span>
                            </Link>
                        </template>
                    </template>
                </div>
            </section>
        </div>

        <section class="panel mt-6 overflow-x-auto">
            <table class="tabla">
                <thead><tr><th>Ubicación</th><th>Zona</th><th class="der">Piezas</th><th class="der">Capacidad</th><th class="der">Pedimentos</th></tr></thead>
                <tbody>
                    <tr v-for="u in otras" :key="u.id">
                        <td><Link :href="`/inventario?ubicacion_id=${u.id}`"><IdSegmentado :valor="u.codigo" tipo="ubicacion" /></Link></td>
                        <td class="text-ink-2">{{ zonas[u.zona] ?? u.zona }}</td>
                        <td class="der font-semibold">{{ n(u.piezas) }}</td>
                        <td class="der text-ink-2">{{ n(u.capacidad) }}</td>
                        <td class="der">{{ u.pedimentos }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
