<script setup lang="ts">
import Flash from '@/Components/Flash.vue';
import SelectorTema from '@/Components/SelectorTema.vue';
import { useCompartido } from '@/composables/useCompartido';
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    Boxes,
    ClipboardList,
    History,
    LayoutGrid,
    LogOut,
    Map,
    MapPin,
    Menu,
    RotateCcw,
    ScanLine,
    Tag,
    TriangleAlert,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{ titulo?: string }>();

const { page, usuario, esResponsable } = useCompartido();
const menuAbierto = ref(false);

interface ItemNav {
    href: string;
    etiqueta: string;
    icono: typeof LayoutGrid;
    exacto?: boolean;
    contador?: number;
}

const secciones = computed<{ nombre: string; items: ItemNav[] }[]>(() => [
    {
        nombre: 'Operación',
        items: [
            { href: '/', etiqueta: 'Tablero', icono: LayoutGrid, exacto: true },
            { href: '/recepciones', etiqueta: 'Recepción', icono: ArrowDownToLine },
            { href: '/inventario', etiqueta: 'Inventario', icono: Boxes, exacto: true },
            { href: '/inventario/mapa', etiqueta: 'Mapa de ubicaciones', icono: Map },
            { href: '/pedidos', etiqueta: 'Pedidos y salida', icono: ClipboardList },
        ],
    },
    {
        nombre: 'Control',
        items: [
            { href: '/incidencias', etiqueta: 'Incidencias', icono: TriangleAlert, contador: page.props.incidenciasAbiertas },
            { href: '/movimientos', etiqueta: 'Historial', icono: History },
        ],
    },
    {
        nombre: 'Catálogos',
        items: [
            { href: '/productos', etiqueta: 'Productos', icono: Tag },
            { href: '/destinos', etiqueta: 'Destinos', icono: MapPin },
        ],
    },
    {
        nombre: 'Piso',
        items: [{ href: '/operador', etiqueta: 'Vista del operador', icono: ScanLine }],
    },
]);

function activo(href: string, exacto?: boolean) {
    const ruta = page.url.split('?')[0];
    return exacto ? ruta === href : ruta === href || ruta.startsWith(href + '/');
}

function reiniciar() {
    if (window.confirm('Se borrarán los cambios y se cargarán de nuevo los datos ficticios. ¿Continuar?')) {
        router.post('/demo/reiniciar');
    }
}
</script>

<template>
    <div class="min-h-dvh lg:grid lg:grid-cols-[15.5rem_1fr]">
        <!-- Barra superior en pantallas chicas -->
        <div class="sticky top-0 z-30 flex items-center justify-between bg-rail px-4 py-3 text-rail-ink lg:hidden">
            <span class="font-display text-lg font-bold">{{ page.props.app.nombre }}</span>
            <button type="button" class="-m-2 p-2" :aria-expanded="menuAbierto" aria-label="Menú" @click="menuAbierto = !menuAbierto">
                <component :is="menuAbierto ? X : Menu" class="size-6" />
            </button>
        </div>

        <aside
            class="fixed inset-y-0 left-0 z-20 flex w-[15.5rem] flex-col bg-rail text-rail-ink transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0"
            :class="menuAbierto ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="hidden px-5 pt-6 pb-5 lg:block">
                <p class="font-display text-xl leading-none font-bold">{{ page.props.app.nombre }}</p>
                <p class="mt-1.5 text-xs text-rail-muted">Demostración con datos ficticios</p>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 pt-16 pb-4 lg:pt-0" aria-label="Principal">
                <div v-for="s in secciones" :key="s.nombre" class="mb-5">
                    <p class="px-2 pb-1.5 text-[0.72rem] font-semibold text-rail-muted">{{ s.nombre }}</p>
                    <Link
                        v-for="item in s.items"
                        :key="item.href"
                        :href="item.href"
                        class="group relative flex items-center gap-3 rounded-ui px-2 py-2 text-[0.95rem] transition-colors"
                        :class="activo(item.href, item.exacto) ? 'bg-rail-2 font-semibold text-rail-ink' : 'text-rail-muted hover:bg-rail-2/60 hover:text-rail-ink'"
                        @click="menuAbierto = false"
                    >
                        <span
                            v-if="activo(item.href, item.exacto)"
                            class="absolute inset-y-1.5 -left-3 w-[3px] rounded-r bg-marca [[data-tema=semaforo]_&]:bg-[var(--luz-verde)]"
                            aria-hidden="true"
                        />
                        <component :is="item.icono" class="size-[1.15rem] flex-none" aria-hidden="true" />
                        <span class="flex-1">{{ item.etiqueta }}</span>
                        <span
                            v-if="item.contador"
                            class="min-w-6 rounded-full bg-bad px-1.5 text-center text-xs leading-5 font-bold text-white"
                            :aria-label="`${item.contador} abiertas`"
                            >{{ item.contador }}</span
                        >
                    </Link>
                </div>
            </nav>

            <div class="space-y-3 border-t border-rail-line px-4 py-4">
                <SelectorTema />
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ usuario?.name }}</p>
                        <p class="text-xs text-rail-muted">{{ esResponsable ? 'Responsable operativa' : 'Operador' }}</p>
                    </div>
                    <Link href="/logout" method="post" as="button" class="rounded-ui p-2 text-rail-muted hover:bg-rail-2 hover:text-rail-ink" aria-label="Cerrar sesión" title="Cerrar sesión">
                        <LogOut class="size-[1.1rem]" />
                    </Link>
                </div>
                <button
                    v-if="esResponsable"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-ui px-2 py-1.5 text-xs text-rail-muted hover:bg-rail-2 hover:text-rail-ink"
                    @click="reiniciar"
                >
                    <RotateCcw class="size-3.5" aria-hidden="true" /> Reiniciar demo
                </button>
            </div>
        </aside>

        <div v-if="menuAbierto" class="fixed inset-0 z-10 bg-black/40 lg:hidden" aria-hidden="true" @click="menuAbierto = false" />

        <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <div class="mx-auto max-w-[88rem]">
                <slot />
            </div>
        </main>
        <Flash />
    </div>
</template>
