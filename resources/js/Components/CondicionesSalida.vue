<script setup lang="ts">
import type { PedidoDetalle } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Circle, CircleCheck, CircleX, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/** Qué falta para despachar: cada condición con su estado y dónde resolverla. */
const props = defineProps<{ pedido: PedidoDetalle; transporte: { transportista: string; placas: string; sello: string } }>();

type Estado = 'ok' | 'falta' | 'aviso' | 'pendiente';
interface Condicion {
    clave: string;
    estado: Estado;
    texto: string;
    detalle?: string;
    accion?: { texto: string; href: string; interna?: boolean };
    bloquea: boolean;
}

const condiciones = computed<Condicion[]>(() => {
    const d = props.pedido.destinos;
    const sinVerificar = d.filter((x) => x.estado !== 'verificado');
    const conDif = d.filter((x) => x.estado === 'con_diferencia');
    const actividades = d
        .map((x) => ({ x, faltan: x.actividades.filter((a) => !a.hecha).map((a) => a.etiqueta.toLowerCase()) }))
        .filter((v) => v.faltan.length);
    const t = props.transporte;
    const transporteOk = !!(t.transportista.trim() && t.placas.trim() && t.sello.trim());
    const fotosCarga = props.pedido.evidencias.filter((e) => e.momento === 'carga').length;

    return [
        {
            clave: 'verificados',
            estado: sinVerificar.length ? 'falta' : 'ok',
            texto: `${d.length - sinVerificar.length} de ${d.length} destinos verificados`,
            detalle: sinVerificar.length
                ? sinVerificar.map((x) => `${x.codigo}${x.estado === 'con_diferencia' ? ' (con diferencia)' : x.estado === 'pendiente' ? ' (sin separar)' : ' (sin revisar)'}`).join(', ')
                : undefined,
            accion: sinVerificar.length ? { texto: conDif.length ? 'Atender diferencia' : 'Revisar', href: `#destino-${(conDif[0] ?? sinVerificar[0]).id}`, interna: true } : undefined,
            bloquea: true,
        },
        {
            clave: 'actividades',
            estado: actividades.length ? 'falta' : 'ok',
            texto: actividades.length ? 'Falta preparación en algunos destinos' : 'Preparación completa (etiquetas y sensores)',
            detalle: actividades.length ? actividades.map((v) => `${v.x.codigo}: ${v.faltan.join(', ')}`).join(' · ') : undefined,
            accion: actividades.length ? { texto: 'Marcar', href: `#destino-${actividades[0].x.id}`, interna: true } : undefined,
            bloquea: true,
        },
        {
            clave: 'incidencias',
            estado: props.pedido.incidencias_abiertas ? 'aviso' : 'ok',
            texto: props.pedido.incidencias_abiertas
                ? `${props.pedido.incidencias_abiertas} ${props.pedido.incidencias_abiertas === 1 ? 'incidencia abierta' : 'incidencias abiertas'} del pedido`
                : 'Sin incidencias abiertas del pedido',
            detalle: props.pedido.incidencias_abiertas ? 'No impiden la salida; se pueden atender después.' : undefined,
            accion: props.pedido.incidencias_abiertas ? { texto: 'Ver', href: '/incidencias' } : undefined,
            bloquea: false,
        },
        {
            clave: 'evidencia',
            estado: fotosCarga ? 'ok' : 'aviso',
            texto: fotosCarga ? `Fotos de la carga y el sello (${fotosCarga})` : 'Sin fotos de la carga y el sello',
            detalle: fotosCarga ? undefined : 'No impide la salida, pero es la evidencia que se pide ante una reclamación.',
            bloquea: false,
        },
        {
            clave: 'transporte',
            estado: transporteOk ? 'ok' : 'pendiente',
            texto: transporteOk ? 'Datos de transporte capturados' : 'Transportista, placas y sello',
            detalle: transporteOk ? undefined : 'Se capturan abajo.',
            bloquea: true,
        },
    ];
});

const listo = computed(() => condiciones.value.every((c) => !c.bloquea || c.estado === 'ok'));
defineExpose({ listo });

const icono = { ok: CircleCheck, falta: CircleX, aviso: TriangleAlert, pendiente: Circle } as const;
const color = { ok: 'text-ok', falta: 'text-bad', aviso: 'text-warn', pendiente: 'text-ink-3' } as const;

function ir(href: string) {
    document.querySelector(href)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <ul class="divide-y divide-line rounded-ui border border-line" aria-label="Condiciones para la salida">
        <li v-for="c in condiciones" :key="c.clave" class="flex items-start gap-3 px-4 py-3">
            <component :is="icono[c.estado]" class="mt-0.5 size-5 flex-none" :class="color[c.estado]" :aria-label="c.estado === 'ok' ? 'cumplida' : c.estado === 'aviso' ? 'aviso' : 'pendiente'" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold" :class="c.estado === 'ok' ? 'text-ink' : color[c.estado]">{{ c.texto }}</p>
                <p v-if="c.detalle" class="text-sm text-ink-2">{{ c.detalle }}</p>
            </div>
            <template v-if="c.accion">
                <button v-if="c.accion.interna" type="button" class="text-sm font-semibold whitespace-nowrap text-primary hover:underline" @click="ir(c.accion.href)">
                    {{ c.accion.texto }} →
                </button>
                <Link v-else :href="c.accion.href" class="text-sm font-semibold whitespace-nowrap text-primary hover:underline">{{ c.accion.texto }} →</Link>
            </template>
        </li>
    </ul>
</template>
