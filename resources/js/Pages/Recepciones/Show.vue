<script setup lang="ts">
import BarraCuadre from '@/Components/BarraCuadre.vue';
import BarridoRfid from '@/Components/BarridoRfid.vue';
import Chip from '@/Components/Chip.vue';
import Girando from '@/Components/Girando.vue';
import Encabezado from '@/Components/Encabezado.vue';
import IdSegmentado from '@/Components/IdSegmentado.vue';
import PreguntaAbierta from '@/Components/PreguntaAbierta.vue';
import Resultado from '@/Components/Resultado.vue';
import { useCompartido } from '@/composables/useCompartido';
import { confirmar } from '@/composables/useConfirmar';
import { useLectura } from '@/composables/useLectura';
import { useOcupado } from '@/composables/useOcupado';
import AppLayout from '@/Layouts/AppLayout.vue';
import { estadosRecepcion } from '@/lib/etiquetas';
import { conSigno, fecha, fechaYHora, n } from '@/lib/formato';
import type { Pedimento, Producto, UbicacionCorta } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Lock, PackagePlus, Printer, RotateCcw, ScanLine, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Linea {
    id: number; producto: Producto; cajas: number; piezas_por_caja: number; esperadas: number;
    leidas: number | null; confirmadas: number | null; sin_accesorio: number; diferencia: number | null;
    ubicacion: UbicacionCorta | null; ubicada: boolean; sugerida: UbicacionCorta | null; no_esperado: boolean;
    danadas: number; sin_etiqueta: number;
}

const props = defineProps<{
    recepcion: {
        id: number; folio: string; estado: string; origen: string; fecha_esperada: string;
        cerrada_at: string | null; cerro: string | null; pedimento: Pedimento; lineas: Linea[];
        conteo: null | { cajas: number; manuales: number; anuladas: number; terminado_at: string | null; termino: string | null };
    };
    ubicaciones: { id: number; codigo: string; zona: string; capacidad: number; ocupadas: number }[];
    zonaRecepcion: string;
    productos: Producto[];
}>();

const { esResponsable } = useCompartido();
const cerrada = computed(() => props.recepcion.estado === 'cerrada');
const leida = computed(() => props.recepcion.lineas.every((l) => l.confirmadas !== null));

// Edición por línea (conteo físico)
const edicion = reactive<Record<number, { confirmadas: number; sin_accesorio: number }>>({});
const destino = reactive<Record<number, number | null>>({});
const esperado = reactive<Record<number, { cajas: number; piezas_por_caja: number }>>({});
watch(
    () => props.recepcion.lineas,
    (lineas) => {
        for (const l of lineas) {
            edicion[l.id] = { confirmadas: l.confirmadas ?? l.esperadas, sin_accesorio: l.sin_accesorio };
            destino[l.id] = l.sugerida?.id ?? null;
            esperado[l.id] = { cajas: l.cajas, piezas_por_caja: l.piezas_por_caja };
        }
    },
    { immediate: true },
);
const cambiada = (l: Linea) => l.confirmadas === null || edicion[l.id].confirmadas !== l.confirmadas || edicion[l.id].sin_accesorio !== l.sin_accesorio;

const totales = computed(() => {
    const esperadas = props.recepcion.lineas.reduce((s, l) => s + l.esperadas, 0);
    const confirmadas = props.recepcion.lineas.reduce((s, l) => s + (l.confirmadas ?? 0), 0);
    const sinAccesorio = props.recepcion.lineas.reduce((s, l) => s + l.sin_accesorio + l.danadas, 0);
    const conDif = props.recepcion.lineas.filter((l) => l.diferencia).length;
    return { esperadas, confirmadas, sinAccesorio, conDif, diferencia: confirmadas - esperadas };
});

const resultado = computed(() => {
    if (!leida.value) return { estado: 'pendiente' as const, titulo: 'Sin lectura todavía' };
    if (totales.value.conDif) return { estado: 'diferencia' as const, titulo: `Diferencia en ${totales.value.conDif} ${totales.value.conDif === 1 ? 'SKU' : 'SKU'}` };
    if (totales.value.sinAccesorio) return { estado: 'parcial' as const, titulo: 'Cuadra en piezas, con pendientes de accesorio' };
    return { estado: 'cuadra' as const, titulo: 'Lo recibido cuadra con lo esperado' };
});

