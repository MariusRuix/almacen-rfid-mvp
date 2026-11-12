<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
</script>

<template>
    <LoaderCircle class="girando size-4" aria-hidden="true" />
</template>

<style scoped>
.girando {
    animation: girar 0.8s linear infinite;
}
@keyframes girar {
    to {
        transform: rotate(360deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .girando {
        animation-duration: 2.4s;
    }
}
</style>
