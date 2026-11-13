<!DOCTYPE html>
<html lang="es" @if (request()->boolean('embebido')) class="embebido" data-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proceso de la operación · {{ config('app.name') }}</title>
    @vite('resources/css/proceso.css')
@verbatim
<style>
/* Layout: banda de riel azul acero con pestañas; cada vista es un lienzo amplio que se desplaza dentro de su propio marco. */
:root {
    --rail: #15304a; --rail-ink: #e3ecf4; --rail-muted: #93abc2; --rail-line: #2a5078;
    --ground: #e9edf1; --surface: #ffffff; --surface-2: #f4f6f9;
    --ink: #142130; --ink-2: #485b6e; --ink-3: #6b7d8f;
    --line: #d3dbe3; --line-strong: #b5c2cf;
    --primary: #1c4f7e; --marca: #d0561c;
    --ok: #18794e; --ok-bg: #e3f4ea; --warn: #9a5b00; --warn-bg: #fdf1d8;
    --bad: #b4232b; --bad-bg: #fbe5e6; --info: #1c4f7e; --info-bg: #e3edf7;
    --neutro: #485b6e; --neutro-bg: #eef1f5;
    --carril-a: #f7f9fb; --carril-b: #eef2f6;
    --zona-alm: #eef3f8; --zona-op: #fbf3ea;
    --f-ui: 'Barlow', ui-sans-serif, system-ui, sans-serif;
    --f-display: 'Barlow Semi Condensed', 'Barlow', ui-sans-serif, sans-serif;
    --f-id: 'JetBrains Mono Variable', 'JetBrains Mono', ui-monospace, monospace;
    --r: 4px;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
    --rail: #0d1e2f; --rail-ink: #e3ecf4; --rail-muted: #8aa3ba; --rail-line: #24496d;
    --ground: #0f1922; --surface: #16222e; --surface-2: #1c2a38;
    --ink: #e3ecf4; --ink-2: #a9b9c8; --ink-3: #7e91a3;
    --line: #2a3a4a; --line-strong: #3b4e61;
    --primary: #6ea8dc; --marca: #f07a3e;
    --ok: #4cc28a; --ok-bg: #12301f; --warn: #e3a64a; --warn-bg: #33270f;
    --bad: #f0707a; --bad-bg: #3a1a1e; --info: #6ea8dc; --info-bg: #142a40;
    --neutro: #a9b9c8; --neutro-bg: #1f2d3b;
    --carril-a: #142029; --carril-b: #18252f;
    --zona-alm: #162636; --zona-op: #2a2117;
    color-scheme: dark;
} }
:root[data-theme="dark"] {
    --rail: #0d1e2f; --rail-ink: #e3ecf4; --rail-muted: #8aa3ba; --rail-line: #24496d;
    --ground: #0f1922; --surface: #16222e; --surface-2: #1c2a38;
    --ink: #e3ecf4; --ink-2: #a9b9c8; --ink-3: #7e91a3;
    --line: #2a3a4a; --line-strong: #3b4e61;
    --primary: #6ea8dc; --marca: #f07a3e;
    --ok: #4cc28a; --ok-bg: #12301f; --warn: #e3a64a; --warn-bg: #33270f;
    --bad: #f0707a; --bad-bg: #3a1a1e; --info: #6ea8dc; --info-bg: #142a40;
    --neutro: #a9b9c8; --neutro-bg: #1f2d3b;
    --carril-a: #142029; --carril-b: #18252f;
    --zona-alm: #162636; --zona-op: #2a2117;
    color-scheme: dark;
}
* { box-sizing: border-box; }
html, body { margin: 0; }
[hidden] { display: none !important; }
img { max-width: 100%; }
body { background: var(--ground); color: var(--ink); font-family: var(--f-ui); font-size: 15px; line-height: 1.45; -webkit-font-smoothing: antialiased; }
h1, h2, h3 { font-family: var(--f-display); letter-spacing: -0.01em; text-wrap: balance; margin: 0; }
.num, .id { font-variant-numeric: tabular-nums; }
.id { font-family: var(--f-id); font-size: 0.92em; letter-spacing: 0.01em; }
:focus-visible { outline: 2px solid var(--marca); outline-offset: 2px; }
.envoltura { max-width: 1320px; margin: 0 auto; padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px)); }

/* Cabecera */
.riel { background: var(--rail); color: var(--rail-ink); }
.riel .envoltura { padding-block: 22px 0; }
.marca-app { display: flex; align-items: center; gap: 10px; font-family: var(--f-display); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.08em; text-transform: uppercase; color: var(--rail-muted); }
.marca-app svg { color: var(--marca); }
.riel h1 { font-size: clamp(1.6rem, 3.2vw, 2.3rem); font-weight: 700; margin-top: 8px; }
.riel p.bajada { color: var(--rail-muted); margin: 6px 0 0; max-width: 64ch; }
.pestanas { display: flex; gap: 2px; margin-top: 22px; overflow-x: auto; scrollbar-width: none; }
.pestanas button { appearance: none; border: 0; background: transparent; color: var(--rail-muted); font: 600 0.98rem var(--f-display); letter-spacing: 0.01em; padding: 12px 16px 13px; border-radius: var(--r) var(--r) 0 0; cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 8px; }
.pestanas button .n { font-family: var(--f-id); font-size: 0.75rem; opacity: 0.8; }
.pestanas button:hover { color: var(--rail-ink); background: color-mix(in srgb, var(--rail-line) 45%, transparent); }
.pestanas button[aria-selected="true"] { background: var(--ground); color: var(--ink); }
.pestanas button[aria-selected="true"] .n { color: var(--marca); opacity: 1; }

