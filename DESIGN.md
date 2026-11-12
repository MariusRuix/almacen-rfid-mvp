# Design

Herramienta de operación (modo Operate). La estructura es la de cualquier WMS: rail lateral, tablas y formularios estándar. El carácter lo dan la tipografía, el color, la densidad y unos cuantos componentes propios. Hay un solo estilo visual, "Contenedor". El tema "Semáforo" se retiró: su idea de "¿puedo avanzar?" vive ahora en la lista de condiciones de salida.

## Tokens (resources/css/app.css)

| Token | Valor | Uso |
|---|---|---|
| `--rail` | #15304a | Barra lateral y encabezado de piso |
| `--ground` | #e9edf1 | Fondo de la app |
| `--surface` / `--surface-2` | #fff / #f4f6f9 | Paneles / encabezados de tabla |
| `--ink` / `--ink-2` / `--ink-3` | #142130 / #485b6e / #6b7d8f | Texto |
| `--primary` | #1c4f7e | Acción principal, avance, ocupación |
| `--marca` | #d0561c | Recuadro del segmento final, foco, paso actual |
| `--ok` `--warn` `--bad` (+ `-bg`) | verde / ámbar / rojo | Solo con significado de estado |

Tipografía: Barlow para la interfaz, Barlow Semi Condensed para los títulos y JetBrains Mono para los identificadores, con cifras tabulares. Radio de 4 px.

## Componentes propios

- **IdSegmentado.** Pedimento, contenedor (ISO 6346), ubicación y SKU se escriben en segmentos nombrados (al pasar el cursor se ve cada nombre), con el último segmento en recuadro naranja.
- **BarraCuadre.** Lo contado contra lo esperado por SKU: barra llena hasta lo contado, una marca en lo esperado, el faltante rayado en rojo y el sobrante en rojo después de la marca.
- **AvancePedido.** Pasos del pedido como nodos sobre una línea, con el dato real de cada paso, avance parcial del paso actual y el paso bloqueado en rojo con su motivo. Tiene una versión compacta para listas.
- **CondicionesSalida.** Lista de lo que debe cumplirse para despachar, cada condición con su estado y un enlace para resolverla.
- **Resultado.** Banda de estado (cuadra, diferencia, parcial o pendiente) para resúmenes.
- **BarridoRfid.** Lectura en curso: haz animado y contador de etiquetas.

## Reglas

- El color de estado solo aparece cuando significa algo. No se usan tarjetas de KPI decorativas.
- En la vista de piso los objetivos táctiles miden 48 px o más y se muestra una instrucción o una caja por pantalla.
- Las preguntas abiertas para el cliente van en `PreguntaAbierta`, con borde punteado y tono neutro.
