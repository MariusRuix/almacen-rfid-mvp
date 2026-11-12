<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';

/** Control de cantidad para piso: botones grandes de menos y más. */
const modelo = defineModel<number>({ required: true });
const props = withDefaults(defineProps<{ etiqueta: string; max?: number; deshabilitado?: boolean }>(), { max: 99999, deshabilitado: false });

const ajustar = (d: number) => (modelo.value = Math.max(0, Math.min(props.max, (modelo.value || 0) + d)));
</script>

<template>
    <div class="flex items-center gap-2">
        <button type="button" class="btn btn-secundario h-12 w-12 px-0" :disabled="deshabilitado || modelo <= 0" :aria-label="`${etiqueta}: una menos`" @click="ajustar(-1)">
            <Minus class="size-5" />
        </button>
        <input
            v-model.number="modelo"
            type="number"
            inputmode="numeric"
            min="0"
            :max="max"
            class="campo num h-12 w-20 text-center text-xl font-bold"
            :disabled="deshabilitado"
            :aria-label="etiqueta"
        />
        <button type="button" class="btn btn-secundario h-12 w-12 px-0" :disabled="deshabilitado || modelo >= max" :aria-label="`${etiqueta}: una más`" @click="ajustar(1)">
            <Plus class="size-5" />
        </button>
    </div>
</template>
