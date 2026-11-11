<script setup lang="ts">
defineProps<{ titulo: string }>();
</script>

<template>
    <div class="px-6 py-10 text-center">
        <p class="font-display text-lg font-semibold text-ink">{{ titulo }}</p>
        <p class="mx-auto mt-1 max-w-[52ch] text-ink-2"><slot /></p>
    </div>
</template>