main .envoltura { padding-block: 24px 48px; }
.vista-cab { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 12px 24px; margin-bottom: 16px; }
.vista-cab h2 { font-size: 1.45rem; font-weight: 700; }
.vista-cab p { margin: 4px 0 0; color: var(--ink-2); max-width: 70ch; }
.panel { background: var(--surface); border: 1px solid var(--line); border-radius: var(--r); }
.desliza { overflow-x: auto; }
.btn { appearance: none; display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border-radius: var(--r); border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink); font: 600 0.92rem var(--f-ui); cursor: pointer; }
.btn:hover:not(:disabled) { background: var(--surface-2); }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn.primario { background: var(--primary); border-color: var(--primary); color: #fff; }
.btn.primario:hover:not(:disabled) { background: color-mix(in srgb, var(--primary) 85%, var(--ink)); }
:root[data-theme="dark"] .btn.primario { color: #0d1e2f; }
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) .btn.primario { color: #0d1e2f; } }
.chip { display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.t-neutro { background: var(--neutro-bg); color: var(--neutro); }
.t-info { background: var(--info-bg); color: var(--info); }
.t-warn { background: var(--warn-bg); color: var(--warn); }
.t-ok { background: var(--ok-bg); color: var(--ok); }
.t-bad { background: var(--bad-bg); color: var(--bad); }

/* ── 1. Carriles ── */
.carriles-marco { position: relative; }
.lienzo { position: relative; display: grid; grid-template-columns: 158px repeat(5, minmax(212px, 1fr)); column-gap: 44px; padding-right: 44px; min-width: 1400px; }
.lienzo svg.flechas { position: absolute; inset: 0; pointer-events: none; z-index: 0; overflow: visible; }
.fase-cab { padding: 14px 0 10px; font: 700 0.8rem var(--f-display); letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-2); border-bottom: 2px solid var(--line); display: flex; gap: 8px; align-items: baseline; }
.fase-cab .id { color: var(--marca); }
.esquina { border-bottom: 2px solid var(--line); position: sticky; left: 0; z-index: 3; background: var(--surface); }
.carril-cab { position: sticky; left: 0; z-index: 3; padding: 14px 12px 14px 14px; border-bottom: 1px solid var(--line); display: flex; flex-direction: column; justify-content: center; gap: 2px; }
.carril-cab b { font: 700 0.98rem var(--f-display); }
.carril-cab span { font-size: 0.8rem; color: var(--ink-3); }
.carril-cab .ico { color: var(--ink-2); margin-bottom: 4px; }
.celda { position: relative; z-index: 1; padding: 14px 0; border-bottom: 1px solid var(--line); display: flex; flex-direction: column; gap: 22px; justify-content: center; min-height: 104px; }
.bandaA { background: var(--carril-a); }
.bandaB { background: var(--carril-b); }
.lienzo .fondo-carril { grid-column: 1 / -1; z-index: 0; margin-right: -44px; }
.paso { position: relative; text-align: left; appearance: none; font: inherit; color: inherit; background: var(--surface); border: 1px solid var(--line-strong); border-radius: var(--r); padding: 9px 11px 10px; cursor: pointer; box-shadow: 0 1px 2px rgb(20 33 48 / 0.06); transition: opacity 220ms ease, border-color 160ms ease, box-shadow 160ms ease; }
.paso:hover { border-color: var(--ink-3); }
.paso .cab { display: flex; align-items: center; gap: 8px; }
.paso .no { flex: none; width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center; font: 600 0.72rem var(--f-id); background: var(--surface-2); color: var(--ink-2); border: 1px solid var(--line-strong); }
.paso b { font: 600 0.95rem var(--f-display); line-height: 1.2; }
.paso p { margin: 5px 0 0; font-size: 0.84rem; color: var(--ink-2); line-height: 1.38; }
.paso .pant { margin-top: 7px; font: 400 0.7rem var(--f-id); color: var(--ink-3); }
.paso.lector { border-style: dashed; }
.paso.sis { background: var(--surface-2); }
.lienzo.recorriendo .paso.futuro { opacity: 0.28; }
.lienzo.recorriendo .paso.hecho .no { background: var(--primary); border-color: var(--primary); color: #fff; }
.paso.actual { border-color: var(--marca); box-shadow: 0 0 0 3px color-mix(in srgb, var(--marca) 22%, transparent); }
.paso.actual .no { background: var(--marca); border-color: var(--marca); color: #fff; }
.flecha { fill: none; stroke: var(--line-strong); stroke-width: 1.6; transition: stroke 200ms ease, opacity 200ms ease; }
.flecha.relevo { stroke: var(--ink-3); stroke-dasharray: 5 4; }
.lienzo.recorriendo .flecha.futura { opacity: 0.25; }
.flecha.viva { stroke: var(--marca); stroke-width: 2.2; stroke-dasharray: none; }
.punta { fill: var(--line-strong); }
.punta-viva { fill: var(--marca); }
.punta-relevo { fill: var(--ink-3); }

.guia { margin-top: 14px; display: grid; grid-template-columns: 1fr auto; gap: 12px 20px; align-items: center; padding: 14px 16px; }
.guia .texto { min-width: 0; }
.guia .meta { font-size: 0.8rem; color: var(--ink-3); display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; }
.guia .meta .id { color: var(--marca); font-weight: 600; }
.guia h3 { font-size: 1.15rem; margin-top: 2px; }
.guia p { margin: 3px 0 0; color: var(--ink-2); }
.guia .ctrl { display: flex; gap: 8px; flex-wrap: wrap; }
.leyenda { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-top: 12px; font-size: 0.82rem; color: var(--ink-2); }
.leyenda span { display: inline-flex; align-items: center; gap: 8px; }
.mini { display: inline-block; width: 22px; height: 14px; border: 1px solid var(--line-strong); border-radius: 3px; background: var(--surface); }
.mini.dash { border-style: dashed; }
.mini.gris { background: var(--surface-2); }
@media (max-width: 720px) { .guia { grid-template-columns: 1fr; } }

/* ── 2. Estados ── */
.estados-rejilla { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start; }
.diagramas { display: grid; gap: 16px; min-width: 0; }
.diagrama { padding: 14px 16px 6px; min-width: 0; }
.diagrama h3 { font-size: 1.08rem; display: flex; gap: 10px; align-items: baseline; flex-wrap: wrap; }
.diagrama h3 small { font: 500 0.82rem var(--f-ui); color: var(--ink-3); letter-spacing: 0; }
.diagrama svg { display: block; width: 100%; min-width: 720px; height: auto; margin-top: 6px; }
.nodo { cursor: pointer; }
.nodo rect { stroke-width: 1.5; transition: stroke-width 150ms ease; }
.nodo:hover rect, .nodo:focus-visible rect { stroke-width: 2.5; }
.nodo:focus { outline: none; }
.nodo.sel rect { stroke: var(--marca) !important; stroke-width: 3; }
.nodo text { font-family: var(--f-display); font-weight: 600; font-size: 15px; }
.nodo text.quien { font-family: var(--f-ui); font-weight: 500; font-size: 11.5px; }
.arista { fill: none; stroke: var(--ink-3); stroke-width: 1.6; }
.arista.atras { stroke: var(--marca); stroke-dasharray: 5 4; }
.etq { font-family: var(--f-ui); font-size: 11.5px; fill: var(--ink-2); paint-order: stroke; stroke: var(--surface); stroke-width: 4px; stroke-linejoin: round; }
.etq.atras { fill: var(--marca); font-weight: 600; }
.etq.nota { fill: var(--ink-3); font-style: italic; }
.ficha { position: sticky; top: 16px; padding: 16px; }
.ficha .vacia { color: var(--ink-3); font-size: 0.92rem; }
.ficha h3 { font-size: 1.25rem; margin: 6px 0 2px; }
.ficha dl { margin: 12px 0 0; display: grid; gap: 12px; }
.ficha dt { font: 700 0.72rem var(--f-display); letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-3); }
.ficha dd { margin: 2px 0 0; font-size: 0.92rem; }
.ficha dd .id { color: var(--ink-2); }
@media (max-width: 980px) { .estados-rejilla { grid-template-columns: 1fr; } .ficha { position: static; } }

/* ── 3. Pieza ── */
.pieza-rejilla { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 20px; align-items: start; }
.etiqueta-pieza { padding: 0; overflow: hidden; position: sticky; top: 16px; }
.etiqueta-pieza .top { background: var(--rail); color: var(--rail-ink); padding: 14px 16px; }
.etiqueta-pieza .top small { color: var(--rail-muted); font: 600 0.72rem var(--f-display); letter-spacing: 0.08em; text-transform: uppercase; }
.etiqueta-pieza .top .epc { font: 600 1rem var(--f-id); letter-spacing: 0.04em; margin-top: 4px; word-break: break-all; }
.etiqueta-pieza .top .epc .pre { color: var(--rail-muted); }
.etiqueta-pieza dl { margin: 0; padding: 12px 16px 16px; display: grid; gap: 10px; }
.etiqueta-pieza dt { font: 700 0.7rem var(--f-display); letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-3); }
.etiqueta-pieza dd { margin: 1px 0 0; }
.contenedor-id { font-family: var(--f-id); font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.contenedor-id .dv { border: 1.5px solid var(--marca); color: var(--marca); padding: 0 4px; border-radius: 2px; }
.existencia { margin: 0 16px 16px; border: 1px dashed var(--line-strong); border-radius: var(--r); padding: 10px 12px; font-size: 0.85rem; }
.existencia b { font: 700 0.72rem var(--f-display); letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink-3); display: block; margin-bottom: 6px; }
.existencia .seg { display: grid; grid-template-columns: auto 1fr; gap: 3px 10px; }
.existencia .seg span:nth-child(odd) { color: var(--ink-3); }
.linea-tiempo { list-style: none; margin: 0; padding: 0; position: relative; }
.linea-tiempo::before { content: ""; position: absolute; left: 15px; top: 8px; bottom: 8px; width: 2px; background: var(--line); }
.evento { position: relative; padding-left: 46px; padding-bottom: 18px; cursor: pointer; }
.evento .punto { position: absolute; left: 6px; top: 12px; width: 20px; height: 20px; border-radius: 50%; background: var(--surface); border: 2px solid var(--line-strong); display: grid; place-items: center; }
.evento.mov .punto { border-color: var(--primary); background: var(--primary); }
.evento.mov .punto::after { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--surface); }
.evento.sel .punto { border-color: var(--marca); box-shadow: 0 0 0 4px color-mix(in srgb, var(--marca) 22%, transparent); }
.evento.mov.sel .punto { background: var(--marca); }
.evento .tarjeta { padding: 12px 14px; transition: border-color 160ms ease; }
.evento:hover .tarjeta { border-color: var(--line-strong); }
.evento.sel .tarjeta { border-color: var(--marca); }
.evento .fila1 { display: flex; flex-wrap: wrap; gap: 6px 12px; align-items: center; font-size: 0.8rem; color: var(--ink-3); }
.evento h3 { font-size: 1.05rem; margin-top: 4px; }
.evento p { margin: 3px 0 0; color: var(--ink-2); font-size: 0.9rem; }
.trayecto { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; font-size: 0.82rem; }
.trayecto .pos { font-family: var(--f-id); font-weight: 600; background: var(--surface-2); border: 1px solid var(--line); border-radius: 3px; padding: 1px 6px; }
.trayecto .sal { color: var(--bad); border-color: color-mix(in srgb, var(--bad) 40%, var(--line)); }
.evento.nota-peps .tarjeta { background: var(--warn-bg); border-color: color-mix(in srgb, var(--warn) 35%, transparent); }
.evento.nota-peps .punto { border-style: dashed; border-color: var(--warn); }
.historial { margin-top: 8px; padding: 0; }
.historial h3 { font-size: 1.05rem; padding: 14px 16px 4px; }
.historial p { margin: 0; padding: 0 16px 10px; color: var(--ink-2); font-size: 0.88rem; }
table.mov { width: 100%; border-collapse: collapse; font-size: 0.86rem; min-width: 640px; }
table.mov th { text-align: left; font: 600 0.75rem var(--f-ui); color: var(--ink-2); background: var(--surface-2); padding: 8px 12px; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); white-space: nowrap; }
table.mov td { padding: 8px 12px; border-bottom: 1px solid var(--line); white-space: nowrap; }
table.mov tr:last-child td { border-bottom: 0; }
table.mov td.der { text-align: right; }
@media (max-width: 860px) { .pieza-rejilla { grid-template-columns: 1fr; } .etiqueta-pieza { position: static; } }

