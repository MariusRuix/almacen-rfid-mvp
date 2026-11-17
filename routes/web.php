<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DestinoController;
use App\Http\Controllers\EtiquetaController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\OperadorController;
use App\Http\Controllers\OperadorRecepcionController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RecepcionController;
use App\Http\Controllers\TableroController;
use App\Http\Controllers\UbicacionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy']);

    Route::get('/', TableroController::class);

    // Diagramas del proceso para la presentación
    Route::inertia('/proceso', 'Proceso');
    Route::view('/proceso/diagramas', 'proceso');

    // Etiquetas para imprimir: caja, bulto y rótulo de ubicación
    Route::get('/etiquetas/recepciones/{recepcion}', [EtiquetaController::class, 'cajas']);
    Route::get('/etiquetas/pedidos/{pedido}', [EtiquetaController::class, 'bultos']);
    Route::get('/etiquetas/ubicaciones', [EtiquetaController::class, 'ubicaciones']);

    // Evidencias: fotos y documentos ligados a un paso
    Route::post('/evidencias', [EvidenciaController::class, 'store']);
    Route::get('/evidencias/{evidencia}', [EvidenciaController::class, 'show']);
    Route::delete('/evidencias/{evidencia}', [EvidenciaController::class, 'destroy']);

    // Recepción
    Route::get('/recepciones', [RecepcionController::class, 'index']);
    Route::get('/recepciones/plantilla', [RecepcionController::class, 'plantilla']);
    Route::post('/recepciones/importar', [RecepcionController::class, 'importar'])->middleware('rol:responsable');
    Route::get('/recepciones/nueva', [RecepcionController::class, 'create'])->middleware('rol:responsable');
    Route::post('/recepciones', [RecepcionController::class, 'store'])->middleware('rol:responsable');
    Route::get('/recepciones/{recepcion}', [RecepcionController::class, 'show']);
    Route::post('/recepciones/{recepcion}/leer', [RecepcionController::class, 'leerRfid']);
    Route::post('/recepciones/{recepcion}/releer', [RecepcionController::class, 'releer']);
    Route::post('/recepciones/{recepcion}/cerrar', [RecepcionController::class, 'cerrar'])->middleware('rol:responsable');
    Route::put('/recepcion-lineas/{linea}', [RecepcionController::class, 'actualizarLinea']);
    Route::post('/recepciones/{recepcion}/lineas', [RecepcionController::class, 'agregarLinea']);
    Route::put('/recepcion-lineas/{linea}/esperado', [RecepcionController::class, 'actualizarEsperado'])->middleware('rol:responsable');
    Route::delete('/recepcion-lineas/{linea}', [RecepcionController::class, 'quitarLinea'])->middleware('rol:responsable');
    Route::post('/recepcion-lineas/{linea}/ubicar', [RecepcionController::class, 'ubicar']);

    // Inventario e historial
    Route::get('/inventario', [InventarioController::class, 'index']);
    Route::get('/inventario/mapa', [InventarioController::class, 'mapa']);
    Route::get('/movimientos', [MovimientoController::class, 'index']);
    Route::post('/areas', [AreaController::class, 'store'])->middleware('rol:responsable');
    Route::put('/areas/{area}', [AreaController::class, 'update'])->middleware('rol:responsable');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->middleware('rol:responsable');
    Route::post('/ubicaciones/pasillo', [UbicacionController::class, 'generarPasillo'])->middleware('rol:responsable');
    Route::post('/ubicaciones', [UbicacionController::class, 'store'])->middleware('rol:responsable');
    Route::put('/ubicaciones/{ubicacion}', [UbicacionController::class, 'update'])->middleware('rol:responsable');
    Route::delete('/ubicaciones/{ubicacion}', [UbicacionController::class, 'destroy'])->middleware('rol:responsable');
    Route::post('/ubicaciones/reubicar', [UbicacionController::class, 'reubicar']);

    // Pedidos, revisión y salida
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::get('/pedidos/nuevo', [PedidoController::class, 'create'])->middleware('rol:responsable');
    Route::post('/pedidos', [PedidoController::class, 'store'])->middleware('rol:responsable');
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
    Route::post('/pedidos/{pedido}/liberar', [PedidoController::class, 'liberar'])->middleware('rol:responsable');
    Route::post('/pedidos/{pedido}/despachar', [PedidoController::class, 'despachar'])->middleware('rol:responsable');
    Route::post('/pedido-destinos/{destino}/leer', [PedidoController::class, 'leerDestino']);
    Route::post('/pedido-destinos/{destino}/contar', [PedidoController::class, 'contarDestino']);
    Route::post('/pedido-destinos/{destino}/corregir', [PedidoController::class, 'corregirDestino']);
    Route::post('/pedido-destinos/{destino}/actividades', [PedidoController::class, 'actividad']);
    Route::post('/pedido-destinos/{destino}/aceptar', [PedidoController::class, 'aceptarLectura'])->middleware('rol:responsable');

    // Catálogo de destinos
    Route::get('/destinos', [DestinoController::class, 'index']);
    Route::post('/destinos', [DestinoController::class, 'store'])->middleware('rol:responsable');
    Route::put('/destinos/{destino}', [DestinoController::class, 'update'])->middleware('rol:responsable');
    Route::delete('/destinos/{destino}', [DestinoController::class, 'destroy'])->middleware('rol:responsable');

    // Catálogo de productos
    Route::get('/productos', [ProductoController::class, 'index']);
    Route::post('/productos', [ProductoController::class, 'store'])->middleware('rol:responsable');
    Route::put('/productos/{producto}', [ProductoController::class, 'update'])->middleware('rol:responsable');
    Route::delete('/productos/{producto}', [ProductoController::class, 'destroy'])->middleware('rol:responsable');

    // Incidencias
    Route::get('/incidencias', [IncidenciaController::class, 'index']);
    Route::post('/incidencias/{incidencia}/resolver', [IncidenciaController::class, 'resolver'])->middleware('rol:responsable');

    // Vista de piso
    Route::get('/operador', [OperadorController::class, 'index']);
    Route::get('/operador/recepciones/{recepcion}', [OperadorRecepcionController::class, 'show']);
    Route::post('/operador/recepciones/{recepcion}/cajas', [OperadorRecepcionController::class, 'leerCaja']);
    Route::post('/operador/recepciones/{recepcion}/cajas-manuales', [OperadorRecepcionController::class, 'registrarManual']);
    Route::post('/operador/recepciones/{recepcion}/terminar', [OperadorRecepcionController::class, 'terminar']);
    Route::post('/operador/cajas/{caja}/releer', [OperadorRecepcionController::class, 'releer']);
    Route::put('/operador/cajas/{caja}', [OperadorRecepcionController::class, 'marcar']);
    Route::post('/operador/cajas/{caja}/anular', [OperadorRecepcionController::class, 'anular']);
    Route::get('/operador/pedidos/{pedido}/surtido', [OperadorController::class, 'surtido']);
    Route::post('/operador/tareas/{tarea}', [OperadorController::class, 'confirmarTarea']);
    Route::get('/operador/pedidos/{pedido}/separacion', [OperadorController::class, 'separacion']);
    Route::post('/operador/destinos/{destino}', [OperadorController::class, 'confirmarDestino']);

    Route::post('/demo/reiniciar', [DemoController::class, 'reiniciar'])->middleware('rol:responsable');
});
