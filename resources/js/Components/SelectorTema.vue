<script setup lang="ts">
import { type Tema, useTema } from '@/composables/useTema';

const { tema, cambiar } = useTema();
const opciones: { valor: Tema; etiqueta: string }[] = [
    { valor: 'contenedor', etiqueta: 'Contenedor' },
    { valor: 'semaforo', etiqueta: 'Semáforo' },
];
</script>

<template>
    <div role="radiogroup" aria-label="Estilo visual" class="grid grid-cols-2 rounded-ui bg-rail-2 p-0.5 text-[0.8rem]">
        <button
            v-for="o in opciones"
            :key="o.valor"
            type="button"
            role="radio"
            :aria-checked="tema === o.valor"
            class="rounded-[calc(var(--radius)-1px)] px-2 py-1.5 font-semibold transition-colors"
            :class="tema === o.valor ? 'bg-rail-ink text-rail' : 'text-rail-muted hover:text-rail-ink'"
            @click="cambiar(o.valor)"
        >
            {{ o.etiqueta }}
        </button>
    </div>
</template>
