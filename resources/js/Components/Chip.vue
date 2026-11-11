<script setup lang="ts">
import type { Tono } from '@/lib/etiquetas';
import { computed } from 'vue';

const props = defineProps<{ valor: string; mapa: Record<string, [string, Tono]> }>();
const par = computed(() => props.mapa[props.valor] ?? [props.valor, 'neutro' as Tono]);
</script>

<template>
    <span class="chip" :class="`chip-${par[1]}`"><span class="punto" aria-hidden="true" />{{ par[0] }}</span>
</template>

<style scoped>
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    background: var(--surface-2);
    color: var(--ink-2);
    border: 1px solid var(--line);
}
.punto {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 999px;
    background: currentColor;
}
.chip-info {
    background: var(--info-bg);
    color: var(--info);
    border-color: transparent;
}
.chip-ok {
    background: var(--ok-bg);
    color: var(--ok);
    border-color: transparent;
}
.chip-warn {
    background: var(--warn-bg);
    color: var(--warn);
    border-color: transparent;
}
.chip-bad {
    background: var(--bad-bg);
    color: var(--bad);
    border-color: transparent;
}
</style>
