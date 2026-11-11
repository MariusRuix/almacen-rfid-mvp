# Design

Herramienta de operación (modo Operate). La estructura es la de cualquier WMS: rail lateral, tablas y formularios estándar. El carácter lo dan la tipografía, el color, la densidad y dos componentes distintivos. Hay dos temas sobre los mismos tokens; el atributo `data-tema` en `<html>` decide cuál aplica.

## Tokens (resources/css/app.css)

| Token | Contenedor | Semáforo | Uso |
|---|---|---|---|
| `--rail` | #15304a | #1b1c1e | Barra lateral y encabezado de piso |
| `--ground` | #e9edf1 | #ecebe6 | Fondo de la app |
| `--surface` / `--surface-2` | #fff / #f4f6f9 | #fff / #f6f5f2 | Paneles / encabezados de tabla |
| `--ink` / `--ink-2` / `--ink-3` | #142130 / #485b6e / #6b7d8f | #1b1c1e / #4f4f4c / #74736e | Texto |
| `--primary` | #1c4f7e | #1b1c1e | Acción principal |
| `--marca` | #d0561c | #1b1c1e | Recuadro del segmento final y foco |
| `--ok` `--warn` `--bad` (+ `-bg`) | verde / ámbar / rojo | igual, afinados | Solo con significado de estado |
| `--luz-verde/ambar/roja` | — | #19c25c / #ffb300 / #f0352a | Luces del semáforo |

Tipografía: Contenedor usa Barlow para la UI y Barlow Semi Condensed para títulos; Semáforo usa Public Sans. En ambos, los identificadores van en JetBrains Mono con cifras tabulares. Radio: 4 px en Contenedor y 6 px en Semáforo.

## Componentes distintivos

- **IdSegmentado.** Pedimento, contenedor (ISO 6346), ubicación y SKU se escriben en segmentos nombrados (al pasar el cursor se ve cada nombre). El último segmento va en recuadro: naranja de marcaje en Contenedor, gris en Semáforo.
- **Resultado.** Es el resultado de toda validación de esperado contra confirmado. En Contenedor es una banda de estado; en Semáforo es un semáforo vertical con la luz encendida.

## Reglas

- El color de estado solo aparece cuando significa algo. No se usan tarjetas de KPI decorativas.
- En la vista de piso los objetivos táctiles miden 56 px o más, se muestra una instrucción por pantalla y la cifra principal va en 60 px.
- Las preguntas abiertas para el cliente van en `PreguntaAbierta`, con borde punteado y tono neutro.
