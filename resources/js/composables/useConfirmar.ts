import { reactive } from 'vue';

export interface PeticionConfirmacion {
    titulo: string;
    mensaje?: string;
    /** Consecuencias concretas: "600 piezas entran a REC-01". */
    detalle?: string[];
    aceptar?: string;
    cancelar?: string;
    tono?: 'normal' | 'peligro';
}

interface Estado extends PeticionConfirmacion {
    abierto: boolean;
    resolver: ((ok: boolean) => void) | null;
}

export const estadoConfirmacion = reactive<Estado>({ abierto: false, titulo: '', resolver: null });

/** Diálogo de confirmación propio de la app, en lugar de window.confirm. */
export function confirmar(p: PeticionConfirmacion): Promise<boolean> {
    estadoConfirmacion.resolver?.(false);
    return new Promise((resolve) => {
        Object.assign(estadoConfirmacion, { mensaje: undefined, detalle: undefined, aceptar: 'Confirmar', cancelar: 'Cancelar', tono: 'normal' }, p, {
            abierto: true,
            resolver: resolve,
        });
    });
}

export function responderConfirmacion(ok: boolean) {
    const r = estadoConfirmacion.resolver;
    estadoConfirmacion.abierto = false;
    estadoConfirmacion.resolver = null;
    r?.(ok);
}
