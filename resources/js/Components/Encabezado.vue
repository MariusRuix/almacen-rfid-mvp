<script setup lang="ts">
defineProps<{ titulo: string; descripcion?: string }>();
</script>

<template>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-[1.75rem] leading-tight font-bold text-ink">{{ titulo }}</h1>
            <p v-if="descripcion" class="mt-1 max-w-[70ch] text-ink-2">{{ descripcion }}</p>
            <slot name="meta" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