// Corrección de lo esperado (antes de leer) y SKU no esperados (durante el conteo)
const editarEsperado = computed(() => props.recepcion.estado === 'esperada' && esResponsable.value);
const porCajas = computed(() => props.recepcion.conteo !== null);
const enConteo = computed(() => ['en_conteo', 'contada'].includes(props.recepcion.estado));
const cambioEsperado = (l: Linea) => esperado[l.id].cajas !== l.cajas || esperado[l.id].piezas_por_caja !== l.piezas_por_caja;
function guardarEsperado(l: Linea) {
    router.put(`/recepcion-lineas/${l.id}/esperado`, esperado[l.id], { ...opciones, ...con(`esperado-${l.id}`) });
}
async function quitar(l: Linea) {
    const ok = await confirmar({
        titulo: `¿Quitar ${l.producto.sku} de la recepción?`,
        mensaje: l.no_esperado ? 'Se borra el registro de este SKU no esperado.' : `Deja de esperarse: ${n(l.esperadas)} piezas.`,
        aceptar: 'Quitar',
        tono: 'peligro',
    });
    if (ok) router.delete(`/recepcion-lineas/${l.id}`, { ...opciones, ...con(`quitar-${l.id}`) });
}
const nuevo = reactive({ sku: '', cajas: null as number | null, piezas_por_caja: null as number | null, piezas: null as number | null });
const errores = ref<Record<string, string>>({});
const productoNuevo = computed(() => props.productos.find((p) => p.sku === nuevo.sku.trim().toUpperCase()) ?? null);
const yaEsta = computed(() => !!productoNuevo.value && props.recepcion.lineas.some((l) => l.producto.id === productoNuevo.value!.id));
function elegirNuevo() {
    nuevo.sku = nuevo.sku.trim().toUpperCase();
    if (productoNuevo.value && !nuevo.piezas_por_caja) nuevo.piezas_por_caja = productoNuevo.value.piezas_por_caja;
}
const puedeAgregar = computed(
    () => !!productoNuevo.value && !yaEsta.value && (enConteo.value ? (nuevo.piezas ?? 0) > 0 : (nuevo.cajas ?? 0) > 0 && (nuevo.piezas_por_caja ?? 0) > 0),
);
function agregar() {
    if (!productoNuevo.value) return;
    const datos = enConteo.value
        ? { producto_id: productoNuevo.value.id, piezas: nuevo.piezas }
        : { producto_id: productoNuevo.value.id, cajas: nuevo.cajas, piezas_por_caja: nuevo.piezas_por_caja };
    router.post(`/recepciones/${props.recepcion.id}/lineas`, datos, {
        ...opciones,
        ...con('agregar'),
        onError: (e) => (errores.value = e),
        onSuccess: () => {
            errores.value = {};
            Object.assign(nuevo, { sku: '', cajas: null, piezas_por_caja: null, piezas: null });
        },
    });
}

const porUbicar = computed(() => props.recepcion.lineas.filter((l) => !l.ubicada).length);
const libre = (u: { capacidad: number; ocupadas: number }) => u.capacidad - u.ocupadas;
const opciones = { preserveScroll: true };
const { ocupado, con, es } = useOcupado();
const lectura = useLectura();
const piezasEnSitio = () => props.recepcion.lineas.reduce((s, l) => s + (l.esperadas > 0 ? l.esperadas : (l.confirmadas ?? 0)), 0);
const leidasTotal = () => props.recepcion.lineas.reduce((s, l) => s + (l.leidas ?? 0), 0);

