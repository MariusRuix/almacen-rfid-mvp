export interface Producto {
    id: number;
    sku: string;
    referencia: string | null;
    descripcion: string;
    accesorio: string | null;
    piezas_por_caja: number;
}

export interface Pedimento {
    id: number;
    numero: string;
    clave: string;
    aduana: string;
    contenedor: string | null;
    proveedor: string;
    fecha_pago: string;
}

export interface UbicacionCorta {
    id: number;
    codigo: string;
    zona: string;
}

export interface Usuario {
    id: number;
    name: string;
    email: string;
    rol: 'responsable' | 'operador';
}

export interface PropsCompartidas {
    app: { nombre: string };
    auth: { user: Usuario | null };
    incidenciasAbiertas: number;
    flash: { ok: string | null; error: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface FilaMovimiento {
    id: number;
    tipo: string;
    fecha: string;
    producto: Producto;
    pedimento: string;
    origen: string | null;
    estado_origen: string | null;
    destino: string | null;
    estado_destino: string | null;
    cantidad: number;
    folio: string | null;
    usuario: string | null;
    nota: string | null;
}

export interface LineaPedido {
    id: number;
    producto: Producto;
    cantidad: number;
    separada: number | null;
    leida: number | null;
}

export interface DestinoPedido {
    id: number;
    codigo: string;
    nombre: string;
    tipo: string;
    estado: string;
    bultos: number;
    verificado_at: string | null;
    actividades: { campo: string; etiqueta: string; hecha: boolean }[];
    lineas: LineaPedido[];
}

export interface Tarea {
    id: number;
    orden: number;
    producto: Producto;
    pedimento: Pedimento;
    ubicacion: UbicacionCorta;
    cantidad: number;
    tomada: number | null;
    estado: string;
}

export interface PedidoDetalle {
    id: number;
    folio: string;
    propietario: string;
    estado: string;
    fecha_compromiso: string;
    destinos: DestinoPedido[];
    tareas: Tarea[];
    salida: null | {
        folio: string;
        transportista: string;
        placas: string;
        sello: string;
        piezas: number;
        autorizo: string;
        fecha: string;
    };
}