/* ── 4. Plano ── */
.plano-rejilla { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; align-items: start; }
.plano { padding: 10px; min-width: 0; }
.plano svg { display: block; width: 100%; min-width: 760px; height: auto; }
.zona { cursor: pointer; }
.zona > rect.fondo { stroke-width: 1.4; transition: stroke 150ms ease, stroke-width 150ms ease; }
.zona.alm > rect.fondo { fill: var(--zona-alm); stroke: var(--line-strong); }
.zona.op > rect.fondo { fill: var(--zona-op); stroke: color-mix(in srgb, var(--warn) 40%, var(--line-strong)); }
.zona.ofi > rect.fondo { fill: var(--surface-2); stroke: var(--line-strong); stroke-dasharray: 4 3; }
.zona:hover > rect.fondo, .zona:focus-visible > rect.fondo { stroke: var(--ink-2); stroke-width: 2; }
.zona.sel > rect.fondo { stroke: var(--marca); stroke-width: 2.6; }
.zona:focus { outline: none; }
.zona .tit { font: 700 14px var(--f-display); fill: var(--ink); letter-spacing: 0.01em; }
.zona .sub { font: 400 11px var(--f-id); fill: var(--ink-3); }
.plano .txt { font: 500 11.5px var(--f-ui); fill: var(--ink-2); }
.modulo { fill: var(--surface); stroke: var(--line-strong); stroke-width: 1; }
.modulo.off { fill: none; stroke-dasharray: 4 3; }
.modulo-n { font: 600 11px var(--f-id); fill: var(--ink-2); }
.nivel { stroke: var(--line); stroke-width: 1; }
.mesa { fill: var(--surface); stroke: var(--line-strong); }
.ruta { fill: none; stroke: var(--marca); stroke-width: 3; stroke-dasharray: 9 6; stroke-linecap: round; stroke-linejoin: round; }
.ruta-inc { fill: none; stroke: var(--bad); stroke-width: 1.8; stroke-dasharray: 3 4; }
.hito circle { fill: var(--marca); stroke: var(--surface); stroke-width: 3; }
.hito text { font: 700 13px var(--f-id); fill: #fff; }
.hito .lbl { font: 700 12px var(--f-display); fill: var(--marca); paint-order: stroke; stroke: var(--surface); stroke-width: 4px; stroke-linejoin: round; }
.lector-ico { color: var(--info); }
.lector-ico circle.base { fill: var(--info); }
.lector-ico path { fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
@media (prefers-reduced-motion: no-preference) { .ruta { animation: marcha 1.4s linear infinite; } }
@keyframes marcha { to { stroke-dashoffset: -30; } }
@media (max-width: 1040px) { .plano-rejilla { grid-template-columns: 1fr; } }
.pie { margin-top: 28px; font-size: 0.82rem; color: var(--ink-3); }
@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
/* Dentro del sistema (iframe en /proceso): sin cabecera propia, tema claro como la app */
.embebido .riel { background: transparent; color: var(--ink); }
.embebido .riel .envoltura, .embebido main .envoltura { max-width: none; padding-left: 0; padding-right: 0; }
.embebido .riel .envoltura { padding-block: 0; }
.embebido main .envoltura { padding-block: 18px 4px; }
.embebido .marca-app, .embebido .riel h1, .embebido .riel p.bajada { display: none; }
.embebido .pestanas { margin-top: 0; border-bottom: 1px solid var(--line); }
.embebido .pestanas button { color: var(--ink-2); }
.embebido .pestanas button:hover { color: var(--ink); background: var(--surface-2); }
.embebido .pestanas button[aria-selected="true"] { background: var(--surface); color: var(--ink); box-shadow: inset 0 0 0 1px var(--line); }
.embebido .ficha, .embebido .etiqueta-pieza { position: static; }
</style>
</head>
<body>
<header class="riel">
  <div class="envoltura">
    <div class="marca-app">
      <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="18" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 6v12M12 6v12M16 6v12" stroke="currentColor" stroke-width="1.5"/></svg>
      Almacén RFID · material de la demo
    </div>
    <h1>Cómo se mueve la mercancía</h1>
    <p class="bajada">Cuatro vistas del mismo proceso: quién hace cada paso, por qué estados pasa cada documento, la historia de una pieza y el recorrido en el almacén.</p>
    <nav class="pestanas" role="tablist" aria-label="Vistas">
      <button role="tab" id="tab-carriles" aria-controls="v-carriles" data-v="carriles"><span class="n">01</span>Quién hace qué</button>
      <button role="tab" id="tab-estados" aria-controls="v-estados" data-v="estados"><span class="n">02</span>Estados</button>
      <button role="tab" id="tab-pieza" aria-controls="v-pieza" data-v="pieza"><span class="n">03</span>Vida de una pieza</button>
      <button role="tab" id="tab-plano" aria-controls="v-plano" data-v="plano"><span class="n">04</span>Plano del almacén</button>
    </nav>
  </div>
</header>

<main>
  <div class="envoltura">

    <!-- 1 · Carriles -->
    <section id="v-carriles" role="tabpanel" aria-labelledby="tab-carriles">
      <div class="vista-cab">
        <div>
          <h2>Quién hace qué, de la llegada a la salida</h2>
          <p>Cada renglón es un puesto. Las flechas punteadas marcan cuando el trabajo pasa a otra persona o al sistema. Recórrelo paso a paso durante la demo.</p>
        </div>
      </div>
      <div class="panel carriles-marco">
        <div class="desliza" id="desliza-carriles">
          <div class="lienzo" id="lienzo"></div>
        </div>
      </div>
      <div class="panel guia" aria-live="polite">
        <div class="texto" id="guia-texto"></div>
        <div class="ctrl">
          <button class="btn" id="g-ant" type="button" aria-label="Paso anterior">← Anterior</button>
          <button class="btn primario" id="g-sig" type="button">Recorrer paso a paso</button>
          <button class="btn" id="g-todo" type="button" hidden>Ver todo</button>
        </div>
      </div>
      <div class="leyenda">
        <span><i class="mini"></i>Lo hace una persona</span>
        <span><i class="mini dash"></i>Lectura: RFID, código de barras o a mano</span>
        <span><i class="mini gris"></i>Lo hace el sistema</span>
        <span><svg width="34" height="10" aria-hidden="true"><path d="M1 5h32" class="flecha relevo"/></svg>Cambio de puesto</span>
      </div>
    </section>

    <!-- 2 · Estados -->
    <section id="v-estados" role="tabpanel" aria-labelledby="tab-estados" hidden>
      <div class="vista-cab">
        <div>
          <h2>Estados: qué tiene que pasar para avanzar</h2>
          <p>Cada caja es un estado que se ve en el sistema. Las flechas dicen qué lo hace avanzar y quién. Las naranjas punteadas son regresos. Toca un estado para ver el detalle.</p>
        </div>
      </div>
      <div class="estados-rejilla">
        <div class="diagramas">
          <div class="panel diagrama"><h3>Recepción <small>un contenedor contra lo que se esperaba</small></h3><div class="desliza"><svg id="sm-recepcion" viewBox="0 0 1000 250" role="group" aria-label="Estados de una recepción"></svg></div></div>
          <div class="panel diagrama"><h3>Pedido <small>de lo registrado a lo que sale por el andén</small></h3><div class="desliza"><svg id="sm-pedido" viewBox="0 0 1000 340" role="group" aria-label="Estados de un pedido"></svg></div></div>
          <div class="panel diagrama"><h3>Bulto por destino <small>dentro de un pedido, cada tienda se revisa por separado</small></h3><div class="desliza"><svg id="sm-destino" viewBox="0 0 1000 260" role="group" aria-label="Estados del bulto de un destino"></svg></div></div>
        </div>
        <aside class="panel ficha" id="ficha" aria-live="polite"></aside>
      </div>
    </section>

    <!-- 3 · Pieza -->
    <section id="v-pieza" role="tabpanel" aria-labelledby="tab-pieza" hidden>
      <div class="vista-cab">
        <div>
          <h2>La vida de una camisa, del contenedor a la tienda</h2>
          <p>Una pieza del contenedor de la demo. Cada punto relleno es un movimiento que queda en el historial; los huecos son pasos de control que no mueven inventario. Toca un evento para ver dónde está la pieza en ese momento.</p>
        </div>
      </div>
      <div class="pieza-rejilla">
        <aside class="panel etiqueta-pieza">
          <div class="top">
            <small>EPC de la etiqueta</small>
            <div class="epc"><span class="pre">3034001A2B11</span>00000A3F2C07</div>
          </div>
          <dl>
            <div><dt>SKU</dt><dd><span class="id">CAM-OXF-M</span> · Camisa oxford manga larga M</dd></div>
            <div><dt>Pedimento</dt><dd class="id">26 16 3156 6006841</dd></div>
            <div><dt>Contenedor</dt><dd><span class="contenedor-id">TXAU 402615 <span class="dv">6</span></span></dd></div>
            <div><dt>Aduana · pago</dt><dd>Manzanillo, Col. · 9 nov 2026</dd></div>
          </dl>
          <div class="existencia" id="existencia"></div>
        </aside>
        <div>
          <ol class="linea-tiempo" id="linea"></ol>
          <div class="panel historial">
            <h3>Lo que queda en Movimientos</h3>
            <p>Filtrando por el pedimento y el SKU, el sistema muestra esta secuencia. Nadie la escribe a mano: cada renglón lo genera la acción de la tarjeta de arriba.</p>
            <div class="desliza">
              <table class="mov">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Documento</th><th>De</th><th>A</th><th class="der">Piezas</th><th>Usuario</th></tr></thead>
                <tbody id="tabla-mov"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 4 · Plano -->
    <section id="v-plano" role="tabpanel" aria-labelledby="tab-plano" hidden>
      <div class="vista-cab">
        <div>
          <h2>El recorrido en el almacén</h2>
          <p>Plano esquemático con las ubicaciones de la demo. La línea naranja es el camino de la mercancía; los íconos azules, los puntos de lectura. Toca un área para ver qué pasa ahí y en qué pantalla se registra.</p>
        </div>
      </div>
      <div class="plano-rejilla">
        <div class="panel plano desliza"><svg id="plano-svg" viewBox="0 0 1000 600" role="group" aria-label="Plano del almacén"></svg></div>
        <aside class="panel ficha" id="ficha-plano" aria-live="polite"></aside>
      </div>
    </section>

    <p class="pie">Datos ficticios de la demo: Textiles Aurora, REC-0001 y PED-0001. Los formatos siguen los reales (pedimento de 15 dígitos, contenedor ISO 6346, ubicación pasillo-módulo-nivel).</p>
  </div>
</main>

<script>
const $ = (s, el = document) => el.querySelector(s);
const NS = 'http://www.w3.org/2000/svg';
const esc = (t) => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;');

/* ───────── Pestañas con hash ───────── */
const vistas = ['carriles', 'estados', 'pieza', 'plano'];
function mostrar(v) {
  if (!vistas.includes(v)) v = 'carriles';
  vistas.forEach((x) => {
    $('#v-' + x).hidden = x !== v;
    $('#tab-' + x).setAttribute('aria-selected', x === v);
    $('#tab-' + x).tabIndex = x === v ? 0 : -1;
  });
  if (v === 'carriles') requestAnimationFrame(dibujarFlechas);
}
document.querySelectorAll('.pestanas button').forEach((b) => b.addEventListener('click', () => {
  if (location.hash !== '#' + b.dataset.v) location.hash = b.dataset.v; else mostrar(b.dataset.v);
}));
addEventListener('hashchange', () => mostrar(location.hash.slice(1)));

/* ───────── 1 · Carriles ───────── */
const carriles = [
  { k: 'resp', n: 'Responsable', d: 'Oficina · computadora', ico: '<path d="M4 6h16v10H4zM2 19h20" />' },
  { k: 'aux', n: 'Auxiliar de recepción', d: 'Tablet en recepción', ico: '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M11 18h2"/>' },
  { k: 'oper', n: 'Operador de piso', d: 'Tablet en racks y mesas', ico: '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M11 18h2"/>' },
  { k: 'lector', n: 'Lectura', d: 'RFID · código · a mano', ico: '<circle cx="8" cy="16" r="2"/><path d="M12 12a6 6 0 0 1 0 8M15 9a10 10 0 0 1 0 14"/>' },
  { k: 'sis', n: 'Sistema', d: 'Almacén RFID', ico: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>' },
];
const fases = ['Recepción', 'Ubicación', 'Pedido y liberación', 'Toma y separación', 'Revisión y salida'];
const pasos = [
  ['resp', 0, 'Carga lo que se espera', 'Excel del propietario o captura a mano. La recepción queda Esperada.', 'Recepciones › Importar', 'Es la base para comparar: SKU, cajas y piezas que deben venir en el contenedor, ligadas a su pedimento.'],
  ['aux', 0, 'Abre la recepción', 'En la tablet elige REC-0001 y cuenta caja por caja.', 'Operador › Recepción', 'El auxiliar no captura cantidades: cada caja que lee se suma sola.'],
  ['lector', 0, 'Lee la caja', 'Lector RFID. Sin lector: código de barras de la caja o captura a mano.', 'Operador › Recepción', 'Cada caja queda registrada con su origen (RFID, código o manual) para saber qué tan confiable fue el conteo.'],
  ['sis', 0, 'Suma y compara', 'Agrupa por SKU y lo pone contra lo esperado al momento.', 'Recepciones › REC-0001', 'Faltantes y sobrantes se ven en vivo, antes de cerrar.'],
  ['aux', 0, 'Pega la etiqueta de la caja', 'Imprime la etiqueta con folio, SKU, piezas y pedimento.', 'Operador › Recepción', 'Las cajas no traen etiqueta RFID maestra. Con su etiqueta, la caja completa se identifica después escaneando un código de barras, sin abrirla.'],
  ['aux', 0, 'Termina el conteo', 'Anota piezas dañadas o sin etiqueta. Queda Contada · por cerrar.', 'Operador › Recepción', 'Si después se lee o corrige una caja, la recepción regresa sola a En conteo.'],
  ['resp', 0, 'Revisa y cierra', 'Lo bueno entra a REC-01; cada diferencia queda como incidencia.', 'Recepciones › REC-0001', 'Aquí nace la existencia por SKU y pedimento. Lo dañado entra a INC-01, apartado.'],
  ['resp', 1, 'Decide dónde va', 'Elige área y posición: pasillo, módulo y nivel.', 'Recepciones › Ubicar', 'El sistema solo ofrece posiciones activas con lugar.'],
  ['oper', 1, 'Acomoda en el rack', 'Lleva las cajas a la posición; el rótulo del rack dice dónde.', '—', 'Cada posición tiene su rótulo con pasillo, módulo, nivel y código de barras, impreso desde el mapa. Si la posición cambia después se registra como reubicación.'],
  ['sis', 1, 'Registra la existencia', 'SKU + pedimento + ubicación + estado, con su movimiento.', 'Inventario › Mapa', 'Desde aquí el mapa muestra qué hay en cada posición y de qué pedimento.'],
  ['resp', 2, 'Registra el pedido', 'Piezas por SKU y destino. Queda Registrado · por liberar.', 'Pedidos › Nuevo', 'Un mismo pedido puede ir a varias tiendas y al almacén de comercio electrónico.'],
  ['resp', 2, 'Lo libera a piso', 'Decide cuándo se empieza a trabajar.', 'Pedidos › PED-0001', 'Separar registrar de liberar permite revisar el pedido antes de mover a la gente.'],
  ['sis', 2, 'Arma las instrucciones', 'Propone de qué pedimento y ubicación tomar y genera las tomas.', 'Operador › Surtido', 'Cómo se elige el pedimento es una regla por confirmar con el cliente; en la demo es un criterio provisional. El operador no elige: va a donde le dicen.'],
  ['oper', 3, 'Toma las piezas', 'Va a cada ubicación y confirma lo tomado.', 'Operador › Surtido', 'Si no encuentra lo que dice el sistema, lo marca como faltante y se abre incidencia.'],
  ['sis', 3, 'Pasa a preparación', 'Las piezas quedan en PREP-01, En preparación.', 'Inventario', 'Siguen siendo inventario, pero ya no están disponibles para otro pedido.'],
  ['oper', 3, 'Separa por destino', 'Arma y etiqueta el bulto de cada tienda: comercial, sensor y etiqueta de bulto.', 'Operador › Separación', 'Cada destino tiene sus actividades: etiqueta comercial, sensor de seguridad, lo que pida la cadena. Al confirmarlo se imprime una etiqueta por bulto: tienda, pedido, bulto 1 de N y contenido.'],
  ['lector', 4, 'Lee cada bulto', 'Con lector, o conteo a mano si no hay.', 'Pedidos › PED-0001', 'Sustituye la revisión que hoy se hace con video.'],
  ['sis', 4, 'Cuadra contra lo pedido', 'Barra de cuadre por destino. Una diferencia detiene la salida.', 'Pedidos › PED-0001', 'Muestra exactamente qué SKU sobra o falta y en qué bulto.'],
  ['resp', 4, 'Resuelve y verifica', 'Acepta la lectura, corrige el bulto o abre incidencia.', 'Pedidos › PED-0001', 'Cuando todos los destinos están verificados, el pedido pasa a Listo para salir.'],
  ['resp', 4, 'Autoriza la salida', 'Captura transportista, placas y sello.', 'Pedidos › PED-0001', 'Las condiciones de salida quedan ligadas al folio.'],
  ['sis', 4, 'Descuenta y registra', 'Sale del inventario con folio de salida y queda en el historial.', 'Movimientos', 'Cierra el ciclo: de cualquier pieza se puede saber de qué pedimento vino y a qué tienda fue.'],
];
const lienzo = $('#lienzo');
function armarCarriles() {
  let h = '<div class="esquina"></div>' + fases.map((f, i) => `<div class="fase-cab"><span class="id">${i + 1}</span>${f}</div>`).join('');
  carriles.forEach((c, r) => {
    const banda = r % 2 ? 'bandaB' : 'bandaA';
    h += `<div class="carril-cab ${banda}"><svg class="ico" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${c.ico}</svg><b>${c.n}</b><span>${c.d}</span></div>`;
    fases.forEach((_, col) => {
      const dentro = pasos.map((p, i) => ({ p, i })).filter(({ p }) => p[0] === c.k && p[1] === col);
      h += `<div class="celda ${banda}">` + dentro.map(({ p, i }) =>
        `<button type="button" class="paso ${c.k === 'lector' ? 'lector' : ''} ${c.k === 'sis' ? 'sis' : ''}" data-i="${i}" id="paso-${i}">
          <span class="cab"><span class="no">${i + 1}</span><b>${p[2]}</b></span>
          <p>${p[3]}</p>${p[4] !== '—' ? `<div class="pant">${p[4]}</div>` : ''}</button>`).join('') + '</div>';
    });
  });
  h += '<svg class="flechas" id="flechas" aria-hidden="true"></svg>';
  lienzo.innerHTML = h;
  lienzo.querySelectorAll('.paso').forEach((b) => b.addEventListener('click', () => irA(+b.dataset.i)));
}
function dibujarFlechas() {
  const svg = $('#flechas');
  if (!svg || !lienzo.offsetWidth) return;
  const base = lienzo.getBoundingClientRect();
  svg.setAttribute('width', lienzo.scrollWidth); svg.setAttribute('height', lienzo.scrollHeight);
  svg.setAttribute('viewBox', `0 0 ${lienzo.scrollWidth} ${lienzo.scrollHeight}`);
  const rc = (i) => { const r = $('#paso-' + i).getBoundingClientRect(); return { l: r.left - base.left, t: r.top - base.top, r: r.right - base.left, b: r.bottom - base.top, w: r.width, h: r.height }; };
  let h = `<defs>
    <marker id="pt" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" class="punta"/></marker>
    <marker id="pt-r" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" class="punta-relevo"/></marker>
    <marker id="pt-v" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" class="punta-viva"/></marker></defs>`;
  for (let i = 0; i < pasos.length - 1; i++) {
    const a = rc(i), b = rc(i + 1), pa = pasos[i], pb = pasos[i + 1];
    const relevo = pa[0] !== pb[0];
    let d;
    if (pa[1] !== pb[1]) {
      const y1 = a.t + a.h / 2, y2 = b.t + b.h / 2;
      d = `M${a.r} ${y1} C${a.r + 30} ${y1} ${b.l - 30} ${y2} ${b.l - 1} ${y2}`;
    } else if (pa[0] === pb[0]) {
      const x = a.l + 26; d = `M${x} ${a.b} L${x} ${b.t - 1}`;
    } else if (b.t > a.t) {
      const x = a.l - 12 - (i % 2) * 9, y1 = a.t + a.h * 0.68, y2 = b.t + b.h * 0.32;
      d = `M${a.l} ${y1} H${x + 4} Q${x} ${y1} ${x} ${y1 + 4} V${y2 - 4} Q${x} ${y2} ${x + 4} ${y2} H${b.l - 1}`;
    } else {
      const x = a.r + 12 + (i % 2) * 9, y1 = a.t + a.h * 0.32, y2 = b.t + b.h * 0.68;
      d = `M${a.r} ${y1} H${x - 4} Q${x} ${y1} ${x} ${y1 - 4} V${y2 + 4} Q${x} ${y2} ${x - 4} ${y2} H${b.r + 1}`;
    }
    h += `<path d="${d}" class="flecha ${relevo ? 'relevo' : ''}" data-f="${i}" data-relevo="${relevo ? 1 : 0}" marker-end="url(#${relevo ? 'pt-r' : 'pt'})"/>`;
  }
  svg.innerHTML = h;
  pintarRecorrido();
}
let actual = -1;
function pintarRecorrido() {
  lienzo.classList.toggle('recorriendo', actual >= 0);
  lienzo.querySelectorAll('.paso').forEach((el) => {
    const i = +el.dataset.i;
    el.classList.toggle('actual', i === actual);
    el.classList.toggle('hecho', actual >= 0 && i < actual);
    el.classList.toggle('futuro', actual >= 0 && i > actual);
  });
  lienzo.querySelectorAll('.flecha').forEach((f) => {
    const i = +f.dataset.f, viva = actual >= 0 && i === actual - 1;
    f.classList.toggle('viva', viva);
    f.classList.toggle('futura', actual >= 0 && i >= actual);
    f.setAttribute('marker-end', `url(#${viva ? 'pt-v' : f.dataset.relevo === '1' ? 'pt-r' : 'pt'})`);
  });
  const g = $('#guia-texto');
  if (actual < 0) {
    g.innerHTML = `<div class="meta">${pasos.length} pasos · 5 puestos · 3 momentos de lectura</div><h3>Empieza en la oficina y termina en el andén</h3><p>La responsable abre y cierra cada etapa; el auxiliar y el operador ejecutan desde la tablet; el sistema hace las sumas y las comparaciones.</p>`;
  } else {
    const p = pasos[actual], c = carriles.find((x) => x.k === p[0]);
    g.innerHTML = `<div class="meta"><span class="id">Paso ${actual + 1} de ${pasos.length}</span><span>${c.n}</span><span>${fases[p[1]]}</span>${p[4] !== '—' ? `<span class="id" style="color:var(--ink-3);font-weight:400">${p[4]}</span>` : ''}</div><h3>${p[2]}</h3><p>${p[5]}</p>`;
  }
  $('#g-ant').disabled = actual <= 0;
  $('#g-sig').textContent = actual < 0 ? 'Recorrer paso a paso' : actual >= pasos.length - 1 ? 'Volver a empezar' : 'Siguiente →';
  $('#g-todo').hidden = actual < 0;
}
function irA(i) {
  actual = i; pintarRecorrido();
  const el = $('#paso-' + i), cont = $('#desliza-carriles');
  if (el && cont) {
    const r = el.getBoundingClientRect(), c = cont.getBoundingClientRect();
    if (r.left < c.left + 170 || r.right > c.right) cont.scrollBy({ left: r.left - c.left - 200, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }
}
$('#g-sig').addEventListener('click', () => irA(actual >= pasos.length - 1 ? 0 : actual + 1));
$('#g-ant').addEventListener('click', () => actual > 0 && irA(actual - 1));
$('#g-todo').addEventListener('click', () => { actual = -1; pintarRecorrido(); });
addEventListener('keydown', (e) => {
  if ($('#v-carriles').hidden || e.target.matches('input, textarea')) return;
  if (e.key === 'ArrowRight') { e.preventDefault(); irA(Math.min(pasos.length - 1, actual + 1)); }
  if (e.key === 'ArrowLeft' && actual > 0) { e.preventDefault(); irA(actual - 1); }
});
armarCarriles();
addEventListener('resize', () => requestAnimationFrame(dibujarFlechas));
document.fonts && document.fonts.ready.then(() => dibujarFlechas());
new ResizeObserver(() => dibujarFlechas()).observe(lienzo);

/* ───────── 2 · Estados ───────── */
const tonos = { neutro: ['--neutro', '--neutro-bg'], info: ['--info', '--info-bg'], warn: ['--warn', '--warn-bg'], ok: ['--ok', '--ok-bg'], bad: ['--bad', '--bad-bg'] };
const maquinas = {
  recepcion: {
    w: 160, h: 64,
    nodos: [
      { k: 'esperada', n: 'Esperada', q: 'responsable', t: 'neutro', x: 20, y: 70,
        quien: 'La responsable, al importar el Excel del propietario o capturar a mano.', donde: 'Recepciones › REC-0001', puede: 'Agregar, quitar o corregir líneas esperadas mientras no se empiece a contar.', sale: 'Cuando el auxiliar lee la primera caja.' },
      { k: 'en_conteo', n: 'En conteo', q: 'auxiliar', t: 'info', x: 290, y: 70,
        quien: 'El auxiliar de recepción, desde la tablet.', donde: 'Operador › Recepción', puede: 'Leer caja por caja con RFID, código de barras o a mano; imprimir la etiqueta de cada caja; releer o anular una caja; anotar dañadas y sin etiqueta.', sale: 'Cuando el auxiliar presiona Terminar conteo.' },
      { k: 'contada', n: 'Contada · por cerrar', q: 'responsable', t: 'warn', x: 560, y: 70,
        quien: 'El auxiliar terminó; ahora decide la responsable.', donde: 'Recepciones (índice y detalle)', puede: 'Revisar diferencias por SKU con la barra de cuadre antes de cerrar.', sale: 'Cierra la responsable. Si alguien vuelve a leer o corrige una caja, regresa a En conteo.' },
      { k: 'cerrada', n: 'Cerrada', q: 'sistema', t: 'ok', x: 830, y: 70,
        quien: 'La responsable al cerrar.', donde: 'Recepciones › REC-0001 · Movimientos', puede: 'Ubicar cada línea en una posición del mapa.', sale: 'Es final. Lo bueno entró a REC-01; faltantes, sobrantes y dañados quedaron como incidencias.' },
    ],
    aristas: [
      { de: 0, a: 1, l: ['Primera caja', 'leída'] },
      { de: 1, a: 2, l: ['Terminar conteo', '(auxiliar)'] },
      { de: 2, a: 3, l: ['Cerrar', '(responsable)'] },
      { atras: 'M640 134 C640 214 370 214 370 136', lx: 505, ly: 226, l: ['Se lee, corrige o anula una caja'] },
    ],
    notas: [{ x: 910, y: 160, l: ['Entra a REC-01', 'diferencias → incidencias', 'sigue: ubicar'] }],
  },
  pedido: {
    w: 200, h: 64,
    nodos: [
      { k: 'nuevo', n: 'Registrado · por liberar', q: 'responsable', t: 'neutro', x: 40, y: 50,
        quien: 'La responsable al capturar el pedido.', donde: 'Pedidos › Nuevo · Pedidos › PED-0001', puede: 'Revisar piezas por SKU y destino antes de mandarlo a piso.', sale: 'Cuando la responsable lo libera: el sistema propone pedimento y ubicación y genera las tomas.' },
      { k: 'en_surtido', n: 'En surtido', q: 'operador', t: 'info', x: 400, y: 50,
        quien: 'El operador toma las piezas de cada ubicación.', donde: 'Operador › Surtido', puede: 'Confirmar cada toma o marcarla con faltante (abre incidencia).', sale: 'Cuando todas las tomas están confirmadas. Las piezas quedan en PREP-01.' },
      { k: 'en_separacion', n: 'Separando por destino', q: 'operador', t: 'info', x: 760, y: 50,
        quien: 'El operador en las mesas de preparación.', donde: 'Operador › Separación', puede: 'Armar el bulto de cada destino y marcar sus actividades: etiqueta comercial, sensor.', sale: 'Cuando todos los destinos están separados.' },
      { k: 'en_revision', n: 'En revisión', q: 'responsable', t: 'warn', x: 40, y: 210,
        quien: 'Lectura del bulto y decisión de la responsable.', donde: 'Pedidos › PED-0001 (barra de cuadre por destino)', puede: 'Leer cada bulto con RFID o contarlo a mano; aceptar la lectura, corregir el bulto o abrir incidencia.', sale: 'Cuando todos los destinos están verificados. Una diferencia abierta lo detiene aquí.' },
      { k: 'listo', n: 'Listo para salir', q: 'responsable', t: 'ok', x: 400, y: 210,
        quien: 'El sistema, al verificar el último destino.', donde: 'Pedidos › PED-0001', puede: 'Capturar condiciones de salida: transportista, placas y sello.', sale: 'Cuando la responsable autoriza la salida.' },
      { k: 'despachado', n: 'Despachado', q: 'sistema', t: 'neutro', x: 760, y: 210,
        quien: 'La responsable al autorizar.', donde: 'Pedidos · Movimientos', puede: 'Consultar folio de salida e historial.', sale: 'Es final. El inventario se descontó de PREP-01 con folio de salida.' },
    ],
    aristas: [
      { de: 0, a: 1, l: ['Liberar a piso', '(responsable)'] },
      { de: 1, a: 2, l: ['Todas las tomas', 'confirmadas'] },
      { d: 'M860 114 V160 H140 V208', lx: 500, ly: 152, l: ['Todos los destinos separados'] },
      { de: 3, a: 4, l: ['Todos los destinos', 'verificados'] },
      { de: 4, a: 5, l: ['Autorizar salida', '(responsable)'] },
      { atras: 'M95 274 C95 326 185 326 185 276', lx: 200, ly: 318, anchor: 'start', l: ['Diferencia: corregir o aceptar lectura'] },
    ],
    notas: [],
  },
  destino: {
    w: 200, h: 56,
    nodos: [
      { k: 'pendiente', n: 'Por separar', q: 'operador', t: 'neutro', x: 40, y: 100,
        quien: 'Se crea con el pedido, uno por destino.', donde: 'Operador › Separación', puede: 'Esperar a que el operador arme el bulto.', sale: 'Cuando el operador confirma el destino.' },
      { k: 'separado', n: 'Separado, sin revisar', q: 'lectura', t: 'info', x: 370, y: 100,
        quien: 'El operador al confirmar.', donde: 'Pedidos › PED-0001', puede: 'Leer el bulto con RFID o contarlo a mano.', sale: 'Según cuadre o no contra lo pedido.' },
      { k: 'verificado', n: 'Verificado', q: 'sistema', t: 'ok', x: 720, y: 24,
        quien: 'La lectura cuadró, o la responsable aceptó o corrigió.', donde: 'Pedidos › PED-0001', puede: 'Nada pendiente para ese destino.', sale: 'Cuando todos los destinos están aquí, el pedido pasa a Listo para salir.' },
      { k: 'con_diferencia', n: 'Con diferencia', q: 'responsable', t: 'bad', x: 720, y: 176,
        quien: 'La lectura no cuadró: sobra o falta algún SKU.', donde: 'Pedidos › PED-0001 (barra de cuadre en rojo)', puede: 'Corregir el bulto y releer, aceptar la lectura o abrir incidencia.', sale: 'Cuando se resuelve. Mientras tanto, el pedido no puede salir.' },
    ],
    aristas: [
      { de: 0, a: 1, l: ['Operador confirma', 'destino'] },
      { d: 'M570 128 C650 128 640 52 718 52', lx: 655, ly: 70, l: ['Cuadra'] },
      { d: 'M570 128 C650 128 640 204 718 204', lx: 655, ly: 196, l: ['No cuadra'] },
      { atras: 'M820 176 V82', lx: 834, ly: 134, anchor: 'start', l: ['Corregir o', 'aceptar lectura'] },
    ],
    notas: [],
  },
};
function textoMulti(x, y, lineas, cls, anchor = 'middle', dy = 15) {
  return `<text x="${x}" y="${y}" text-anchor="${anchor}" class="${cls}">` + lineas.map((l, i) => `<tspan x="${x}" dy="${i ? dy : 0}">${esc(l)}</tspan>`).join('') + '</text>';
}
function dibujarMaquina(id, m) {
  const svg = $('#sm-' + id);
  let h = `<defs><marker id="sm-pt-${id}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" style="fill:var(--ink-3)"/></marker>
    <marker id="sm-pa-${id}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" style="fill:var(--marca)"/></marker></defs>`;
  m.aristas.forEach((a) => {
    if (a.atras) {
      h += `<path d="${a.atras}" class="arista atras" marker-end="url(#sm-pa-${id})"/>` + textoMulti(a.lx, a.ly, a.l, 'etq atras', a.anchor || 'middle', 14);
    } else if (a.d) {
      h += `<path d="${a.d}" class="arista" marker-end="url(#sm-pt-${id})"/>` + textoMulti(a.lx, a.ly, a.l, 'etq', 'middle', 14);
    } else {
      const A = m.nodos[a.de], B = m.nodos[a.a], y = A.y + m.h / 2, x1 = A.x + m.w, x2 = B.x - 2, cx = (x1 + x2) / 2;
      h += `<path d="M${x1} ${y} H${x2}" class="arista" marker-end="url(#sm-pt-${id})"/>` + textoMulti(cx, y - 22, a.l, 'etq', 'middle', 14);
    }
  });
  m.notas.forEach((n) => { h += textoMulti(n.x, n.y, n.l, 'etq nota', 'middle', 15); });
  m.nodos.forEach((n, i) => {
    const [c, bg] = tonos[n.t];
    h += `<g class="nodo" tabindex="0" role="button" data-m="${id}" data-i="${i}" aria-label="${esc(n.n)}">
      <rect x="${n.x}" y="${n.y}" width="${m.w}" height="${m.h}" rx="6" style="fill:var(${bg});stroke:var(${c})"/>
      <text x="${n.x + m.w / 2}" y="${n.y + m.h / 2 - (m.h > 60 ? 3 : 2)}" text-anchor="middle" style="fill:var(${c})">${esc(n.n)}</text>
      <text class="quien" x="${n.x + m.w / 2}" y="${n.y + m.h / 2 + 15}" text-anchor="middle" style="fill:var(--ink-3)">${n.q}</text></g>`;
  });
  svg.innerHTML = h;
}
Object.entries(maquinas).forEach(([k, m]) => dibujarMaquina(k, m));
const nombresMaquina = { recepcion: 'Recepción', pedido: 'Pedido', destino: 'Bulto por destino' };
function verEstado(id, i) {
  document.querySelectorAll('.nodo').forEach((g) => g.classList.toggle('sel', g.dataset.m === id && +g.dataset.i === i));
  const n = maquinas[id].nodos[i];
  $('#ficha').innerHTML = `<span class="chip t-${n.t}">${nombresMaquina[id]}</span><h3>${esc(n.n)}</h3>
    <dl><div><dt>Quién lo provoca</dt><dd>${n.quien}</dd></div>
    <div><dt>Dónde se ve</dt><dd class="id">${n.donde}</dd></div>
    <div><dt>Qué se puede hacer</dt><dd>${n.puede}</dd></div>
    <div><dt>Cómo sale de aquí</dt><dd>${n.sale}</dd></div></dl>`;
}
document.querySelectorAll('.nodo').forEach((g) => {
  const ir = () => verEstado(g.dataset.m, +g.dataset.i);
  g.addEventListener('click', ir);
  g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ir(); } });
});
verEstado('recepcion', 2);

/* ───────── 3 · Vida de una pieza ───────── */
const eventos = [
  { f: '09 nov · 13:20', t: 'Se paga el pedimento', p: 'Aduana de Manzanillo. La camisa viaja en el contenedor TXAU 402615 6 junto con 119 camisas más de su SKU.', donde: 'En tránsito', ex: null },
  { f: '12 nov · 09:12', t: 'Se espera en REC-0001', p: 'El Excel del propietario dice 5 cajas de 24 piezas de CAM-OXF-M. La recepción queda Esperada.', quien: 'Laura Méndez', ex: null },
  { f: '12 nov · 09:41', t: 'Su caja se lee en recepción', p: 'Caja 3 de 5 de CAM-OXF-M: 24 EPC leídos por el lector. Se le pega la etiqueta REC-0001-C03. El sistema ya la cuenta, pero todavía no es inventario.', quien: 'Auxiliar · tablet', ex: null },
  { f: '12 nov · 10:58', mov: 'Recepción', t: 'Entra al inventario', p: 'La responsable cierra REC-0001. Las 120 camisas cuadran y entran a la zona de recepción.', quien: 'Laura Méndez', de: 'Contenedor', a: 'REC-01', est: 'Almacenado', ex: ['REC-01', 'Almacenado'], doc: 'REC-0001' },
  { f: '12 nov · 12:30', mov: 'Ubicación', t: 'Se acomoda en el rack', p: 'La responsable elige A-01-3 en el mapa y el operador sube las cajas.', quien: 'Laura Méndez', de: 'REC-01', a: 'A-01-3', est: 'Almacenado', ex: ['A-01-3', 'Almacenado'], doc: 'REC-0001' },
  { peps: true, f: '13 nov – 2 dic', t: '¿De qué pedimento se surte?', p: 'PED-0001 pide 72 camisas M y hay de tres pedimentos. En la demo el sistema propone las de enero y mayo, y la camisa de noviembre se queda en A-01-3. Qué pedimento sale primero es una regla que el cliente todavía tiene que definir.', ex: ['A-01-3', 'Almacenado'] },
  { f: '03 dic · 08:15', t: 'Un pedido la reserva', p: 'Se libera PED-0007 (pedido de ejemplo) con 36 camisas M para Tienda Coyoacán. El sistema propone tomarla de A-01-3.', quien: 'Laura Méndez', ex: ['A-01-3', 'Almacenado'] },
  { f: '03 dic · 09:02', mov: 'Surtido', t: 'Se toma del rack', p: 'Instrucción en la tablet: ir a A-01-3, tomar 36 de pedimento 26 16 3156 6006841.', quien: 'Jorge Ríos', de: 'A-01-3', a: 'PREP-01', est: 'En preparación', ex: ['PREP-01', 'En preparación'], doc: 'PED-0007' },
  { f: '03 dic · 09:40', t: 'Va al bulto de T-022', p: 'En la mesa de Coyoacán se le pone etiqueta comercial y el operador confirma el destino. El bulto sale con su etiqueta PED-0007-T-022-B1.', quien: 'Jorge Ríos', ex: ['PREP-01 · bulto T-022', 'En preparación'] },
  { f: '03 dic · 10:05', t: 'Se lee el bulto', p: 'La lectura del bulto encuentra 36 de 36 CAM-OXF-M. El destino queda Verificado.', quien: 'Lectura RFID', ex: ['PREP-01 · bulto T-022', 'En preparación'] },
  { f: '03 dic · 11:30', mov: 'Salida', t: 'Sale por el andén', p: 'La responsable autoriza con transportista, placas y sello. El inventario se descuenta con folio de salida.', quien: 'Laura Méndez', de: 'PREP-01', a: 'T-022 Coyoacán', sal: true, est: 'Fuera del almacén', ex: ['Tienda Coyoacán (T-022)', 'Despachado'], doc: 'SAL-0004' },
];
function verEvento(i) {
  document.querySelectorAll('.evento').forEach((e) => e.classList.toggle('sel', +e.dataset.i === i));
  const ev = eventos[i], ex = ev.ex;
  $('#existencia').innerHTML = `<b>Dónde está en ese momento</b>` + (ex
    ? `<div class="seg"><span>SKU</span><span class="id">CAM-OXF-M</span><span>Pedimento</span><span class="id">…6006841</span><span>Ubicación</span><span class="id">${ex[0]}</span><span>Estado</span><span>${ex[1]}</span></div>`
    : `<div style="color:var(--ink-3)">Todavía no es inventario. Existe como línea esperada y lectura, no como existencia.</div>`);
}
$('#linea').innerHTML = eventos.map((e, i) => `
  <li class="evento ${e.mov ? 'mov' : ''} ${e.peps ? 'nota-peps' : ''}" data-i="${i}" tabindex="0">
    <span class="punto" aria-hidden="true"></span>
    <div class="panel tarjeta">
      <div class="fila1"><span class="num">${e.f}</span>${e.mov ? `<span class="chip t-info">Movimiento · ${e.mov}</span>` : e.peps ? '<span class="chip t-warn">Por confirmar</span>' : '<span class="chip t-neutro">Control</span>'}${e.quien ? `<span>${e.quien}</span>` : ''}</div>
      <h3>${e.t}</h3><p>${e.p}</p>
      ${e.de ? `<div class="trayecto"><span class="pos">${e.de}</span><span aria-hidden="true">→</span><span class="pos ${e.sal ? 'sal' : ''}">${e.a}</span><span style="color:var(--ink-3)">· ${e.est}</span></div>` : ''}
    </div></li>`).join('');
document.querySelectorAll('.evento').forEach((el) => {
  el.addEventListener('click', () => verEvento(+el.dataset.i));
  el.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); verEvento(+el.dataset.i); } });
});
$('#tabla-mov').innerHTML = eventos.filter((e) => e.mov).map((e) => `<tr><td class="num">${e.f.replace(' · ', ' 2026 · ')}</td><td><span class="chip t-info">${e.mov}</span></td><td class="id">${e.doc}</td><td class="id">${e.de}</td><td class="id">${e.a}</td><td class="der num">${e.mov === 'Recepción' || e.mov === 'Ubicación' ? 120 : 36}</td><td>${e.quien}</td></tr>`).join('');
verEvento(4);

