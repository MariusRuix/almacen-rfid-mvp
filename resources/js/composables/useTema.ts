import { ref } from 'vue';

export type Tema = 'contenedor' | 'semaforo';

function leer(): Tema {
    const actual = typeof document !== 'undefined' ? document.documentElement.dataset.tema : undefined;
    return actual === 'semaforo' ? 'semaforo' : 'contenedor';
}

const tema = ref<Tema>(leer());

export function useTema() {
    function cambiar(nuevo: Tema) {
        tema.value = nuevo;
        document.documentElement.dataset.tema = nuevo;
        try {
            localStorage.setItem('tema', nuevo);
        } catch {
            /* sin almacenamiento: el tema dura lo que dure la pestaña */
        }
    }

    return { tema, cambiar };
}
