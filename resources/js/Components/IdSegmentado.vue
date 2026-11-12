<script setup lang="ts">
import { computed } from 'vue';

/**
 * Identificador marcado como número de contenedor: cada parte separada y nombrada,
 * la última en recuadro. Pasa el cursor para ver qué significa cada segmento.
 */
const props = withDefaults(
    defineProps<{
        valor: string | null | undefined;
        tipo: 'pedimento' | 'contenedor' | 'ubicacion' | 'sku';
        tam?: 'sm' | 'md' | 'lg';
    }>(),
    { tam: 'md' },
);

const segmentos = computed<{ texto: string; nombre: string }[]>(() => {
    const v = (props.valor ?? '').trim();
    if (!v) return [];

    if (props.tipo === 'pedimento') {
        const partes = v.split(/\s+/);
        const nombres = ['Año', 'Aduana', 'Patente', 'Número de pedimento'];
        return partes.map((texto, i) => ({ texto, nombre: nombres[i] ?? '' }));
    }
    if (props.tipo === 'contenedor') {
        const m = v.replace(/\s+/g, '').match(/^([A-Z]{4})(\d{6})(\d)$/);
        if (!m) return [{ texto: v, nombre: 'Contenedor' }];
        return [
            { texto: m[1], nombre: 'Dueño y categoría' },
            { texto: m[2], nombre: 'Número de serie' },
            { texto: m[3], nombre: 'Dígito verificador' },
        ];
    }
    if (props.tipo === 'ubicacion') {
        const partes = v.split('-');
        const nombres = partes.length === 3 ? ['Pasillo', 'Módulo', 'Nivel'] : ['Zona', 'Posición'];
        return partes.map((texto, i) => ({ texto, nombre: nombres[i] ?? '' }));
    }
    return v.split('-').map((texto) => ({ texto, nombre: '' }));
});

const titulo = computed(() =>
    segmentos.value
        .filter((s) => s.nombre)
        .map((s) => `${s.nombre}: ${s.texto}`)
        .join(' · '),
);
</script>

<template>
    <span v-if="segmentos.length" class="id id-segmentado" :class="[`id-${tam}`, `id-${tipo}`]" :title="titulo || undefined">
        <template v-for="(s, i) in segmentos" :key="i">
            <span v-if="i > 0 && (tipo === 'sku' || tipo === 'ubicacion')" class="id-sep" aria-hidden="true">-</span>
            <span class="id-seg" :class="{ 'id-final': i === segmentos.length - 1 && segmentos.length > 1 && tipo !== 'sku' }">{{ s.texto }}</span>
        </template>
    </span>
    <span v-else class="text-ink-3">—</span>
</template>

<style scoped>
.id {
    display: inline-flex;
    align-items: stretch;
    gap: 0.28em;
    font-family: var(--font-id);
    font-weight: 500;
    letter-spacing: 0.02em;
    color: var(--ink);
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
    line-height: 1.35;
}
.id-sm {
    font-size: 0.8rem;
}
.id-md {
    font-size: 0.88rem;
}
.id-lg {
    font-size: 1.6rem;
    font-weight: 600;
    gap: 0.35em;
}
.id-seg {
    display: inline-block;
}
.id-final {
    padding: 0 0.28em;
    border: 1.5px solid var(--marca);
    border-radius: 2px;
    color: var(--ink);
}
.id-sep {
    color: var(--ink-3);
}
.id-sku,
.id-ubicacion {
    gap: 0.08em;
}
</style>

<style>
/* Tema Semáforo: recuadro discreto (sin scoped para poder leer el atributo de <html>) */
[data-tema='semaforo'] .id-segmentado .id-final {
    border-width: 1px;
    border-color: var(--line-strong);
    background: var(--surface-2);
}
</style>
