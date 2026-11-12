import { ref } from 'vue';

/** Qué acción espera respuesta del servidor, para mostrar su indicador y bloquear las demás. */
export function useOcupado() {
    const ocupado = ref<string | null>(null);
    const con = (clave: string) => ({
        onStart: () => (ocupado.value = clave),
        onFinish: () => (ocupado.value = null),
    });
    const es = (clave: string) => ocupado.value === clave;

    return { ocupado, con, es };
}