/* ───────── 4 · Plano ───────── */
const lector = (x, y) => `<g class="lector-ico" transform="translate(${x} ${y})"><circle class="base" cx="0" cy="0" r="4"/><path d="M7 -7a10 10 0 0 1 0 14M12 -12a17 17 0 0 1 0 24"/></g>`;
const zonasPlano = {
  descarga: { n: 'Andén de descarga', t: 'Operativa', que: 'Llega el contenedor con su pedimento. Se descargan las cajas tal como vienen del origen, con RFID de fábrica.', quien: 'Personal de recepción', pant: '—', lectura: 'No. Todavía no se cuenta.' },
  recepcion: { n: 'Recepción · REC-01', t: 'Operativa', que: 'Se cuenta caja por caja contra lo esperado y a cada caja se le pega su etiqueta. Al cerrar, lo bueno entra al inventario aquí mismo.', quien: 'Auxiliar con tablet; cierra la responsable', pant: 'Operador › Recepción · Recepciones › REC-0001', lectura: 'Sí. Lector RFID por caja; sin lector, código de barras o a mano.' },
  incidencia: { n: 'Incidencias · INC-01', t: 'Operativa', que: 'Piezas dañadas, sin accesorio o con alguna diferencia. Quedan apartadas y no se pueden surtir hasta que se liberan.', quien: 'Responsable', pant: 'Incidencias', lectura: 'No.' },
  principal: { n: 'Bodega principal · racks A y B', t: 'Almacenaje', que: 'Posiciones pasillo-módulo-nivel: A-01-1 a B-04-3, 24 posiciones. Cada una guarda SKU + pedimento y lleva su rótulo con código de barras.', quien: 'Operador acomoda y toma; la responsable decide dónde', pant: 'Inventario › Mapa · Operador › Surtido', lectura: 'No hace falta: la instrucción dice ubicación y pedimento.' },
  temporada: { n: 'Área Temporada · AD-01, AD-02', t: 'Almacenaje', que: 'Posiciones adicionales para los picos de enero, mayo y agosto. AD-02 está inactiva: no se ofrece al ubicar.', quien: 'Responsable las activa o desactiva', pant: 'Inventario › Mapa', lectura: 'No.' },
  oficina: { n: 'Oficina de la responsable', t: 'Control', que: 'Carga lo esperado, cierra recepciones, decide ubicaciones, registra y libera pedidos, resuelve diferencias y autoriza salidas.', quien: 'Responsable (Laura Méndez en la demo)', pant: 'Tablero · Recepciones · Pedidos', lectura: 'No.' },
  preparacion: { n: 'Preparación · PREP-01', t: 'Operativa', que: 'Mesas por destino: T-014, T-022, T-031 y comercio electrónico. Se separa, se etiqueta (comercial, sensor y etiqueta de bulto) y se revisa cada bulto antes de salir.', quien: 'Operador separa; la responsable verifica', pant: 'Operador › Separación · Pedidos › PED-0001', lectura: 'Sí. Estación de revisión: lectura del bulto o conteo a mano.' },
  salida: { n: 'Andén de salida', t: 'Operativa', que: 'Los bultos verificados se cargan. Se registran transportista, placas y sello y se descuenta el inventario.', quien: 'Responsable autoriza', pant: 'Pedidos › PED-0001 · Movimientos', lectura: 'No. Lo que sale ya se leyó en revisión.' },
};
function armarPlano() {
  let h = `<defs><marker id="pl-pt" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="5" markerHeight="5" orient="auto"><path d="M0 0L10 5L0 10z" style="fill:var(--marca)"/></marker>
    <marker id="pl-inc" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto"><path d="M0 0L10 5L0 10z" style="fill:var(--bad)"/></marker></defs>`;
  const zona = (k, cls, x, y, w, hh, extra, tx = x + 12, ty = y + 22, sub = '') =>
    `<g class="zona ${cls}" data-z="${k}" tabindex="0" role="button" aria-label="${esc(zonasPlano[k].n)}"><rect class="fondo" x="${x}" y="${y}" width="${w}" height="${hh}" rx="4"/>
     <text class="tit" x="${tx}" y="${ty}">${esc(zonasPlano[k].n.split(' · ')[0])}</text>${sub ? `<text class="sub" x="${tx}" y="${ty + 16}">${sub}</text>` : ''}${extra}</g>`;
  // Descarga
  h += zona('descarga', 'op', 20, 30, 170, 120, `<rect x="34" y="78" width="60" height="42" rx="2" class="mesa"/><rect x="94" y="92" width="22" height="28" rx="2" class="mesa"/><text class="txt" x="34" y="138">contenedor</text>`, 32, 52, 'llega el pedimento');
  // Recepción
  h += zona('recepcion', 'op', 20, 170, 170, 220, `${[0,1,2,3,4,5].map((i) => `<rect class="mesa" x="${36 + (i % 3) * 46}" y="${236 + Math.floor(i / 3) * 44}" width="38" height="34" rx="2"/>`).join('')}<text class="txt" x="34" y="340">caja por caja</text>${lector(166, 196)}`, 32, 194, 'REC-01');
  // Incidencias
  h += zona('incidencia', 'op', 20, 410, 170, 160, `<rect class="mesa" x="36" y="470" width="56" height="40" rx="2"/><rect class="mesa" x="100" y="470" width="56" height="40" rx="2"/><text class="txt" x="34" y="536">apartado, no se surte</text>`, 32, 434, 'INC-01');
  // Bodega principal
  let mods = '';
  ['A', 'B'].forEach((p, r) => {
    const y = r ? 172 : 66;
    for (let m = 1; m <= 4; m++) {
      const x = 240 + (m - 1) * 112;
      mods += `<rect class="modulo" x="${x}" y="${y}" width="100" height="64" rx="2"/><line class="nivel" x1="${x}" y1="${y + 21}" x2="${x + 100}" y2="${y + 21}"/><line class="nivel" x1="${x}" y1="${y + 43}" x2="${x + 100}" y2="${y + 43}"/><text class="modulo-n" x="${x + 6}" y="${y + 15}">${p}-0${m}</text>`;
    }
  });
  h += zona('principal', 'alm', 225, 18, 480, 232, mods, 240, 40, 'pasillo-módulo-nivel · 3 niveles');
  // Temporada
  h += zona('temporada', 'alm', 225, 280, 300, 140, `<rect class="modulo" x="241" y="330" width="120" height="64" rx="2"/><text class="modulo-n" x="249" y="346">AD-01</text><rect class="modulo off" x="377" y="330" width="120" height="64" rx="2"/><text class="modulo-n" x="385" y="346">AD-02</text><text class="txt" x="385" y="384">inactiva</text>`, 240, 304, 'picos ene · may · ago');
  // Oficina
  h += zona('oficina', 'ofi', 545, 280, 160, 140, `<rect class="mesa" x="565" y="340" width="80" height="44" rx="2"/><rect x="583" y="348" width="44" height="26" rx="2" style="fill:var(--surface-2);stroke:var(--ink-3)"/>`, 558, 304, 'responsable');
  // Preparación
  const mesas = ['T-014', 'T-022', 'T-031', 'ALM-EC'].map((d, i) => { const x = 760 + (i % 2) * 108, y = 82 + Math.floor(i / 2) * 92; return `<rect class="mesa" x="${x}" y="${y}" width="96" height="70" rx="3"/><text class="modulo-n" x="${x + 8}" y="${y + 18}">${d}</text>`; }).join('');
  h += zona('preparacion', 'op', 740, 18, 240, 372, `${mesas}<rect x="760" y="290" width="204" height="72" rx="3" style="fill:var(--surface);stroke:var(--info);stroke-width:1.5"/><text class="txt" x="772" y="312" style="fill:var(--info);font-weight:600">estación de revisión</text><text class="txt" x="772" y="330">lectura del bulto</text>${lector(940, 314)}`, 754, 40, 'PREP-01 · mesas por destino');
  // Salida
  h += zona('salida', 'op', 740, 410, 240, 160, `<rect class="mesa" x="760" y="466" width="70" height="44" rx="2"/><rect class="mesa" x="830" y="478" width="26" height="32" rx="2"/><text class="txt" x="760" y="536">transportista · placas · sello</text>`, 754, 434, 'folio de salida');
  // Recorrido
  h += `<path class="ruta" d="M105 112 V230" marker-end="url(#pl-pt)"/>
    <path class="ruta" d="M170 300 H208 V151 H600" marker-end="url(#pl-pt)"/>
    <path class="ruta" d="M640 151 H720 V230 H792" marker-end="url(#pl-pt)"/>
    <path class="ruta" d="M860 254 V284" marker-end="url(#pl-pt)"/>
    <path class="ruta" d="M920 362 V470" marker-end="url(#pl-pt)"/>
    <path class="ruta-inc" d="M172 372 V462" marker-end="url(#pl-inc)"/>`;
  const hitos = [[105, 98, '1', 'Descarga', 'start', 18], [105, 286, '2', 'Conteo', 'start', 18], [330, 151, '3', 'Ubicación', 'start', 18], [620, 151, '4', 'Toma', 'start', 18], [862, 163, '5', 'Separación', 'start', 18], [860, 270, '6', 'Revisión', 'start', 18], [920, 500, '7', 'Salida', 'end', -18]];
  hitos.forEach(([x, y, n, l, a, dx]) => {
    const tx = a === 'middle' ? x : x + dx, ty = a === 'middle' ? y + dx : y + 4;
    h += `<g class="hito" pointer-events="none"><circle cx="${x}" cy="${y}" r="12"/><text x="${x}" y="${y + 4.5}" text-anchor="middle">${n}</text><text class="lbl" x="${tx}" y="${ty}" text-anchor="${a}">${l}</text></g>`;
  });
  // Leyenda
  h += `<g transform="translate(225 452)"><text class="txt" x="0" y="0" style="font-weight:600;fill:var(--ink)">Cómo leer el plano</text>
    <path class="ruta" d="M0 22 H40" style="animation:none"/><text class="txt" x="50" y="26">camino de la mercancía</text>
    ${lector(6, 50)}<text class="txt" x="50" y="54">punto de lectura (RFID o a mano)</text>
    <path class="ruta-inc" d="M0 78 H40"/><text class="txt" x="50" y="82">a incidencias</text>
    <rect x="300" y="14" width="22" height="14" rx="2" style="fill:var(--zona-op);stroke:var(--line-strong)"/><text class="txt" x="330" y="26">área operativa</text>
    <rect x="300" y="42" width="22" height="14" rx="2" style="fill:var(--zona-alm);stroke:var(--line-strong)"/><text class="txt" x="330" y="54">almacenaje</text>
    <rect x="300" y="70" width="22" height="14" rx="2" style="fill:var(--surface-2);stroke:var(--line-strong);stroke-dasharray:3 2"/><text class="txt" x="330" y="82">control</text></g>`;
  $('#plano-svg').innerHTML = h;
  document.querySelectorAll('.zona').forEach((g) => {
    const ir = () => verZona(g.dataset.z);
    g.addEventListener('click', ir);
    g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); ir(); } });
  });
}
function verZona(k) {
  document.querySelectorAll('.zona').forEach((g) => g.classList.toggle('sel', g.dataset.z === k));
  const z = zonasPlano[k], tono = z.t === 'Almacenaje' ? 'info' : z.t === 'Control' ? 'neutro' : 'warn';
  $('#ficha-plano').innerHTML = `<span class="chip t-${tono}">${z.t}</span><h3>${esc(z.n)}</h3>
    <dl><div><dt>Qué pasa aquí</dt><dd>${z.que}</dd></div>
    <div><dt>Quién</dt><dd>${z.quien}</dd></div>
    <div><dt>Se lee</dt><dd>${z.lectura}</dd></div>
    <div><dt>Pantalla</dt><dd class="id">${z.pant}</dd></div></dl>`;
}
armarPlano();
verZona('recepcion');

mostrar(location.hash.slice(1));
</script>
@endverbatim
</body>
</html>
