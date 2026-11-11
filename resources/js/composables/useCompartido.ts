import type { PropsCompartidas } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useCompartido() {
    const page = usePage<PropsCompartidas>();
    const usuario = computed(() => page.props.auth.user);
    const esResponsable = computed(() => usuario.value?.rol === 'responsable');

    return { page, usuario, esResponsable };
}
