<script setup lang="ts">
import { CircleAlert, CircleCheck, CircleDashed, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

/** Resultado de una validación (esperado contra confirmado), como banda de estado. */
const props = withDefaults(
    defineProps<{ estado: 'cuadra' | 'diferencia' | 'parcial' | 'pendiente'; titulo: string; grande?: boolean }>(),
    { grande: false },
);

const icono = computed(() => ({ cuadra: CircleCheck, diferencia: CircleAlert, parcial: TriangleAlert, pendiente: CircleDashed })[props.estado]);
</script>

<template>
    <div class="banda" :class="[`banda-${estado}`, { grande }]" role="status">
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
</style>
