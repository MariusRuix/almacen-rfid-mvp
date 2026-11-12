<script setup lang="ts">
import { n } from '@/lib/formato';
import { Radio } from 'lucide-vue-next';

/** Lectura RFID en curso: barrido y contador de etiquetas. */
withDefaults(defineProps<{ contador: number; esperado?: number; titulo?: string; grande?: boolean }>(), {
    esperado: 0,
    titulo: 'Leyendo etiquetas',
    grande: false,
});
</script>

<template>
    <div class="barrido barrido-rfid" :class="{ grande }" role="status" aria-live="polite">
        <Radio class="icono" aria-hidden="true" />
        <div class="min-w-0 flex-1">
            <p class="titulo">{{ titulo }}…</p>
            <p class="cifra">
                <span class="num">{{ n(contador) }}</span>
                <span class="unidad">etiquetas<template v-if="esperado"> · se esperan {{ n(esperado) }}</template></span>
            </p>
            <div class="pista" aria-hidden="true"><span class="haz" /></div>
        </div>
    </div>
</template>

<style scoped>
.barrido {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
    padding: 0.9rem 1.1rem;
    border-radius: var(--radius);
    background: var(--info-bg);
    border: 1px solid color-mix(in srgb, var(--info) 28%, transparent);
    color: var(--info);
}
.icono {
    flex: none;
    width: 1.6rem;
    height: 1.6rem;
    margin-top: 0.05rem;
    animation: pulso 1.1s ease-in-out infinite;
}
.grande .icono {
    width: 2rem;
    height: 2rem;
}
.titulo {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.1rem;
    line-height: 1.2;
}
.grande .titulo {
    font-size: 1.45rem;
}
.cifra {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin-top: 0.15rem;
    color: var(--ink);
}
.cifra .num {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 1.6rem;
    line-height: 1.1;
    min-width: 4ch;
}
.unidad {
    font-size: 0.9rem;
    color: var(--ink-2);
}
.pista {
    position: relative;
    height: 4px;
    margin-top: 0.6rem;
    border-radius: 999px;
    overflow: hidden;
    background: color-mix(in srgb, var(--info) 16%, transparent);
}
.haz {
    position: absolute;
    inset: 0 auto 0 0;
    width: 35%;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, var(--info), transparent);
    animation: barrer 1.1s cubic-bezier(0.45, 0, 0.55, 1) infinite;
}
@keyframes barrer {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(290%);
    }
}
@keyframes pulso {
    50% {
        opacity: 0.45;
    }
}
@media (prefers-reduced-motion: reduce) {
    .icono,
    .haz {
        animation: none;
    }
    .haz {
        width: 100%;
        opacity: 0.35;
    }
}
</style>

<style>
/* Tema Semáforo: luz ámbar mientras se lee (sin scoped para leer el atributo de <html>) */
[data-tema='semaforo'] .barrido-rfid {
    background: var(--surface);
    border-color: var(--line);
    color: var(--warn);
}
[data-tema='semaforo'] .barrido-rfid .haz {
    background: linear-gradient(90deg, transparent, var(--luz-ambar), transparent);
}
[data-tema='semaforo'] .barrido-rfid .pista {
    background: color-mix(in srgb, var(--luz-ambar) 18%, transparent);
}
</style>
