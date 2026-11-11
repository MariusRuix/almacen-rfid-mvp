<script setup lang="ts">
import { MessageCircleQuestion } from 'lucide-vue-next';
</script>

<template>
    <!-- Puntos para conversar con el cliente durante la demo. -->
    <aside class="flex gap-3 rounded-ui border border-dashed border-line-strong bg-surface-2 px-4 py-3 text-sm text-ink-2">
        <MessageCircleQuestion class="mt-0.5 size-4 flex-none text-ink-3" aria-hidden="true" />
        <div><span class="font-semibold text-ink">Para conversar:</span> <slot /></div>
    </aside>
</template>
