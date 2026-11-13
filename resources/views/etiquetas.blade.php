@php
    use App\Support\Code128;
    $termica = $formato === 'termica';
    // Tamaño real de cada etiqueta (mm). Caja y bulto: 100 × 150 (4 × 6 in); rótulo de ubicación: 100 × 50.
    [$ancho, $alto] = $tipo === 'ubicacion' ? [100, 50] : [100, 150];
    $otro = request()->fullUrlWithQuery(['formato' => $termica ? 'carta' : 'termica']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    @vite('resources/css/proceso.css')
    <style>
        @page { size: {{ $termica ? $ancho.'mm '.$alto.'mm' : 'letter' }}; margin: {{ $termica ? '0' : '10mm' }}; }
        :root { --tinta: #000; --gris: #555; --fondo: #e9edf1; --rail: #15304a; --primario: #1c4f7e; --marca: #d0561c; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { background: var(--fondo); color: var(--tinta); font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .mono { font-family: 'JetBrains Mono Variable', ui-monospace, monospace; }
        .cond { font-family: 'Barlow Semi Condensed', 'Barlow', sans-serif; }

        /* Barra de herramientas (solo pantalla) */
        .barra { position: sticky; top: 0; z-index: 2; background: var(--rail); color: #e3ecf4; padding: 12px max(16px, env(safe-area-inset-left, 0px)); display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: center; }
        .barra h1 { font: 700 1.15rem 'Barlow Semi Condensed', sans-serif; margin: 0; flex: 1 1 16rem; }
        .barra h1 small { display: block; font: 400 0.8rem 'Barlow', sans-serif; color: #93abc2; }
        .barra a, .barra button { appearance: none; border: 1px solid #2a5078; background: transparent; color: #e3ecf4; font: 600 0.92rem 'Barlow', sans-serif; padding: 8px 14px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .barra a:hover, .barra button:hover { background: #1d4062; }
        .barra .imprimir { background: #fff; color: var(--rail); border-color: #fff; }
        .barra .imprimir:hover { background: #dfe8f1; }
        .nota { max-width: 60rem; margin: 14px auto 0; padding: 0 16px; color: #485b6e; font-size: 0.88rem; }
        .vacio { max-width: 40rem; margin: 40px auto; padding: 24px; background: #fff; border: 1px dashed #b5c2cf; border-radius: 4px; text-align: center; color: #485b6e; }

        .hojas { display: flex; flex-wrap: wrap; gap: 8mm; justify-content: center; padding: 8mm 16px 16mm; }
        .etiqueta { width: {{ $ancho }}mm; height: {{ $alto }}mm; background: #fff; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgb(20 33 48 / 0.18); border-radius: 2mm; }
        .etiqueta.grande { padding: 5mm 5.5mm 4.5mm; gap: 3mm; }
        .etiqueta.rotulo { padding: 3mm 4.5mm; gap: 1.5mm; }

        .kicker { display: flex; justify-content: space-between; align-items: baseline; gap: 3mm; font: 700 2.9mm 'Barlow Semi Condensed', sans-serif; letter-spacing: 0.06em; text-transform: uppercase; border-bottom: 0.5mm solid var(--tinta); padding-bottom: 1.6mm; }
        .kicker span:last-child { font-weight: 600; color: var(--gris); }
        .cabeza { display: flex; align-items: flex-end; justify-content: space-between; gap: 3mm; }
        .cabeza .num { font: 700 21mm/0.85 'JetBrains Mono Variable', monospace; letter-spacing: -0.04em; }
        .cabeza .nota-g { font: 600 3.6mm 'Barlow', sans-serif; color: var(--gris); text-align: right; padding-bottom: 1mm; }
        .destino .num { font-size: 17mm; }
        .destino .nombre { font: 600 4.4mm/1.15 'Barlow', sans-serif; margin-top: 1.5mm; }
        .bulto-n { flex: none; background: var(--tinta); color: #fff; text-align: center; padding: 2mm 3mm 1.6mm; border-radius: 1mm; }
        .bulto-n b { display: block; font: 700 9mm/1 'JetBrains Mono Variable', monospace; }
        .bulto-n span { font: 700 2.6mm 'Barlow Semi Condensed', sans-serif; letter-spacing: 0.08em; text-transform: uppercase; }

        .codigo svg.barras { display: block; width: 100%; height: 17mm; }
        .rotulo .codigo svg.barras { height: 11mm; }
        .codigo p { margin: 1mm 0 0; text-align: center; font: 600 3.4mm 'JetBrains Mono Variable', monospace; letter-spacing: 0.12em; }

        .contenido { flex: 1; min-height: 0; }
        .contenido h2 { margin: 0 0 1.2mm; font: 700 2.6mm 'Barlow Semi Condensed', sans-serif; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gris); }
        .linea { display: grid; grid-template-columns: 1fr auto; gap: 0 3mm; align-items: end; padding: 1.4mm 0; border-top: 0.25mm solid #bbb; }
        .linea:first-of-type { border-top: 0; padding-top: 0; }
        .linea .sku { font: 700 5.2mm/1.1 'JetBrains Mono Variable', monospace; }
        .linea .desc { grid-column: 1; font-size: 3.1mm; color: var(--gris); }
        .linea .pz { grid-row: 1 / span 2; grid-column: 2; text-align: right; font: 700 8mm/1 'Barlow Semi Condensed', sans-serif; }
        .linea .pz small { display: block; font: 600 2.6mm 'Barlow', sans-serif; color: var(--gris); }
        .aviso { grid-column: 1 / -1; margin-top: 1mm; background: var(--tinta); color: #fff; font: 700 3mm 'Barlow', sans-serif; padding: 0.8mm 2mm; border-radius: 0.8mm; justify-self: start; }
        .compacta .linea .sku { font-size: 4mm; }
        .compacta .linea .pz { font-size: 6mm; }
        .compacta .linea .desc { font-size: 2.7mm; }

        .campos { display: grid; grid-template-columns: 1fr 1fr; gap: 1.6mm 4mm; border-top: 0.5mm solid var(--tinta); padding-top: 2mm; }
        .campos div { min-width: 0; }
        .campos dt { font: 700 2.4mm 'Barlow Semi Condensed', sans-serif; letter-spacing: 0.06em; text-transform: uppercase; color: var(--gris); }
        .campos dd { margin: 0.3mm 0 0; font-size: 3.4mm; font-weight: 600; overflow-wrap: anywhere; }
        .campos dd.mono { font-size: 3.2mm; }
        .campos .ancho { grid-column: 1 / -1; }

        .partes { display: flex; align-items: stretch; gap: 1.5mm; flex: 1; min-height: 0; }
        .partes div { flex: 1; border: 0.5mm solid var(--tinta); border-radius: 1mm; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .partes b { font: 700 11mm/1 'JetBrains Mono Variable', monospace; }
        .partes span { font: 700 2.3mm 'Barlow Semi Condensed', sans-serif; letter-spacing: 0.08em; text-transform: uppercase; color: var(--gris); margin-top: 0.6mm; }
        .rotulo .solo { flex: 1; display: flex; align-items: center; justify-content: center; font: 700 12mm 'JetBrains Mono Variable', monospace; }

        @media print {
            body { background: #fff; }
            .barra, .nota { display: none; }
            .hojas { padding: 0; gap: {{ $termica ? '0' : '6mm' }}; justify-content: flex-start; }
            .etiqueta { box-shadow: none; border-radius: 0; break-inside: avoid; {{ $termica ? 'break-after: page;' : 'outline: 0.2mm dashed #999;' }} }
        }
    </style>
</head>
<body>
    <header class="barra">
        <h1>{{ $titulo }}<small>{{ count($etiquetas) }} {{ count($etiquetas) === 1 ? 'etiqueta' : 'etiquetas' }} · {{ $termica ? 'impresora térmica, '.$ancho.' × '.$alto.' mm' : 'hoja carta' }}</small></h1>
        <a href="{{ $otro }}">{{ $termica ? 'Ver en hoja carta' : 'Ver para impresora térmica' }}</a>
        <button type="button" onclick="history.length > 1 ? history.back() : window.close()">Volver</button>
        @if (count($etiquetas))
            <button type="button" class="imprimir" onclick="window.print()">Imprimir</button>
        @endif
    </header>
    <p class="nota">Vista para imprimir desde el navegador. En la operación, estas mismas etiquetas se mandarían directo a una impresora térmica; con impresora RFID, la etiqueta de la caja podría llevar su propio chip.</p>

    @if (! count($etiquetas))
        <div class="vacio">{{ $vacio }}</div>
    @endif

    <main class="hojas">
        @foreach ($etiquetas as $e)
            @if ($e['tipo'] === 'ubicacion')
                <article class="etiqueta rotulo">
                    <div class="kicker"><span>{{ $e['kicker'] }}</span><span>Ubicación</span></div>
                    @if ($e['partes'])
                        <div class="partes">
                            @foreach ($e['partes'] as $nombre => $valor)
                                <div><b>{{ $valor }}</b><span>{{ $nombre }}</span></div>
                            @endforeach
                        </div>
                    @else
                        <div class="solo">{{ $e['grande'] }}</div>
                    @endif
                    <div class="codigo">{!! Code128::svg($e['codigo']) !!}</div>
                </article>
            @else
                <article class="etiqueta grande">
                    <div class="kicker"><span>{{ $e['kicker'] }}</span><span>{{ config('app.name') }}</span></div>

                    @if ($e['tipo'] === 'bulto')
                        <div class="cabeza destino">
                            <div><div class="num">{{ $e['grande'] }}</div><div class="nombre">{{ $e['grande_nota'] }}</div></div>
                            <div class="bulto-n"><b>{{ $e['bulto']['n'] }}/{{ $e['bulto']['de'] }}</b><span>Bulto</span></div>
                        </div>
                    @else
                        <div class="cabeza">
                            <div class="num">{{ $e['grande'] }}</div>
                            <div class="nota-g">{{ $e['grande_nota'] }}</div>
                        </div>
                    @endif

                    <div class="codigo">{!! Code128::svg($e['codigo']) !!}<p>{{ $e['codigo'] }}</p></div>

                    <section class="contenido {{ count($e['contenido']) > 2 ? 'compacta' : '' }}">
                        @isset($e['contenido_titulo'])<h2>{{ $e['contenido_titulo'] }}</h2>@endisset
                        @foreach ($e['contenido'] as $l)
                            <div class="linea">
                                <div class="sku">{{ $l['sku'] }}</div>
                                <div class="pz">{{ $l['piezas'] }}<small>piezas</small></div>
                                <div class="desc">{{ $l['descripcion'] }}</div>
                                @foreach ($l['avisos'] as $a)
                                    <div class="aviso">{{ $a }}</div>
                                @endforeach
                            </div>
                        @endforeach
                    </section>

                    <dl class="campos">
                        @foreach ($e['campos'] as [$etq, $valor, $mono])
                            <div class="{{ strlen($valor) > 24 ? 'ancho' : '' }}"><dt>{{ $etq }}</dt><dd class="{{ $mono ? 'mono' : '' }}">{{ $valor }}</dd></div>
                        @endforeach
                    </dl>
                </article>
            @endif
        @endforeach
    </main>
</body>
</html>
