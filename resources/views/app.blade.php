<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            try { document.documentElement.dataset.tema = localStorage.getItem('tema') || 'contenedor'; }
            catch (e) { document.documentElement.dataset.tema = 'contenedor'; }
        </script>
        @vite('resources/js/app.ts')
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
