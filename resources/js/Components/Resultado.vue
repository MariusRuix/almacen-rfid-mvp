<script setup lang="ts">
import { useTema } from '@/composables/useTema';
import { CircleAlert, CircleCheck, CircleDashed, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Resultado de una validación (esperado contra confirmado).
 * Contenedor: banda de estado. Semáforo: luz grande como en el módulo de selección aduanal.
 */
const props = withDefaults(
    defineProps<{ estado: 'cuadra' | 'diferencia' | 'parcial' | 'pendiente'; titulo: string; grande?: boolean }>(),
    { grande: false },
);

const { tema } = useTema();
const icono = computed(() => ({ cuadra: CircleCheck, diferencia: CircleAlert, parcial: TriangleAlert, pendiente: CircleDashed })[props.estado]);
const luz = computed(() => ({ cuadra: 'verde', diferencia: 'roja', parcial: 'ambar', pendiente: '' })[props.estado]);
</script>

<template>
    <div v-if="tema === 'semaforo'" class="semaforo" :class="{ grande }" role="status">
        <div class="caja" aria-hidden="true">
            <span class="luz roja" :class="{ on: luz === 'roja' }" />
            <span class="luz ambar" :class="{ on: luz === 'ambar' }" />
            <span class="luz verde" :class="{ on: luz === 'verde' }" />
        </div>
        <div class="min-w-0">
            <p class="titulo">{{ titulo }}</p>
            <div class="detalle"><slot /></div>
        </div>
    </div>

    <div v-else class="banda" :class="[`banda-${estado}`, { grande }]" role="status">
        <component :is="icono" class="icono" aria-hidden="true" />
        <div class="min-w-0">
            <p class="titulo">{{ titulo }}</p>
            <div class="detalle"><slot /></div>
        </div>
    </div>
</template>

<style scoped>
.titulo {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1.2;
}
.grande .titulo {
    font-size: 1.45rem;
}
.detalle {
    font-size: 0.92rem;
    color: var(--ink-2);
    margin-top: 0.15rem;
}

/* Contenedor */
.banda {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    padding: 0.9rem 1.1rem;
    border-radius: var(--radius);
    background: var(--surface-2);
    border: 1px solid var(--line);
}
.icono {
    flex: none;
    width: 1.6rem;
    height: 1.6rem;
    margin-top: 0.05rem;
}
.grande .icono {
    width: 2rem;
    height: 2rem;
}
.banda-cuadra {
    background: var(--ok-bg);
    border-color: color-mix(in srgb, var(--ok) 30%, transparent);
}
.banda-cuadra .icono,
.banda-cuadra .titulo {
    color: var(--ok);
}
.banda-diferencia {
    background: var(--bad-bg);
    border-color: color-mix(in srgb, var(--bad) 30%, transparent);
}
.banda-diferencia .icono,
.banda-diferencia .titulo {
    color: var(--bad);
}
.banda-parcial {
    background: var(--warn-bg);
    border-color: color-mix(in srgb, var(--warn) 30%, transparent);
}
.banda-parcial .icono,
.banda-parcial .titulo {
    color: var(--warn);
}
.banda-pendiente .icono {
    color: var(--ink-3);
}

/* Semáforo */
.semaforo {
    display: flex;
    gap: 1rem;
    align-items: center;
    padding: 0.85rem 1.1rem;
    border-radius: var(--radius);
    background: var(--surface);
    border: 1px solid var(--line);
}
.caja {
    flex: none;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    padding: 0.4rem;
    border-radius: 999px;
    background: #1b1c1e;
}
.luz {
    width: 1.05rem;
    height: 1.05rem;
    border-radius: 999px;
    background: #3a3b3f;
    transition:
        background-color 300ms cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 300ms cubic-bezier(0.16, 1, 0.3, 1);
}
.grande .caja {
    gap: 0.45rem;
    padding: 0.55rem;
}
.grande .luz {
    width: 1.7rem;
    height: 1.7rem;
}
.luz.roja.on {
    background: var(--luz-roja);
    box-shadow: 0 0 14px 2px color-mix(in srgb, var(--luz-roja) 70%, transparent);
}
.luz.ambar.on {
    background: var(--luz-ambar);
    box-shadow: 0 0 14px 2px color-mix(in srgb, var(--luz-ambar) 70%, transparent);
}
.luz.verde.on {
    background: var(--luz-verde);
    box-shadow: 0 0 14px 2px color-mix(in srgb, var(--luz-verde) 70%, transparent);
}
</style>
