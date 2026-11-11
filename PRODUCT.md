# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Laravel 12 (PHP 8.2+) + Inertia.js 2 + Vue 3 (TypeScript) + Tailwind CSS 4 + Vite. SQLite como base de datos (un archivo, fácil de mover a la laptop de la presentación). Sin Filament ni Livewire (decisión del usuario). Desarrollo local en Laragon (Windows).

## Users

- **Responsable operativa** (escritorio): mantiene el inventario, asigna referencias y ubicaciones, recibe pedidos del propietario en Excel y prepara la distribución por tienda.
- **Operador de almacén** (tablet o handheld en piso): recibe, ubica, toma piezas y las separa por destino; hoy trabaja con órdenes impresas y cuenta varias veces.
- **Dirección** (audiencia de la demo): quiere ver cómo se relacionan las actividades del almacén y qué control daría una herramienta independiente del sistema del propietario.

## Product Purpose

MVP de demostración para una sesión de 45–60 min con un operador logístico (una bodega, recibe por vía marítima, distribuye a tiendas y a otro almacén de e-commerce). Debe permitir recorrer con datos ficticios: recepción (esperado vs confirmado y diferencia), ubicación e inventario por SKU/pedimento/ubicación/estado, surtido de un pedido para varios destinos con instrucciones al operador y confirmación, revisión de lo preparado, atención de una diferencia y salida con efecto en inventario e historial de movimientos. El éxito es que el cliente explique sus reglas al ver el flujo, no que el sistema esté completo.

## Positioning

Control del almacén propio del operador, separado del sistema del propietario de la mercancía, con el pedimento como dimensión de primera clase del inventario y validación de piezas por RFID de origen.

## Operating Context

- Hoy: Excel para recepción (SKU, cajas, piezas por caja) y pedidos; órdenes impresas; varios conteos; video como evidencia ante reclamaciones.
- Un SKU puede existir bajo varios pedimentos; la separación se conserva al surtir.
- Racks más ubicaciones adicionales habilitadas cuando sube la ocupación.
- Preparación por destino: etiqueta de importación, etiquetado comercial, sensores; productos sin accesorio quedan como pendientes.
- Las piezas traen RFID de origen; las cajas no tienen etiqueta RFID maestra.
- Picos de recepción en enero, mayo y agosto; existencias de ~80 mil piezas.

## Capabilities and Constraints

- Nombre genérico de la herramienta (sin marca de empresa): "Almacén RFID".
- Acceso con login y usuarios de prueba por rol (responsable, operador).
- Inventario derivado de un historial de movimientos inmutable.
- RFID simulado detrás de una interfaz de lector; compatibilidad con producto real pendiente de prueba.
- Abierto: reglas de qué pedimento surtir (FIFO u otro), quién autoriza la salida, quién actualiza registros externos, cómo se registran pendientes de accesorio.

## Evidence on Hand

Documento de entendimiento de Siticob (documentos_claude/document(7).pdf). No hay datos reales del cliente: todo dato en la demo es ficticio y debe verse como tal.

## Product Principles

1. Lo esperado contra lo confirmado siempre visible: la diferencia es el protagonista.
2. Pedimento nunca se pierde: toda existencia y movimiento lo muestra.
3. Cada acción deja rastro en el historial.
4. El operador ve una instrucción a la vez; la responsable ve el conjunto.
5. La demo provoca preguntas: mostrar puntos abiertos en vez de ocultarlos.

## Accessibility & Inclusion

Uso en piso con guantes y luz de bodega: objetivos táctiles grandes y contraste alto en la vista de operador.
