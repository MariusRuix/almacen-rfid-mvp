export type Tono = 'neutro' | 'info' | 'ok' | 'warn' | 'bad';

type Mapa = Record<string, [string, Tono]>;

export const estadosRecepcion: Mapa = {
    esperada: ['Esperada', 'neutro'],
    en_conteo: ['En conteo', 'info'],
    contada: ['Contada · por cerrar', 'warn'],
    cerrada: ['Cerrada', 'ok'],
};

export const estadosPedido: Mapa = {
    nuevo: ['Registrado · por liberar', 'neutro'],
    en_surtido: ['En surtido', 'info'],
    en_separacion: ['Separando por destino', 'info'],
    en_revision: ['En revisión', 'warn'],
    listo: ['Listo para salir', 'ok'],
    despachado: ['Despachado', 'neutro'],
};

export const estadosDestino: Mapa = {
    pendiente: ['Por separar', 'neutro'],
    separado: ['Separado, sin revisar', 'info'],
    con_diferencia: ['Con diferencia', 'bad'],
    verificado: ['Verificado', 'ok'],
};

export const estadosTarea: Mapa = {
    pendiente: ['Pendiente', 'neutro'],
    tomada: ['Tomada', 'ok'],
    con_faltante: ['Con faltante', 'bad'],
};

export const estadosExistencia: Mapa = {
    disponible: ['Almacenado', 'ok'],
    en_preparacion: ['En preparación', 'info'],
    incidencia: ['Con incidencia', 'bad'],
};

export const estadosIncidencia: Mapa = {
    abierta: ['Abierta', 'bad'],
    resuelta: ['Resuelta', 'ok'],
};

export const tiposIncidencia: Record<string, string> = {
    faltante: 'Faltante',
    sobrante: 'Sobrante',
    accesorio_faltante: 'Sin accesorio',
    danado: 'Dañado',
    destino_equivocado: 'Destino equivocado',
};

export const tiposMovimiento: Record<string, string> = {
    recepcion: 'Recepción',
    ubicacion: 'Ubicación',
    surtido: 'Surtido',
    separacion: 'Corrección de bulto',
    ajuste: 'Ajuste',
    incidencia: 'Incidencia',
    liberacion: 'Liberación',
    salida: 'Salida',
    reubicacion: 'Reubicación',
};

export const zonas: Record<string, string> = {
    rack: 'Rack',
    estanteria: 'Estantería',
    piso: 'Piso',
    adicional: 'Adicional',
    recepcion: 'Recepción',
    preparacion: 'Preparación',
    incidencia: 'Incidencias',
};
