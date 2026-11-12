import { onBeforeUnmount, ref } from 'vue';

type Fase = 'inactivo' | 'leyendo' | 'revelando';

const MINIMO_MS = 1100; // la lectura se ve al menos este tiempo, aunque el servidor responda antes
const PASO_FILA_MS = 120;

/**
 * Coreografía de una lectura RFID: barrido con contador mientras responde el servidor,
 * luego el contador llega al total real y las filas se revelan una por una.
 */
export function useLectura() {
    const fase = ref<Fase>('inactivo');
    const contador = ref(0);
    const revelados = ref(0);
    let inicio = 0;
    let cuadro = 0;
    let timers: ReturnType<typeof setTimeout>[] = [];
    const sinMovimiento = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function limpiar() {
        cancelAnimationFrame(cuadro);
        timers.forEach(clearTimeout);
        timers = [];
    }

    function animar(desde: number, hasta: number, ms: number, alTerminar?: () => void) {
        cancelAnimationFrame(cuadro);
        const t0 = performance.now();
        const paso = (t: number) => {
            const p = Math.min(1, (t - t0) / ms);
            const suave = 1 - Math.pow(1 - p, 3);
            contador.value = Math.round(desde + (hasta - desde) * suave);
            if (p < 1) cuadro = requestAnimationFrame(paso);
            else alTerminar?.();
        };
        cuadro = requestAnimationFrame(paso);
    }

    /** Arranca el barrido; `estimado` = piezas que se espera leer. */
    function iniciar(estimado: number) {
        limpiar();
        fase.value = 'leyendo';
        revelados.value = 0;
        contador.value = 0;
        inicio = performance.now();
        if (!sinMovimiento()) animar(0, Math.round(estimado * 0.82), MINIMO_MS);
    }

    /** Termina con el total real leído y revela `filas` renglones. */
    function terminar(total: number, filas: number) {
        const espera = Math.max(0, MINIMO_MS - (performance.now() - inicio));
        if (sinMovimiento()) {
            contador.value = total;
            revelados.value = filas;
            fase.value = 'inactivo';
            return;
        }
        timers.push(
            setTimeout(() => {
                animar(contador.value, total, 450, () => {
                    fase.value = 'revelando';
                    for (let i = 1; i <= filas; i++) timers.push(setTimeout(() => (revelados.value = i), i * PASO_FILA_MS));
                    timers.push(setTimeout(() => (fase.value = 'inactivo'), filas * PASO_FILA_MS + 250));
                });
            }, espera),
        );
    }

    function cancelar() {
        limpiar();
        fase.value = 'inactivo';
    }

    /** ¿La fila i ya se muestra? Durante el revelado solo las primeras. */
    const visible = (i: number) => fase.value === 'inactivo' || (fase.value === 'revelando' && i < revelados.value);
    /** ¿La fila i acaba de revelarse? (para resaltarla un instante) */
    const recien = (i: number) => fase.value === 'revelando' && i === revelados.value - 1;

    onBeforeUnmount(limpiar);

    return { fase, contador, iniciar, terminar, cancelar, visible, recien };
}
