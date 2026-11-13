# Almacén RFID · MVP de demostración

Recorrido de recepción a salida para la sesión con Dirección y la responsable operativa.
Todos los datos son **ficticios** (empresas, pedimentos, contenedores y personas).

**Stack:** Laravel 12 · Inertia 2 · Vue 3 + TypeScript · Tailwind 4 · SQLite.

## Instalación en Laragon (Windows)

Requisitos: PHP 8.2 o superior, Composer y Node 20 o superior (Laragon trae los tres).
En **Menú › PHP › Extensiones** activa `pdo_sqlite`, `sqlite3`, `zip` y `gd`. Las dos últimas las usa la importación de Excel.

Abre la Terminal de Laragon en esta carpeta y ejecuta:

```bash
composer install
composer run setup      # crea .env y database/database.sqlite, genera la llave y carga los datos ficticios
npm install
npm run build           # o "npm run dev" mientras desarrollas
```

Para que Laragon cree el host `http://almacen-rfid-mvp.test`, usa **Menú › Recargar**.
Laragon toma `public/` como raíz del sitio. Si prefieres no usarlo: `php artisan serve` y abre `http://127.0.0.1:8000`.

### Usuarios de prueba (contraseña `demo2026`)

| Rol | Correo |
|---|---|
| Responsable operativa | responsable@demo.test |
| Operador de almacén | operador@demo.test |

### Reiniciar entre ensayos

Usa el botón **Reiniciar demo** en la barra lateral (como responsable) o ejecuta `php artisan demo:reiniciar`.

## Guion sugerido (45 a 60 min)

1. **Tablero.** Muestra el flujo de 5 etapas y las existencias por pedimento. Un mismo SKU aparece en varios pedimentos.
2. **Recepción REC-0001.** Pulsa *Leer RFID*: el pantalón **PAN-DEN-3032** sale con −6. Captura 4 bolsas **sin correa**, porque el RFID no detecta accesorios. Elige entre *Volver a leer* (ya se corrigió el conteo) o cerrar con la diferencia. Al cerrar, las diferencias quedan como incidencias y la mercancía entra a `REC-01`. Después ubica cada línea en un rack (el sistema sugiere uno).
   *En piso:* con el usuario operador, **Vista del operador › REC-0001** cuenta **caja por caja** (23 cajas). La última caja del pantalón trae 6 piezas de menos. En cada caja se marcan las piezas sin etiqueta, sin accesorio o dañadas, y al final se pulsa *Terminar conteo*. La responsable cierra desde su panel. Las migraciones nuevas se aplican con `php artisan migrate` o con **Reiniciar demo**.
3. **Inventario y mapa.** Filtra por pedimento o ubicación y abre el desglose de un SKU.
4. **Pedido PED-0001.** Pulsa *Liberar a surtido*. El sistema propone de qué pedimento y ubicación tomar; con **CAM-OXF-M** salen dos pedimentos distintos. El criterio es provisional: de qué pedimento surten es una pregunta para el cliente.
5. **Vista del operador** (en tablet o en otra pestaña con el usuario operador): una instrucción a la vez. Ahí se puede probar "No están completas". Después se separa por destino con las actividades de etiquetado y sensor.
6. **Revisión.** Lee el bulto de **T-014**: sobra 1 camisa. En **T-022** falta 1, porque la pieza quedó en el bulto equivocado. Ahí puedes *Corregí el bulto, volver a leer* o *Aceptar lectura*, que lo registra como incidencia.
7. **Salida.** Captura transportista, placas y sello. El inventario se descuenta y la salida aparece en el **Historial**.

Las notas **Para conversar** en cada pantalla son las preguntas abiertas del documento de entendimiento.

## Estilos visuales

En la barra lateral se cambia entre **Contenedor** (identificadores marcados como número de contenedor) y **Semáforo** (validaciones como semáforo fiscal). La elección se guarda en el navegador.

## Arquitectura en breve

- `app/Services/Inventario.php` es el único punto que cambia existencias. Cada cambio crea un `Movimiento` que no se puede editar ni borrar.
- Una existencia es la combinación de **SKU + pedimento + ubicación + estado** (`disponible`, `en_preparacion`, `incidencia`).
- RFID: la interfaz `app/Services/Rfid/LectorRfid.php` tiene la implementación `LectorSimulado`. Los escenarios de diferencia están en `config/demo.php`. Para conectar un lector real se implementa `LectorRfid` y se cambia el binding en `AppServiceProvider`.
- El criterio provisional para proponer pedimento al liberar está en `PedidoController::liberar` y se describe en `config/demo.php` (`regla_surtido`). No es una regla del cliente.
