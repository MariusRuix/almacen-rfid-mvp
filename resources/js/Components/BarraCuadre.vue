<script setup lang="ts">
import { n } from '@/lib/formato';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Lo contado contra lo esperado: la barra llega hasta lo contado, la marca vertical es lo esperado.
 * Faltante = tramo rayado en rojo antes de la marca; sobrante = tramo rojo después de la marca.
 */
const props = withDefaults(defineProps<{ esperado: number; contado: number | null; cifras?: boolean }>(), { cifras: true });

const total = computed(() => Math.max(props.esperado, props.contado ?? 0, 1));
const pct = (v: number) => `${(v / total.value) * 100}%`;
const dif = computed(() => (props.contado === null ? null : props.contado - props.esperado));
const base = computed(() => Math.min(props.contado ?? 0, props.esperado));
const etiqueta = computed(() => {
    if (props.contado === null) return `Sin contar; se esperan ${props.esperado}`;
    if (dif.value === 0) return `${props.contado} de ${props.esperado}, cuadra`;
    return `${props.contado} de ${props.esperado}, ${dif.value! < 0 ? 'faltan' : 'sobran'} ${Math.abs(dif.value!)}`;
});
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="pista" role="img" :aria-label="etiqueta">
            <span v-if="contado !== null" class="tramo base" :class="{ completo: dif === 0 }" :style="{ width: pct(base) }" />
            <span v-if="dif !== null && dif < 0" class="tramo falta" :style="{ left: pct(base), width: pct(-dif) }" />
            <span v-if="dif !== null && dif > 0" class="tramo sobra" :style="{ left: pct(esperado), width: pct(dif) }" />
            <span class="marca" :style="{ left: `calc(${pct(esperado)} - 1px)` }" />
        </div>
        <span v-if="cifras" class="num w-[5.5rem] text-right text-sm whitespace-nowrap text-ink-2">
            <b class="text-ink">{{ n(contado) }}</b> / {{ n(esperado) }}
        </span>
        <span class="num w-12 text-right text-sm font-bold">
            <span v-if="dif === null" class="text-ink-3">—</span>
            <Check v-else-if="dif === 0" class="ml-auto size-4 text-ok" aria-label="cuadra" />
            <span v-else class="text-bad">{{ dif > 0 ? '+' : '−' }}{{ n(Math.abs(dif)) }}</span>
        </span>
    </div>
</template>

<style scoped>
.pista {
    position: relative;
    flex: 1;
    min-width: 6rem;
    height: 0.65rem;
    border-radius: 999px;
    background: var(--surface-2);
    box-shadow: inset 0 0 0 1px var(--line);
}
.tramo {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    border-radius: 999px;
    transition: width 500ms cubic-bezier(0.16, 1, 0.3, 1);
}
.base {
    background: var(--primary);
}
.base.completo {
    background: var(--ok);
}
.falta {
    background: repeating-linear-gradient(-45deg, color-mix(in srgb, var(--bad) 55%, transparent) 0 3px, transparent 3px 6px);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--bad) 60%, transparent);
}
.sobra {
    background: var(--bad);
}
.marca {
    position: absolute;
    top: -0.3rem;
    bottom: -0.3rem;
    width: 2px;
    border-radius: 1px;
    background: var(--ink);
}
@media (prefers-reduced-motion: reduce) {
    .tramo {
        transition: none;
    }
}
</style>
