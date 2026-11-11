<?php

// Laravel combina este archivo con su configuración por defecto; aquí solo va lo que cambia.
return [
    'timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),
    'locale' => env('APP_LOCALE', 'es'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),
    'faker_locale' => 'es_MX',
];