function leer(yaIniciada = false) {
    if (!yaIniciada) lectura.iniciar(piezasEnSitio());
    router.post(`/recepciones/${props.recepcion.id}/leer`, {}, {
        ...opciones,
        ...con('leer'),
        onSuccess: () => lectura.terminar(leidasTotal(), props.recepcion.lineas.length),
        onError: lectura.cancelar,
    });
}
function releer() {
    lectura.iniciar(piezasEnSitio());
    router.post(`/recepciones/${props.recepcion.id}/releer`, {}, { ...opciones, ...con('leer'), onSuccess: () => leer(true), onError: lectura.cancelar });
}
function guardar(l: Linea) {
    router.put(`/recepcion-lineas/${l.id}`, edicion[l.id], { ...opciones, ...con(`guardar-${l.id}`) });
}
async function cerrar() {
    const detalle0: string[] = [];
    const t = totales.value;
    const buenas = t.confirmadas - t.sinAccesorio;
    if (porCajas.value && !props.recepcion.conteo!.terminado_at) detalle0.push('El auxiliar todavía no marca el conteo como terminado.');
    const incidencias = props.recepcion.lineas.filter((l) => l.diferencia).length + props.recepcion.lineas.filter((l) => l.sin_accesorio).length + props.recepcion.lineas.filter((l) => l.danadas).length;
    const detalle = [...detalle0, `${n(buenas)} piezas entran al inventario en ${props.zonaRecepcion}, listas para ubicarse.`];
    if (t.sinAccesorio) detalle.push(`${n(t.sinAccesorio)} piezas sin accesorio o dañadas se apartan como incidencia.`);
    detalle.push(incidencias ? `Se ${incidencias === 1 ? 'creará 1 incidencia' : `crearán ${incidencias} incidencias`} por las diferencias.` : 'Todo cuadra: no se crearán incidencias.');
    detalle.push('Después de cerrar ya no se puede cambiar el conteo.');
    const ok = await confirmar({ titulo: `¿Cerrar la recepción ${props.recepcion.folio}?`, detalle, aceptar: 'Cerrar recepción' });
    if (ok) router.post(`/recepciones/${props.recepcion.id}/cerrar`, {}, { ...opciones, ...con('cerrar') });
}
function ubicar(l: Linea) {
    router.post(`/recepcion-lineas/${l.id}/ubicar`, { ubicacion_id: destino[l.id] }, { ...opciones, ...con(`ubicar-${l.id}`) });
}
</script>

