<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Existencia;
use App\Models\Incidencia;
use App\Models\Pedimento;
use App\Models\Producto;
use App\Models\Recepcion;
use App\Models\RecepcionLinea;
use App\Models\Ubicacion;
use App\Services\Inventario;
use App\Services\Rfid\Epc;
use App\Services\Rfid\LectorRfid;
use App\Services\Rfid\LectorSimulado;
use App\Support\Contenedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RecepcionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Recepciones/Index', [
            'recepciones' => Recepcion::with('pedimento')
                ->withSum('lineas', 'piezas_esperadas')->withSum('lineas', 'piezas_confirmadas')
                ->withCount(['lineas as por_ubicar' => fn ($q) => $q->whereNull('ubicada_at')])
                ->latest('id')->get()->map(fn ($r) => [
                    'id' => $r->id,
                    'folio' => $r->folio,
                    'estado' => $r->estado,
                    'origen' => $r->origen,
                    'fecha_esperada' => $r->fecha_esperada->toDateString(),
                    'pedimento' => Presentar::pedimento($r->pedimento),
                    'esperadas' => (int) $r->lineas_sum_piezas_esperadas,
                    'confirmadas' => $r->lineas_sum_piezas_confirmadas === null ? null : (int) $r->lineas_sum_piezas_confirmadas,
                    'por_ubicar' => $r->por_ubicar,
                ]),
        ]);
    }

    public function show(Recepcion $recepcion): Response
    {
        $recepcion->load(['pedimento', 'lineas.producto', 'lineas.ubicacion', 'cerro', 'terminoConteo']);
        $cajas = $recepcion->cajas()->get();
        $rec = Ubicacion::zona('recepcion');

        return Inertia::render('Recepciones/Show', [
            'recepcion' => [
                'id' => $recepcion->id,
                'folio' => $recepcion->folio,
                'estado' => $recepcion->estado,
                'origen' => $recepcion->origen,
                'fecha_esperada' => $recepcion->fecha_esperada->toDateString(),
                'cerrada_at' => $recepcion->cerrada_at?->toDateTimeString(),
                'cerro' => $recepcion->cerro?->name,
                'evidencias' => Evidencia::paraVista(Evidencia::de('recepcion', [$recepcion->id])->get($recepcion->id)),
                'fotos_cajas' => Evidencia::where('tipo', 'caja')->whereIn('referencia_id', $cajas->pluck('id'))->count(),
                'conteo' => $cajas->isEmpty() ? null : [
                    'cajas' => $cajas->where('estado', 'leida')->count(),
                    'manuales' => $cajas->where('estado', 'leida')->where('origen', '!=', 'rfid')->count(),
                    'anuladas' => $cajas->where('estado', 'anulada')->count(),
                    'terminado_at' => $recepcion->conteo_terminado_at?->toDateTimeString(),
                    'termino' => $recepcion->terminoConteo?->name,
                ],
                'pedimento' => Presentar::pedimento($recepcion->pedimento),
                'lineas' => $recepcion->lineas->map(fn (RecepcionLinea $l) => [
                    'id' => $l->id,
                    'producto' => Presentar::producto($l->producto),
                    'cajas' => $l->cajas_esperadas,
                    'piezas_por_caja' => $l->piezas_por_caja,
                    'esperadas' => $l->piezas_esperadas,
                    'leidas' => $l->piezas_leidas,
                    'confirmadas' => $l->piezas_confirmadas,
                    'sin_accesorio' => $l->piezas_sin_accesorio,
                    'danadas' => $l->piezas_danadas,
                    'sin_etiqueta' => $l->piezas_sin_etiqueta,
                    'diferencia' => $l->diferencia(),
                    'ubicacion' => Presentar::ubicacion($l->ubicacion),
                    'ubicada' => $l->ubicada_at !== null,
                    'sugerida' => $recepcion->estado === 'cerrada' && ! $l->ubicada_at ? Presentar::ubicacion($this->sugerirUbicacion($l)) : null,
                    'no_esperado' => $l->piezas_esperadas === 0,
                ]),
            ],
            'ubicaciones' => Ubicacion::whereIn('zona', Ubicacion::ALMACENAJE)->disponibles()->orderBy('codigo')->get()
                ->map(fn ($u) => [
                    'id' => $u->id, 'codigo' => $u->codigo, 'zona' => $u->zona, 'capacidad' => $u->capacidad,
                    'ocupadas' => (int) Existencia::where('ubicacion_id', $u->id)->sum('cantidad'),
                ]),
            'zonaRecepcion' => $rec->codigo,
            'productos' => $recepcion->estado === 'cerrada' ? [] : Producto::orderBy('sku')->get()->map(fn ($p) => Presentar::producto($p)),
        ]);
    }

    /** Barrido RFID de lo recibido (portal o handheld). */
    public function leerRfid(Recepcion $recepcion, LectorRfid $lector)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        $recepcion->load('lineas');

        if ($recepcion->cajas()->exists()) {
            return back()->with('error', 'Esta recepción se está contando caja por caja en piso.');
        }
        // Lo que físicamente está: lo esperado, y en renglones no esperados lo que ya se registró.
        $esperado = $recepcion->lineas->mapWithKeys(fn ($l) => [
            $l->producto_id => $l->piezas_esperadas > 0 ? $l->piezas_esperadas : (int) $l->piezas_confirmadas,
        ])->all();
        $epcs = $lector->leer($esperado, $recepcion->folio);
        $conteo = Epc::contarPorProducto($epcs);

        foreach ($recepcion->lineas as $linea) {
            $leidas = $conteo[$linea->producto_id] ?? 0;
            $linea->update(['piezas_leidas' => $leidas, 'piezas_confirmadas' => $leidas]);
        }
        // El lector vio un SKU que no venía en el archivo: se agrega como no esperado.
        foreach (array_diff_key($conteo, $esperado) as $productoId => $leidas) {
            $recepcion->lineas()->create([
                'producto_id' => $productoId, 'cajas_esperadas' => 0, 'piezas_esperadas' => 0,
                'piezas_por_caja' => Producto::find($productoId)->piezas_por_caja,
                'piezas_leidas' => $leidas, 'piezas_confirmadas' => $leidas,
            ]);
        }
        $recepcion->update(['estado' => 'en_conteo']);

        return back()->with('ok', count($epcs).' etiquetas leídas.');
    }

    /** Ajuste manual del operador (conteo físico, piezas sin accesorio). */
    public function actualizarLinea(Request $request, RecepcionLinea $linea)
    {
        abort_if($linea->recepcion->estado === 'cerrada', 422);
        $datos = $request->validate([
            'confirmadas' => ['required', 'integer', 'min:0', 'max:100000'],
            'sin_accesorio' => ['required', 'integer', 'min:0', 'lte:confirmadas'],
        ], ['sin_accesorio.lte' => 'Las piezas sin accesorio no pueden ser más que las confirmadas.']);

        $linea->update(['piezas_confirmadas' => $datos['confirmadas'], 'piezas_sin_accesorio' => $datos['sin_accesorio']]);
        $linea->recepcion->update(['estado' => 'en_conteo']);

        return back()->with('ok', 'Conteo de '.$linea->producto->sku.' actualizado.');
    }

    /**
     * Agrega un SKU a la recepción.
     * Esperada: la responsable corrige lo que se espera (cajas × piezas por caja).
     * En conteo: llegó un SKU que no venía en el archivo; se registra con lo contado (esperado 0).
     */
    public function agregarLinea(Request $request, Recepcion $recepcion)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        $esperada = $recepcion->estado === 'esperada';
        if ($esperada && ! $request->user()->esResponsable()) {
            return back()->with('error', 'Lo esperado lo corrige la responsable operativa.');
        }

        $datos = $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id', Rule::unique('recepcion_lineas', 'producto_id')->where('recepcion_id', $recepcion->id)],
            ...($esperada
                ? ['cajas' => ['required', 'integer', 'min:1', 'max:10000'], 'piezas_por_caja' => ['required', 'integer', 'min:1', 'max:10000']]
                : ['piezas' => ['required', 'integer', 'min:1', 'max:100000']]),
        ], [
            'producto_id.required' => 'Elige un SKU del catálogo.',
            'producto_id.exists' => 'Ese SKU no está en el catálogo.',
            'producto_id.unique' => 'Ese SKU ya está en la recepción.',
            'cajas.*' => 'Indica las cajas (1 o más).',
            'piezas_por_caja.*' => 'Indica las piezas por caja (1 o más).',
            'piezas.*' => 'Indica cuántas piezas llegaron (1 o más).',
        ]);

        $producto = Producto::findOrFail($datos['producto_id']);
        $recepcion->lineas()->create($esperada
            ? ['producto_id' => $producto->id, 'cajas_esperadas' => $datos['cajas'], 'piezas_por_caja' => $datos['piezas_por_caja'],
                'piezas_esperadas' => $datos['cajas'] * $datos['piezas_por_caja']]
            : ['producto_id' => $producto->id, 'cajas_esperadas' => 0, 'piezas_por_caja' => $producto->piezas_por_caja,
                'piezas_esperadas' => 0, 'piezas_confirmadas' => $datos['piezas']]);

        return back()->with('ok', $esperada
            ? $producto->sku.' agregado a lo esperado.'
            : $producto->sku.' registrado como no esperado: al cerrar quedará como sobrante.');
    }

    /** Corrige cajas y piezas por caja mientras la recepción no tenga lectura. */
    public function actualizarEsperado(Request $request, RecepcionLinea $linea)
    {
        abort_if($linea->recepcion->estado !== 'esperada', 422);
        $datos = $request->validate([
            'cajas' => ['required', 'integer', 'min:1', 'max:10000'],
            'piezas_por_caja' => ['required', 'integer', 'min:1', 'max:10000'],
        ], ['cajas.*' => 'Indica las cajas (1 o más).', 'piezas_por_caja.*' => 'Indica las piezas por caja (1 o más).']);

        $linea->update(['cajas_esperadas' => $datos['cajas'], 'piezas_por_caja' => $datos['piezas_por_caja'],
            'piezas_esperadas' => $datos['cajas'] * $datos['piezas_por_caja']]);

        return back()->with('ok', 'Esperado de '.$linea->producto->sku.' corregido.');
    }

    /** Quita un SKU: de lo esperado (antes de leer) o un no esperado registrado por error. */
    public function quitarLinea(Request $request, RecepcionLinea $linea)
    {
        $recepcion = $linea->recepcion;
        $permitido = $recepcion->estado === 'esperada' || (in_array($recepcion->estado, ['en_conteo', 'contada'], true) && $linea->piezas_esperadas === 0);
        abort_unless($permitido, 422);
        if ($recepcion->lineas()->count() === 1) {
            return back()->with('error', 'La recepción debe tener al menos un SKU.');
        }
        $sku = $linea->producto->sku;
        $linea->delete();

        return back()->with('ok', $sku.' quitado de la recepción.');
    }

    public function releer(Recepcion $recepcion)
    {
        LectorSimulado::marcarCorregido($recepcion->folio);

        return back()->with('ok', 'Listo para volver a leer.');
    }

    /** Cierra la recepción: entra al inventario y las diferencias quedan como incidencias. */
    public function cerrar(Request $request, Recepcion $recepcion, Inventario $inventario)
    {
        abort_if($recepcion->estado === 'cerrada', 422);
        $recepcion->load('lineas.producto');

        if ($recepcion->lineas->contains(fn ($l) => $l->piezas_confirmadas === null)) {
            return back()->with('error', 'Falta confirmar el conteo de algunas líneas.');
        }

        $zonaRec = Ubicacion::zona('recepcion');
        $zonaInc = Ubicacion::zona('incidencia');

        $cajas = $recepcion->cajas()->where('estado', 'leida')->get()->countBy('origen');
        $nota = $cajas->isEmpty() ? 'Conteo: lectura RFID' : 'Conteo: '.$cajas->map(fn ($n, $o) => $n.' '.['rfid' => 'cajas RFID', 'codigo' => 'cajas por código de barras', 'manual' => 'cajas a mano'][$o])->implode(', ');

        DB::transaction(function () use ($recepcion, $inventario, $zonaRec, $zonaInc, $request, $nota) {
            foreach ($recepcion->lineas as $l) {
                $buenas = $l->piezas_confirmadas - $l->piezas_sin_accesorio - $l->piezas_danadas;
                if ($buenas > 0) {
                    $inventario->mover('recepcion', $l->producto_id, $recepcion->pedimento_id, null,
                        ['ubicacion_id' => $zonaRec->id, 'estado' => 'disponible'], $buenas, $recepcion, $recepcion->folio, $nota);
                }
                if ($l->piezas_sin_accesorio > 0) {
                    $inventario->mover('recepcion', $l->producto_id, $recepcion->pedimento_id, null,
                        ['ubicacion_id' => $zonaInc->id, 'estado' => 'incidencia'], $l->piezas_sin_accesorio, $recepcion, $recepcion->folio,
                        'Llegó sin '.($l->producto->accesorio ?? 'accesorio'));
                    $this->incidencia($recepcion, $l, 'accesorio_faltante', $l->piezas_sin_accesorio,
                        'Llegaron '.$l->piezas_sin_accesorio.' piezas sin '.($l->producto->accesorio ?? 'accesorio').'.', $request->user()->id);
                }
                if ($l->piezas_danadas > 0) {
                    $inventario->mover('recepcion', $l->producto_id, $recepcion->pedimento_id, null,
                        ['ubicacion_id' => $zonaInc->id, 'estado' => 'incidencia'], $l->piezas_danadas, $recepcion, $recepcion->folio, 'Llegó dañada');
                    $this->incidencia($recepcion, $l, 'danado', $l->piezas_danadas,
                        'Llegaron '.$l->piezas_danadas.' piezas dañadas.', $request->user()->id);
                }
                $dif = $l->diferencia();
                if ($dif !== 0) {
                    $descripcion = $l->piezas_esperadas === 0
                        ? 'Llegaron '.$dif.' piezas de un SKU que no venía en el archivo del propietario.'
                        : ($dif < 0 ? 'Faltan ' : 'Sobran ').abs($dif).' piezas contra el archivo del propietario.';
                    $this->incidencia($recepcion, $l, $dif < 0 ? 'faltante' : 'sobrante', abs($dif), $descripcion, $request->user()->id);
                }
            }
            $recepcion->update(['estado' => 'cerrada', 'cerro_id' => $request->user()->id, 'cerrada_at' => now()]);
        });

        return back()->with('ok', 'Recepción cerrada. La mercancía está en '.$zonaRec->codigo.' lista para ubicarse.');
    }

    /** Guarda lo recibido de una línea en un rack o ubicación adicional. */
    public function ubicar(Request $request, RecepcionLinea $linea, Inventario $inventario)
    {
        $recepcion = $linea->recepcion;
        abort_if($recepcion->estado !== 'cerrada' || $linea->ubicada_at, 422);
        $datos = $request->validate(['ubicacion_id' => ['required', 'exists:ubicaciones,id']]);

        $cantidad = $linea->piezas_confirmadas - $linea->piezas_sin_accesorio - $linea->piezas_danadas;
        $destino = Ubicacion::with('area')->findOrFail($datos['ubicacion_id']);
        if (! $destino->disponible()) {
            return back()->with('error', $destino->codigo.' está desactivada o en un área desactivada.');
        }

        DB::transaction(function () use ($inventario, $linea, $recepcion, $cantidad, $destino) {
            if ($cantidad > 0) {
                $inventario->mover('ubicacion', $linea->producto_id, $recepcion->pedimento_id,
                    ['ubicacion_id' => Ubicacion::zona('recepcion')->id, 'estado' => 'disponible'],
                    ['ubicacion_id' => $destino->id, 'estado' => 'disponible'], $cantidad, $recepcion, $recepcion->folio);
            }
            $linea->update(['ubicacion_id' => $destino->id, 'ubicada_at' => now()]);
        });

        return back()->with('ok', $cantidad.' piezas de '.$linea->producto->sku.' ubicadas en '.$destino->codigo.'.');
    }

    /** Alta de una recepción desde el Excel del propietario (SKU, cajas, piezas por caja). */
    public function importar(Request $request)
    {
        $this->normalizarEncabezado($request);
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            ...$this->reglasEncabezado(),
        ], $this->mensajes());

        $filas = IOFactory::load($request->file('archivo')->getRealPath())->getActiveSheet()->toArray();
        array_shift($filas); // encabezados
        $productos = Producto::pluck('id', 'sku');
        $lineas = [];
        $desconocidos = [];
        foreach ($filas as $fila) {
            [$sku, $cajas, $ppc] = array_pad(array_map(fn ($v) => trim((string) $v), $fila), 3, '');
            if ($sku === '') {
                continue;
            }
            if (! isset($productos[$sku])) {
                $desconocidos[] = $sku;

                continue;
            }
            $lineas[] = ['producto_id' => $productos[$sku], 'cajas_esperadas' => (int) $cajas, 'piezas_por_caja' => (int) $ppc,
                'piezas_esperadas' => (int) $cajas * (int) $ppc];
        }

        if ($desconocidos) {
            return back()->with('error', 'SKU que no están en el catálogo: '.implode(', ', array_unique($desconocidos)).'.');
        }
        if (! $lineas) {
            return back()->with('error', 'El archivo no trae líneas. Columnas esperadas: SKU, cajas, piezas por caja.');
        }

        $recepcion = $this->crearRecepcion($datos, $lineas, 'Excel importado');

        return redirect('/recepciones/'.$recepcion->id)->with('ok', count($lineas).' líneas importadas.');
    }

    /** Formulario para capturar a mano una recepción (urgente o pequeña). */
    public function create(): Response
    {
        return Inertia::render('Recepciones/Create', [
            'productos' => Producto::orderBy('sku')->get()->map(fn ($p) => Presentar::producto($p)),
            'pedimentos' => Pedimento::orderByDesc('fecha_pago')->get()->map(fn ($p) => Presentar::pedimento($p)),
            'hoy' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizarEncabezado($request);
        $datos = $request->validate([
            ...$this->reglasEncabezado(),
            'lineas' => ['required', 'array', 'min:1', 'max:200'],
            'lineas.*.producto_id' => ['required', 'integer', 'distinct', 'exists:productos,id'],
            'lineas.*.cajas' => ['required', 'integer', 'min:1', 'max:10000'],
            'lineas.*.piezas_por_caja' => ['required', 'integer', 'min:1', 'max:10000'],
        ], [
            ...$this->mensajes(),
            'lineas.required' => 'Agrega al menos un SKU.',
            'lineas.*.producto_id.required' => 'Elige un SKU del catálogo.',
            'lineas.*.producto_id.exists' => 'Ese SKU no está en el catálogo.',
            'lineas.*.producto_id.distinct' => 'Este SKU ya está en otro renglón.',
            'lineas.*.cajas.min' => 'Al menos 1 caja.',
            'lineas.*.cajas.required' => 'Indica el número de cajas.',
            'lineas.*.cajas.integer' => 'Solo números enteros.',
            'lineas.*.cajas.max' => 'Máximo 10,000 cajas.',
            'lineas.*.piezas_por_caja.min' => 'Al menos 1 pieza por caja.',
            'lineas.*.piezas_por_caja.required' => 'Indica las piezas por caja.',
            'lineas.*.piezas_por_caja.integer' => 'Solo números enteros.',
            'lineas.*.piezas_por_caja.max' => 'Máximo 10,000 piezas por caja.',
        ]);

        $lineas = array_map(fn ($l) => [
            'producto_id' => (int) $l['producto_id'],
            'cajas_esperadas' => (int) $l['cajas'],
            'piezas_por_caja' => (int) $l['piezas_por_caja'],
            'piezas_esperadas' => (int) $l['cajas'] * (int) $l['piezas_por_caja'],
        ], $datos['lineas']);

        $recepcion = $this->crearRecepcion($datos, $lineas, 'Captura manual');

        return redirect('/recepciones/'.$recepcion->id)->with('ok', 'Recepción '.$recepcion->folio.' creada con '.count($lineas).' SKU.');
    }

    private function crearRecepcion(array $datos, array $lineas, string $origen): Recepcion
    {
        return DB::transaction(function () use ($datos, $lineas, $origen) {
            $pedimento = Pedimento::firstOrCreate(['numero' => $datos['pedimento']], [
                'clave' => 'A1', 'aduana' => 'Por confirmar', 'contenedor' => $datos['contenedor'] ?? null,
                'proveedor' => 'Por confirmar', 'fecha_pago' => now()->toDateString(),
            ]);
            $recepcion = Recepcion::create([
                'folio' => sprintf('REC-%04d', (Recepcion::max('id') ?? 0) + 1),
                'pedimento_id' => $pedimento->id,
                'origen' => $origen,
                'fecha_esperada' => $datos['fecha_esperada'],
            ]);
            $recepcion->lineas()->createMany($lineas);

            return $recepcion;
        });
    }

    /** Acepta el pedimento con o sin espacios y el contenedor en minúsculas o con espacios. */
    private function normalizarEncabezado(Request $request): void
    {
        $digitos = preg_replace('/\D/', '', (string) $request->input('pedimento'));
        $request->merge([
            'pedimento' => strlen($digitos) === 15
                ? substr($digitos, 0, 2).' '.substr($digitos, 2, 2).' '.substr($digitos, 4, 4).' '.substr($digitos, 8)
                : trim((string) $request->input('pedimento')),
            'contenedor' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('contenedor'))) ?: null,
        ]);
    }

    private function reglasEncabezado(): array
    {
        return [
            'pedimento' => ['required', 'regex:/^\d{2} \d{2} \d{4} \d{7}$/'],
            'contenedor' => ['nullable', 'regex:/^[A-Z]{4}\d{7}$/', function (string $campo, $valor, $falla) {
                if ($valor && Contenedor::digito(substr($valor, 0, 10)) !== (int) substr($valor, 10)) {
                    $falla('El dígito verificador no coincide: revisa el número del contenedor.');
                }
            }],
            'fecha_esperada' => ['required', 'date'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'pedimento.required' => 'Captura el número de pedimento.',
            'pedimento.regex' => 'El pedimento lleva 15 dígitos: año, aduana, patente y número.',
            'contenedor.regex' => 'El contenedor lleva 4 letras y 7 dígitos, por ejemplo TXAU1047725.',
            'fecha_esperada.required' => 'Indica cuándo llega.',
        ];
    }

    public function plantilla()
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->fromArray([['SKU', 'Cajas', 'Piezas por caja']] + [1 => ['PLA-BAS-M', 10, 24], 2 => ['SUD-CAP-M', 6, 12]]);
        $ruta = storage_path('app/plantilla-recepcion.xlsx');
        (new Xlsx($libro))->save($ruta);

        return response()->download($ruta, 'plantilla-recepcion.xlsx')->deleteFileAfterSend();
    }

    private function incidencia(Recepcion $r, RecepcionLinea $l, string $tipo, int $cantidad, string $descripcion, int $userId): void
    {
        Incidencia::create([
            'folio' => Incidencia::siguienteFolio(),
            'tipo' => $tipo,
            'origen_type' => 'recepcion',
            'origen_id' => $r->id,
            'origen_folio' => $r->folio,
            'producto_id' => $l->producto_id,
            'pedimento_id' => $r->pedimento_id,
            'cantidad' => $cantidad,
            'descripcion' => $descripcion,
            'reporto_id' => $userId,
        ]);
    }

    /** Sugiere un rack donde ya está el SKU con espacio; si no, el más vacío. */
    private function sugerirUbicacion(RecepcionLinea $l): ?Ubicacion
    {
        $necesita = $l->piezas_confirmadas - $l->piezas_sin_accesorio - $l->piezas_danadas;
        $candidatas = Ubicacion::whereIn('zona', Ubicacion::ALMACENAJE)->disponibles()->orderBy('codigo')->get()
            ->map(fn ($u) => [$u, (int) Existencia::where('ubicacion_id', $u->id)->sum('cantidad'),
                Existencia::where('ubicacion_id', $u->id)->where('producto_id', $l->producto_id)->exists()]);

        $conSku = $candidatas->first(fn ($c) => $c[2] && $c[0]->capacidad - $c[1] >= $necesita);
        if ($conSku) {
            return $conSku[0];
        }

        return $candidatas->filter(fn ($c) => $c[0]->capacidad - $c[1] >= $necesita)->sortBy(fn ($c) => $c[1])->first()[0]
            ?? $candidatas->sortBy(fn ($c) => $c[1])->first()[0] ?? null;
    }
}
