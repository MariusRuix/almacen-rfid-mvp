<script setup lang="ts">
import Encabezado from '@/Components/Encabezado.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

/**
 * Diagramas del proceso para la presentación. El contenido vive en resources/views/proceso.blade.php
 * (HTML y SVG sin Vue) y aquí se muestra dentro del sistema; el iframe crece con su contenido.
 */
const marco = ref<HTMLIFrameElement | null>(null);
const alto = ref(900);
let observador: ResizeObserver | undefined;

function alCargar() {
    const doc = marco.value?.contentDocument;
    if (!doc) return;
    observador?.disconnect();
    observador = new ResizeObserver(() => (alto.value = doc.documentElement.scrollHeight));
    observador.observe(doc.body);
}
onBeforeUnmount(() => observador?.disconnect());
</script>

<template>
    <Head title="Proceso de la operación" />
    <AppLayout>
        <Encabezado
            titulo="Proceso de la operación"
            descripcion="Cuatro vistas del mismo proceso: quién hace cada paso, por qué estados pasa cada documento, la historia de una pieza y el recorrido en el almacén."
        >
            <a href="/proceso/diagramas" target="_blank" rel="noopener" class="btn btn-secundario">
                <ExternalLink class="size-4" aria-hidden="true" /> Pantalla completa
            </a>
        </Encabezado>
        <iframe
            ref="marco"
            src="/proceso/diagramas?embebido=1"
            title="Diagramas del proceso"
            class="block w-full border-0"
            :style="{ height: `${alto}px` }"
            @load="alCargar"
        />
    </AppLayout>
</template>