<template>
    <Head :title="recepcion.folio" />
    <AppLayout>
        <Link href="/recepciones" class="mb-2 inline-block text-sm text-ink-2 hover:underline">Recepción</Link>
        <Encabezado :titulo="`Recepción ${recepcion.folio}`">
            <template #meta>
                <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    <div><dt class="text-ink-3">Pedimento</dt><dd class="mt-0.5"><IdSegmentado :valor="recepcion.pedimento.numero" tipo="pedimento" /></dd></div>
                    <div><dt class="text-ink-3">Contenedor</dt><dd class="mt-0.5"><IdSegmentado :valor="recepcion.pedimento.contenedor" tipo="contenedor" /></dd></div>
                    <div><dt class="text-ink-3">Llega</dt><dd class="mt-0.5">{{ fecha(recepcion.fecha_esperada) }}</dd></div>
                    <div><dt class="text-ink-3">Origen</dt><dd class="mt-0.5">{{ recepcion.origen }}</dd></div>
                    <div><dt class="text-ink-3">Estado</dt><dd class="mt-0.5"><Chip :valor="recepcion.estado" :mapa="estadosRecepcion" /></dd></div>
                </dl>
            </template>
            <a v-if="porCajas" :href="`/etiquetas/recepciones/${recepcion.id}`" target="_blank" class="btn btn-secundario"><Printer class="size-4" />Etiquetas de caja</a>
            <template v-if="!cerrada">
                <template v-if="porCajas" />
                <button v-else-if="leida" type="button" class="btn btn-secundario" title="Simula que se corrigió el conteo físico y se vuelve a pasar el lector" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="releer">
                    <Girando v-if="es('leer')" /><RotateCcw v-else class="size-4" />Volver a leer
                </button>
                <button v-else type="button" class="btn btn-primario" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="leer()">
                    <Girando v-if="es('leer')" /><ScanLine v-else class="size-4" />{{ es('leer') ? 'Leyendo…' : 'Leer RFID' }}
                </button>
                <button v-if="esResponsable && (leida || porCajas)" type="button" class="btn btn-primario" :disabled="!!ocupado || lectura.fase.value !== 'inactivo'" @click="cerrar">
                    <Girando v-if="es('cerrar')" /><Lock v-else class="size-4" />{{ es('cerrar') ? 'Cerrando…' : 'Cerrar recepción' }}
                </button>
            </template>
        </Encabezado>

        <BarridoRfid v-if="lectura.fase.value !== 'inactivo'" :contador="lectura.contador.value" :esperado="piezasEnSitio()" grande class="mb-5" />
        <Resultado v-else :estado="resultado.estado" :titulo="resultado.titulo" grande class="mb-5">
            <template v-if="leida">
                Esperadas <b class="num">{{ n(totales.esperadas) }}</b> · confirmadas <b class="num">{{ n(totales.confirmadas) }}</b> ·
                diferencia <b class="num">{{ conSigno(totales.diferencia) }}</b>
                <template v-if="totales.sinAccesorio"> · <b class="num">{{ totales.sinAccesorio }}</b> sin accesorio o dañadas</template>
            </template>
            <template v-else>
                Pasa el lector por la mercancía recibida para comparar contra el archivo del propietario.
                <template v-if="editarEsperado"> Antes de leer todavía puedes corregir lo esperado.</template>
            </template>
        </Resultado>

        <p v-if="porCajas && !cerrada" class="mb-4 flex flex-wrap items-center gap-x-2 text-sm text-ink-2">
            <ScanLine class="size-4" aria-hidden="true" />
            Se cuenta caja por caja en piso: <b class="text-ink">{{ recepcion.conteo!.cajas }} cajas leídas</b>
            <template v-if="recepcion.conteo!.manuales"> · {{ recepcion.conteo!.manuales }} sin lector</template>
            <template v-if="recepcion.conteo!.anuladas"> ({{ recepcion.conteo!.anuladas }} anuladas)</template>.
            <template v-if="recepcion.conteo!.terminado_at"> {{ recepcion.conteo!.termino }} terminó el conteo {{ fechaYHora(recepcion.conteo!.terminado_at) }}.</template>
            <template v-else> El conteo sigue en curso.</template>
            <Link :href="`/operador/recepciones/${recepcion.id}`" class="font-semibold text-primary hover:underline">Ver cajas</Link>
        </p>
        <p v-if="cerrada" class="mb-4 text-sm text-ink-2">
            Cerrada por {{ recepcion.cerro }} el {{ fechaYHora(recepcion.cerrada_at) }}. Lo confirmado está en
            <IdSegmentado :valor="zonaRecepcion" tipo="ubicacion" tam="sm" />;
            <b v-if="porUbicar">faltan {{ porUbicar }} líneas por ubicar.</b><span v-else>todo está ubicado.</span>
        </p>

        <section class="panel overflow-x-auto">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th class="der">Cajas × pz</th>
                        <th class="der">Esperadas</th>
                        <th class="der">Leídas RFID</th>
                        <th class="der">Confirmadas</th>
                        <th class="der">Sin accesorio · dañadas</th>
                        <th class="min-w-56">Cuadre <span class="font-normal text-ink-3">(confirmado contra esperado)</span></th>
                        <th>{{ cerrada ? 'Ubicación' : '' }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(l, i) in recepcion.lineas"
                        :key="l.id"
                        :class="{ 'bg-bad-bg/50': l.diferencia && lectura.visible(i), 'fila-recien': lectura.recien(i) }"
                    >
                        <td>
                            <IdSegmentado :valor="l.producto.sku" tipo="sku" />
                            <span v-if="l.no_esperado" class="ml-2 rounded bg-warn-bg px-1.5 py-0.5 text-xs font-semibold text-warn">No esperado</span>
                            <p class="text-xs text-ink-2">{{ l.producto.descripcion }}<template v-if="l.producto.referencia"> · ref. {{ l.producto.referencia }}</template></p>
                        </td>
                        <td class="der text-ink-2">
                            <div v-if="editarEsperado" class="flex items-center justify-end gap-1.5">
                                <input v-model.number="esperado[l.id].cajas" type="number" min="1" class="campo num w-20 text-right" :aria-label="`Cajas esperadas ${l.producto.sku}`" />
                                <span aria-hidden="true">×</span>
                                <input v-model.number="esperado[l.id].piezas_por_caja" type="number" min="1" class="campo num w-20 text-right" :aria-label="`Piezas por caja ${l.producto.sku}`" />
                            </div>
                            <template v-else-if="l.no_esperado">—</template>
                            <template v-else>{{ l.cajas }} × {{ l.piezas_por_caja }}</template>
                        </td>
                        <td class="der">{{ n(l.esperadas) }}</td>
                        <td class="der" :class="{ 'celda-oculta': !lectura.visible(i) }">{{ n(l.leidas) }}</td>
                        <td class="der">
                            <span v-if="!lectura.visible(i)" />
                            <input v-else-if="!cerrada && leida && !porCajas" v-model.number="edicion[l.id].confirmadas" type="number" min="0" class="campo num w-24 text-right" :aria-label="`Confirmadas ${l.producto.sku}`" />
                            <span v-else class="font-semibold">{{ n(l.confirmadas) }}</span>
                            <p v-if="l.sin_etiqueta && lectura.visible(i)" class="text-xs text-ink-3">{{ l.sin_etiqueta }} sin etiqueta</p>
                        </td>
                        <td class="der">
                            <span v-if="!lectura.visible(i)" />
                            <template v-else-if="l.producto.accesorio">
                                <input v-if="!cerrada && leida && !porCajas" v-model.number="edicion[l.id].sin_accesorio" type="number" min="0" class="campo num w-20 text-right" :aria-label="`Sin ${l.producto.accesorio}`" :title="`Piezas sin ${l.producto.accesorio}`" />
                                <span v-else :class="l.sin_accesorio ? 'font-semibold text-warn' : 'text-ink-3'">{{ l.sin_accesorio }}</span>
                                <p class="text-xs text-ink-3">{{ l.producto.accesorio }}</p>
                            </template>
                            <span v-else-if="!l.danadas" class="text-ink-3">—</span>
                            <p v-if="l.danadas && lectura.visible(i)" class="text-xs font-semibold text-bad">{{ l.danadas }} dañadas</p>
                        </td>
                        <td>
                            <BarraCuadre v-if="lectura.visible(i)" :esperado="l.esperadas" :contado="l.confirmadas" :cifras="false" />
                        </td>
                        <td>
                            <div v-if="!cerrada" class="flex items-center gap-1">
                                <button v-if="editarEsperado && cambioEsperado(l)" type="button" class="btn btn-secundario min-h-9 px-3 text-sm" :disabled="!!ocupado" @click="guardarEsperado(l)"><Girando v-if="es(`esperado-${l.id}`)" />Guardar</button>
                                <button v-else-if="leida && !porCajas && cambiada(l) && lectura.fase.value === 'inactivo'" type="button" class="btn btn-secundario min-h-9 px-3 text-sm" :disabled="!!ocupado" @click="guardar(l)"><Girando v-if="es(`guardar-${l.id}`)" />Guardar</button>
                                <button
                                    v-if="esResponsable && (editarEsperado || (enConteo && l.no_esperado))"
                                    type="button"
                                    class="rounded-ui p-2 text-ink-3 hover:bg-surface-2 hover:text-bad"
                                    :aria-label="`Quitar ${l.producto.sku}`"
                                    title="Quitar de la recepción"
                                    @click="quitar(l)"
                                >
                                    <Girando v-if="es(`quitar-${l.id}`)" /><Trash2 v-else class="size-4" />
                                </button>
                            </div>
                            <template v-else>
                                <IdSegmentado v-if="l.ubicada && l.ubicacion" :valor="l.ubicacion.codigo" tipo="ubicacion" />
                                <span v-else-if="l.ubicada" class="text-ink-3">Todo a incidencias</span>
                                <div v-else class="flex items-center gap-2">
                                    <select v-model="destino[l.id]" class="campo w-44 font-id text-sm" :aria-label="`Ubicación para ${l.producto.sku}`">
                                        <option v-for="u in ubicaciones" :key="u.id" :value="u.id">
                                            {{ u.codigo }} · {{ n(libre(u)) }} libres{{ u.id === l.sugerida?.id ? ' · sugerida' : '' }}
                                        </option>
                                    </select>
                                    <button type="button" class="btn btn-primario min-h-9 px-3 text-sm" :disabled="!destino[l.id] || !!ocupado" @click="ubicar(l)"><Girando v-if="es(`ubicar-${l.id}`)" />Ubicar</button>
                                </div>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Agregar SKU: a lo esperado (antes de leer) o como no esperado (durante el conteo) -->
            <form v-if="editarEsperado || (enConteo && !porCajas)" class="flex flex-wrap items-end gap-3 border-t border-line bg-surface-2 px-5 py-4" @submit.prevent="agregar">
                <div class="min-w-56 flex-1">
                    <label for="nuevo-sku" class="etiqueta">{{ enConteo ? 'Llegó un SKU que no venía en el archivo' : 'Agregar SKU a lo esperado' }}</label>
                    <input id="nuevo-sku" v-model="nuevo.sku" list="skus-recepcion" class="campo font-id uppercase placeholder:normal-case" placeholder="Busca por SKU" autocomplete="off" @change="elegirNuevo" @blur="elegirNuevo" />
                    <datalist id="skus-recepcion">
                        <option v-for="p in productos" :key="p.id" :value="p.sku">{{ p.descripcion }}</option>
                    </datalist>
                    <p v-if="nuevo.sku && !productoNuevo" class="mt-1 text-sm text-bad">No está en el catálogo. <Link href="/productos" class="underline">Dar de alta en Productos</Link></p>
                    <p v-else-if="yaEsta" class="mt-1 text-sm text-bad">Ese SKU ya está en la recepción.</p>
                    <p v-else-if="errores.producto_id" class="mt-1 text-sm text-bad">{{ errores.producto_id }}</p>
                </div>
                <template v-if="enConteo">
                    <div>
                        <label for="nuevo-piezas" class="etiqueta">Piezas contadas</label>
                        <input id="nuevo-piezas" v-model.number="nuevo.piezas" type="number" min="1" class="campo num w-32 text-right" />
                    </div>
                </template>
                <template v-else>
                    <div>
                        <label for="nuevo-cajas" class="etiqueta">Cajas</label>
                        <input id="nuevo-cajas" v-model.number="nuevo.cajas" type="number" min="1" class="campo num w-24 text-right" />
                    </div>
                    <div>
                        <label for="nuevo-ppc" class="etiqueta">Piezas por caja</label>
                        <input id="nuevo-ppc" v-model.number="nuevo.piezas_por_caja" type="number" min="1" class="campo num w-24 text-right" />
                    </div>
                </template>
                <button type="submit" class="btn btn-secundario" :disabled="!puedeAgregar || !!ocupado"><Girando v-if="es('agregar')" /><PackagePlus v-else class="size-4" />{{ enConteo ? 'Registrar no esperado' : 'Agregar' }}</button>
                <p v-if="enConteo" class="w-full text-xs text-ink-3">Queda con esperado 0: al cerrar la recepción se registra como sobrante.</p>
            </form>
        </section>

        <div class="mt-5 grid gap-3 lg:grid-cols-2">
            <PreguntaAbierta>Las cajas no traen etiqueta RFID maestra. ¿Cómo cuentan hoy cajas contra piezas, y quién firma la diferencia?</PreguntaAbierta>
            <PreguntaAbierta>El RFID no ve si falta un accesorio. ¿Quién lo revisa, dónde se aparta la pieza y quién decide su destino?</PreguntaAbierta>
        </div>
    </AppLayout>
</template>
