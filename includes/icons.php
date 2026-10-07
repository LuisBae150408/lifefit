<?php
/**
 * icons.php
 * Iconos SVG de las categorias del menu.
 * Todos comparten el mismo lenguaje visual: glifos solidos, un solo
 * color (currentColor), viewBox 24x24 y el mismo peso de trazo, para
 * que se vean como un set y no como piezas sueltas.
 */
function render_icon(string $key): string
{
    $icons = icon_set();
    $paths = $icons[$key] ?? $icons['bowl'];
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $paths . '</svg>';
}

/** Lista de claves de íconos disponibles, para selects del panel. */
function render_icon_keys(): array
{
    return array_keys(icon_set());
}

function icon_set(): array
{
    return [
        // Crea tu Yogurt: pote con cuchara
        'yogurt' => '<path d="M7 9h10l-1.1 8.6A2 2 0 0 1 13.9 19.4h-3.8A2 2 0 0 1 8.1 17.6L7 9Z"/><path d="M6.2 7.2A.8.8 0 0 1 7 6.4h10a.8.8 0 0 1 .8.8v.6a.9.9 0 0 1-.9.9H7.1a.9.9 0 0 1-.9-.9v-.6Z"/><g transform="rotate(28 17 5)"><ellipse cx="17" cy="3.6" rx="1.5" ry="2"/><rect x="16.35" y="5.3" width="1.3" height="4.6" rx=".65"/></g>',

        // Bowl generico (Crea tu Bowl): tazon con dos toppings
        'bowl' => '<ellipse cx="12" cy="9.6" rx="8.6" ry="1.8"/><path d="M3.6 10.2h16.8l-1.4 5.6A3.6 3.6 0 0 1 15.5 18.6h-7A3.6 3.6 0 0 1 5 15.8L3.6 10.2Z"/><circle cx="9" cy="6.2" r="1"/><circle cx="13.4" cy="5.3" r="1.15"/>',

        // Arma tu Poke: tazon con palillos
        'poke' => '<ellipse cx="12" cy="9.6" rx="8.6" ry="1.8"/><path d="M3.6 10.2h16.8l-1.4 5.6A3.6 3.6 0 0 1 15.5 18.6h-7A3.6 3.6 0 0 1 5 15.8L3.6 10.2Z"/><g transform="rotate(-24 12 6.6)"><rect x="7.2" y="6.1" width="9.6" height="1" rx=".5"/></g><g transform="rotate(-24 12 8.6)"><rect x="7.2" y="8.1" width="9.6" height="1" rx=".5"/></g>',

        // Salad Bar: tazon con hoja
        'ensaladas' => '<ellipse cx="12" cy="10.4" rx="8" ry="1.7"/><path d="M4.3 11h15.4l-1.3 5.2A3.4 3.4 0 0 1 15.1 18.8h-6.2A3.4 3.4 0 0 1 5.6 16.2L4.3 11Z"/><path d="M12 3.4c2.1 1.4 2.1 5 0 6.8-2.1-1.8-2.1-5.4 0-6.8Z"/><rect x="11.55" y="9.6" width=".9" height="1.6" rx=".45"/>',

        // Smoothies: vaso con pajita
        'smoothies' => '<path d="M7.3 8h9.4l-1 10.4A2 2 0 0 1 13.7 20.2h-3.4a2 2 0 0 1-2-1.8L7.3 8Z"/><rect x="7" y="7" width="10" height="1.6" rx=".8"/><g transform="rotate(20 14.6 2)"><rect x="13.9" y="1.6" width="1.5" height="8.6" rx=".75"/></g>',

        // Malteadas: vaso con copete de crema, cereza y pajita
        'fatness' => '<path d="M7.6 10.8h8.8l-.9 7.8A2 2 0 0 1 13.5 20.4h-3a2 2 0 0 1-2-1.8l-.9-7.8Z"/><circle cx="9.2" cy="9.6" r="2"/><circle cx="12" cy="8.3" r="2.3"/><circle cx="14.8" cy="9.6" r="2"/><circle cx="14.3" cy="4.4" r="1.25"/><g transform="rotate(18 12.3 2.4)"><rect x="11.6" y="2" width="1.4" height="4.6" rx=".7"/></g>',

        // Jugos vitales: gota
        'gota' => '<path d="M12 2c3.2 5 7 9.6 7 13.3A7 7 0 0 1 5 15.3C5 11.6 8.8 7 12 2Z"/>',

        // Cafe: taza con asa y vapor
        'cafe' => '<path d="M5 9c0-.6.4-1 1-1h9v7a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V9Z"/><path d="M15 10h1.5a2.5 2.5 0 0 1 0 5H15v-2h1.5a.5.5 0 0 0 0-1H15v-2Z"/><path d="M8 3.3c0 .9-1.1 1.1-1.1 2s1.1 1.1 1.1 2h1.4c0-.9-1.1-1.1-1.1-2s1.1-1.1 1.1-2H8Z"/><path d="M12.2 3.3c0 .9-1.1 1.1-1.1 2s1.1 1.1 1.1 2h1.4c0-.9-1.1-1.1-1.1-2s1.1-1.1 1.1-2h-1.4Z"/>',

        // Postres saludables: muffin / cupcake
        'postre' => '<path d="M6 10h12l-1.3 8.4A2 2 0 0 1 14.7 20H9.3a2 2 0 0 1-2-1.6L6 10Z"/><path d="M5 10a7 7 0 0 1 14 0c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1Z"/><circle cx="12" cy="4.3" r="1.3"/>',

        // Waffles: cuadro con esquinas redondeadas y pocillos en rombo (antes era un cuadrado de borde recto con puntos, desentonaba con el resto)
        'waffle' => '<path fill-rule="evenodd" d="M7.4 4h9.2a3.4 3.4 0 0 1 3.4 3.4v9.2a3.4 3.4 0 0 1-3.4 3.4H7.4A3.4 3.4 0 0 1 4 16.6V7.4A3.4 3.4 0 0 1 7.4 4Z M8.2 6.9 9.5 8.2 8.2 9.5 6.9 8.2Z M12 6.9 13.3 8.2 12 9.5 10.7 8.2Z M15.8 6.9 17.1 8.2 15.8 9.5 14.5 8.2Z M8.2 10.7 9.5 12 8.2 13.3 6.9 12Z M12 10.7 13.3 12 12 13.3 10.7 12Z M15.8 10.7 17.1 12 15.8 13.3 14.5 12Z M8.2 14.5 9.5 15.8 8.2 17.1 6.9 15.8Z M12 14.5 13.3 15.8 12 17.1 10.7 15.8Z M15.8 14.5 17.1 15.8 15.8 17.1 14.5 15.8Z"/>',

        // Sushi (sin usar por ahora): dos piezas de maki vistas desde arriba, cada una con su aro de nori y relleno al centro
        'sushi' => '<path fill-rule="evenodd" d="M3.7 13.5A5.3 5.3 0 1 0 14.3 13.5 5.3 5.3 0 1 0 3.7 13.5Z M5.6 13.5A3.4 3.4 0 1 0 12.4 13.5 3.4 3.4 0 1 0 5.6 13.5Z M7.5 13.5A1.5 1.5 0 1 0 10.5 13.5 1.5 1.5 0 1 0 7.5 13.5Z M13.4 7.6A3.6 3.6 0 1 0 20.6 7.6 3.6 3.6 0 1 0 13.4 7.6Z M14.7 7.6A2.3 2.3 0 1 0 19.3 7.6 2.3 2.3 0 1 0 14.7 7.6Z M16 7.6A1 1 0 1 0 18 7.6 1 1 0 1 0 16 7.6Z"/>',
    ];
}
