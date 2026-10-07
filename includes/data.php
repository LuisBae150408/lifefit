<?php
/**
 * data.php
 * -----------------------------------------------------------------
 * Fuente de datos de LIFE FIT -- conectada a MySQL:
 *   - get_catalogo() / get_info_negocio()  -> secciones ESTANDAR
 *     (categorias / productos / configuracion)
 *   - get_constructores()                  -> secciones PERSONALIZABLES
 *     ("Crea tu Yogurt", "Arma tu Poke", etc. -- constructores /
 *     grupos_ingredientes / ingredientes)
 *
 * Si la base de datos todavia no tiene datos cargados (o la conexion
 * falla porque includes/config.php no esta configurado todavia), se
 * usan las funciones de EJEMPLO de mas abajo para que el sitio nunca
 * se vea vacio o roto mientras se termina de montar.
 *
 * Cuando el panel de administracion este listo y el contenido real
 * viva en MySQL, se puede borrar todo el bloque "DATOS DE EJEMPLO".
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/db.php';

/** Código de 3 letras del día actual (lun..dom), para filtrar descuentos. */
function dia_de_hoy(): string
{
    $map = [1 => 'lun', 2 => 'mar', 3 => 'mie', 4 => 'jue', 5 => 'vie', 6 => 'sab', 7 => 'dom'];
    return $map[(int) date('N')];
}

/**
 * Descuentos activos hoy, agrupados por alcance para buscarlos rápido:
 * ['global' => fila|null, 'categoria' => [id => fila], 'producto' => [...], ...,
 *  'combo' => [constructor_id => [fila, fila, ...]]]
 * (a diferencia de los otros alcances, puede haber varios combos para
 * el mismo constructor, asi que se agrupan en una lista por id)
 */
function obtener_descuentos_activos(PDO $pdo): array
{
    $hoy = dia_de_hoy();
    $resultado = ['global' => null, 'categoria' => [], 'producto' => [], 'constructor' => [], 'ingrediente' => [], 'combo' => []];

    foreach ($pdo->query('SELECT * FROM descuentos WHERE activo = 1')->fetchAll() as $d) {
        if (!empty($d['dias']) && !in_array($hoy, explode(',', $d['dias']), true)) {
            continue; // tiene días restringidos y hoy no es uno de ellos
        }
        if ($d['alcance'] === 'global') {
            $resultado['global'] = $d;
        } elseif ($d['alcance'] === 'combo') {
            $resultado['combo'][(string) $d['referencia_id']][] = $d;
        } else {
            $resultado[$d['alcance']][(string) $d['referencia_id']] = $d;
        }
    }

    return $resultado;
}

function aplicar_valor_descuento(float $precio, array $descuento): float
{
    $final = $descuento['tipo'] === 'porcentaje'
        ? $precio - ($precio * (float) $descuento['valor'] / 100)
        : $precio - (float) $descuento['valor'];

    return max(0.0, round($final, 2));
}

/**
 * Busca la fila de descuento activo mas especifica para algo con id propio
 * + su padre (categoria o constructor). Devuelve la fila (o null).
 */
function resolver_descuento(array $descuentos, string $alcancePropio, ?string $idPropio, string $alcancePadre, ?string $idPadre): ?array
{
    if ($idPropio !== null && isset($descuentos[$alcancePropio][$idPropio])) {
        return $descuentos[$alcancePropio][$idPropio];
    }
    if ($idPadre !== null && isset($descuentos[$alcancePadre][$idPadre])) {
        return $descuentos[$alcancePadre][$idPadre];
    }
    return $descuentos['global'] ?: null;
}

/**
 * Resuelve el precio final de algo con id propio + su padre (categoria o
 * constructor), probando primero el descuento más específico. Devuelve
 * [precio_final, nombre_descuento|null].
 */
function resolver_precio(float $precioBase, array $descuentos, string $alcancePropio, ?string $idPropio, string $alcancePadre, ?string $idPadre): array
{
    $d = resolver_descuento($descuentos, $alcancePropio, $idPropio, $alcancePadre, $idPadre);

    if (!$d) {
        return [$precioBase, null];
    }

    return [aplicar_valor_descuento($precioBase, $d), $d['nombre']];
}

function get_catalogo(): array
{
    try {
        $pdo = get_pdo();

        $categorias = $pdo->query(
            'SELECT id, nombre, icono FROM categorias WHERE activo = 1 ORDER BY orden, nombre'
        )->fetchAll();

        if (!$categorias) {
            return get_catalogo_ejemplo();
        }

        $descuentos = obtener_descuentos_activos($pdo);

        $stmt = $pdo->prepare(
            'SELECT id, nombre, descripcion, precio, imagen
             FROM productos
             WHERE categoria_id = ? AND activo = 1
             ORDER BY orden, nombre'
        );
        $stmtTamanos = $pdo->prepare('SELECT nombre, precio FROM tamanos_producto WHERE producto_id = ? ORDER BY orden, precio');

        $catalogo = [];
        $total_items = 0;

        foreach ($categorias as $cat) {
            $stmt->execute([$cat['id']]);

            $items = [];
            foreach ($stmt->fetchAll() as $row) {
                $precioBase = (float) $row['precio'];
                $descuento = resolver_descuento($descuentos, 'producto', (string) $row['id'], 'categoria', $cat['id']);
                $precioFinal = $descuento ? aplicar_valor_descuento($precioBase, $descuento) : $precioBase;
                $descNombre = $descuento['nombre'] ?? null;

                $stmtTamanos->execute([$row['id']]);
                $tamanos = [];
                foreach ($stmtTamanos->fetchAll() as $t) {
                    $tPrecio = (float) $t['precio'];
                    $tFinal = $descuento ? aplicar_valor_descuento($tPrecio, $descuento) : $tPrecio;
                    $tamanos[] = [
                        'nombre' => $t['nombre'],
                        'precio' => number_format($tPrecio, 2, '.', ''),
                        'precio_final' => number_format($tFinal, 2, '.', ''),
                    ];
                }

                $items[] = [
                    'nombre'  => $row['nombre'],
                    'desc'    => $row['descripcion'],
                    'precio'  => number_format($precioBase, 2, '.', ''),
                    'precio_final' => number_format($precioFinal, 2, '.', ''),
                    'descuento' => $descNombre,
                    'imagen'  => $row['imagen'],
                    'tamanos' => $tamanos,
                ];
            }
            $total_items += count($items);

            $catalogo[] = [
                'id'    => $cat['id'],
                'nombre'=> $cat['nombre'],
                'icono' => $cat['icono'],
                'items' => $items,
            ];
        }

        if ($total_items === 0) {
            return get_catalogo_ejemplo();
        }

        return $catalogo;
    } catch (Throwable $e) {
        return get_catalogo_ejemplo();
    }
}

/** Link de WhatsApp a partir del número guardado (solo dígitos, con código de país). */
function url_whatsapp(string $numero, string $texto = ''): string
{
    $numero = preg_replace('/\D+/', '', $numero);
    if ($numero === '') return '#';
    return 'https://wa.me/' . $numero . ($texto !== '' ? '?text=' . rawurlencode($texto) : '');
}

/** Link de Instagram a partir del usuario guardado (sin @). Null si está vacío. */
function url_instagram(string $usuario): ?string
{
    $usuario = ltrim(trim($usuario), '@');
    return $usuario === '' ? null : 'https://instagram.com/' . rawurlencode($usuario);
}

/** Link de TikTok a partir del usuario guardado (sin @). Null si está vacío. */
function url_tiktok(string $usuario): ?string
{
    $usuario = ltrim(trim($usuario), '@');
    return $usuario === '' ? null : 'https://www.tiktok.com/@' . rawurlencode($usuario);
}

/**
 * Normaliza el enlace del botón de la publicación: si es una ruta
 * relativa del propio sitio (ej. "menu/", "#contacto") o ya trae
 * protocolo (http, https, mailto, tel) la deja igual; si parece un
 * dominio externo sin protocolo (ej. "wa.me/584..." o
 * "instagram.com/lifefit") le agrega "https://" para que el enlace
 * funcione en vez de intentar abrir una ruta interna inexistente.
 */
function normalizar_url_enlace(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '#';
    }
    if (preg_match('~^(https?://|mailto:|tel:|#|/)~i', $url)) {
        return $url;
    }

    $primerSegmento = strtok($url, '/');
    if ($primerSegmento !== false && str_contains($primerSegmento, '.')) {
        return 'https://' . $url;
    }

    return $url; // ruta relativa dentro del sitio (ej. "menu/", "menu/#build-poke")
}

/**
 * Datos generales del negocio (bio, redes, horario), leidos de la
 * tabla configuracion (clave -> valor). Si no hay datos o falla la
 * conexion, se usa la info de ejemplo.
 */
function get_info_negocio(): array
{
    try {
        $pdo = get_pdo();
        $rows = $pdo->query('SELECT clave, valor FROM configuracion')->fetchAll();

        if (!$rows) {
            return get_info_negocio_ejemplo();
        }

        $info = [];
        foreach ($rows as $row) {
            $info[$row['clave']] = $row['valor'];
        }

        return $info;
    } catch (Throwable $e) {
        return get_info_negocio_ejemplo();
    }
}

/**
 * Publicación personalizada de la landing (banner con foto que sube
 * el admin, ej. para avisar un descuento). Devuelve un array (vacío si
 * no hay ninguna activa, o falla la conexión) -- puede haber hasta 2
 * publicaciones cargadas, y la landing muestra las que estén activas.
 */
function get_publicaciones(): array
{
    try {
        $pdo = get_pdo();
        $filas = $pdo->query("SELECT * FROM publicaciones WHERE activo = 1 AND imagen != '' ORDER BY id LIMIT 2")->fetchAll();

        return $filas ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Constructores personalizables ("Crea tu Yogurt", "Arma tu Poke", etc.)
 * con sus grupos de ingredientes y las opciones de cada grupo. Cada
 * ingrediente ya viene con su regla de seleccion (min/max) resuelta
 * a nivel de grupo, lista para que el front arme el picker dinamico.
 */
function get_constructores(): array
{
    try {
        $pdo = get_pdo();

        $filas = $pdo->query(
            'SELECT id, nombre, descripcion, nota, precio_base, precio_mini, imagen, icono
             FROM constructores WHERE activo = 1 ORDER BY orden, nombre'
        )->fetchAll();

        if (!$filas) {
            return get_constructores_ejemplo();
        }

        $descuentos = obtener_descuentos_activos($pdo);

        $stmtGrupos = $pdo->prepare(
            'SELECT id, nombre, seleccion_min, seleccion_max, seleccion_min_mini, seleccion_max_mini
             FROM grupos_ingredientes WHERE constructor_id = ? ORDER BY orden, nombre'
        );
        $stmtIng = $pdo->prepare(
            'SELECT id, nombre, descripcion, precio_extra
             FROM ingredientes WHERE grupo_id = ? AND disponible = 1 ORDER BY orden, nombre'
        );

        $constructores = [];
        $total_grupos = 0;

        foreach ($filas as $c) {
            $stmtGrupos->execute([$c['id']]);
            $grupos = [];
            $mapaIdNombre = []; // id de ingrediente -> nombre, para resolver los combos de este constructor

            foreach ($stmtGrupos->fetchAll() as $g) {
                $stmtIng->execute([$g['id']]);
                $ingredientes = [];
                foreach ($stmtIng->fetchAll() as $i) {
                    $ingredientes[] = [
                        'id'     => (int) $i['id'],
                        'nombre' => $i['nombre'],
                        'desc'   => $i['descripcion'],
                        'extra'  => (float) $i['precio_extra'],
                    ];
                    $mapaIdNombre[$i['id']] = $i['nombre'];
                }
                $grupos[] = [
                    'nombre' => $g['nombre'],
                    'min'    => (int) $g['seleccion_min'],
                    'max'    => $g['seleccion_max'] === null ? null : (int) $g['seleccion_max'],
                    // Límites alternativos para cuando el cliente elige
                    // tamaño "Mini" (solo aplica en constructores con esa
                    // opción, ej. el Poke); si es NULL, ese grupo no cambia.
                    'min_mini' => $g['seleccion_min_mini'] === null ? null : (int) $g['seleccion_min_mini'],
                    'max_mini' => $g['seleccion_max_mini'] === null ? null : (int) $g['seleccion_max_mini'],
                    'ingredientes' => $ingredientes,
                ];
            }
            $total_grupos += count($grupos);

            $precioBase = (float) $c['precio_base'];
            [$precioFinal, $descNombre] = resolver_precio($precioBase, $descuentos, 'constructor', (string) $c['id'], 'constructor', null);

            // Precio de la version Mini (si el constructor tiene una, ej.
            // el Poke); pasa por el mismo descuento que el precio normal.
            $precioMini = $c['precio_mini'] === null ? null : (float) $c['precio_mini'];
            $precioMiniFinal = null;
            if ($precioMini !== null) {
                [$precioMiniFinal, ] = resolver_precio($precioMini, $descuentos, 'constructor', (string) $c['id'], 'constructor', null);
            }

            // Combos de ingredientes de este constructor (pueden ser 1 o mas):
            // se resuelven los ids guardados a nombres (para que el front los
            // pueda comparar contra lo que el cliente va tildando) y se
            // descartan combos rotos (sin ningun ingrediente valido, ej. si
            // se borro el ingrediente despues).
            $combos = [];
            $fuentes = $descuentos['combo'][(string) $c['id']] ?? [];
            foreach ($descuentos['ingrediente'] as $idIng => $d) {
                if (isset($mapaIdNombre[$idIng])) $fuentes[] = $d;
            }
            foreach ($fuentes as $d) {
                if (($d['alcance'] ?? 'combo') === 'ingrediente') {
                    $nombres = [$mapaIdNombre[(int) $d['referencia_id']]];
                } else {
                    $ids = array_filter(array_map('trim', explode(',', (string) ($d['combo_ingredientes'] ?? ''))));
                    $nombres = array_values(array_filter(array_map(
                        static fn($id) => $mapaIdNombre[(int) $id] ?? null,
                        $ids
                    )));
                }
                if (count($nombres) < 1) continue;
                $combos[] = [
                    'nombre'  => $d['nombre'],
                    'tipo'    => $d['tipo'],
                    'valor'   => (float) $d['valor'],
                    'ingredientes' => $nombres,
                ];
            }

            // Plantillas de este constructor (combinaciones ya armadas que el
            // admin cargó para que el cliente las aplique con un toque). Se
            // resuelven los ids guardados a nombres, igual que los combos.
            $plantillas = [];
            $stmtPlant = $pdo->prepare('SELECT nombre, ingredientes FROM plantillas_constructor WHERE constructor_id = ? AND activo = 1 ORDER BY orden, nombre');
            $stmtPlant->execute([$c['id']]);
            foreach ($stmtPlant->fetchAll() as $p) {
                $ids = array_filter(array_map('trim', explode(',', (string) $p['ingredientes'])));
                $nombres = array_values(array_filter(array_map(
                    static fn($id) => $mapaIdNombre[(int) $id] ?? null,
                    $ids
                )));
                if (count($nombres) < 1) continue;
                $plantillas[] = ['nombre' => $p['nombre'], 'ingredientes' => $nombres];
            }

            $constructores[] = [
                'id'     => $c['id'],
                'nombre' => $c['nombre'],
                'desc'   => $c['descripcion'],
                'nota'   => $c['nota'],
                'precio' => number_format($precioBase, 2, '.', ''),
                'precio_final' => number_format($precioFinal, 2, '.', ''),
                'precio_mini' => $precioMini === null ? null : number_format($precioMini, 2, '.', ''),
                'precio_mini_final' => $precioMiniFinal === null ? null : number_format($precioMiniFinal, 2, '.', ''),
                'descuento' => $descNombre,
                'imagen' => $c['imagen'],
                'icono'  => $c['icono'],
                'grupos' => $grupos,
                'combos' => $combos,
                'plantillas' => $plantillas,
            ];
        }

        if ($total_grupos === 0) {
            return get_constructores_ejemplo();
        }

        return $constructores;
    } catch (Throwable $e) {
        return get_constructores_ejemplo();
    }
}

/* =================================================================
   DATOS DE EJEMPLO
   -----------------------------------------------------------------
   Mismo contenido que sembramos en database/schema.sql. Se puede
   borrar este bloque completo una vez que el contenido real viva en
   MySQL y se administre desde el panel.
================================================================= */

function get_catalogo_ejemplo(): array
{
    $catalogo = [
        [
            'id' => 'smoothies', 'nombre' => 'Smoothies', 'icono' => 'smoothies',
            'items' => [
                ['nombre' => 'Clásico', 'desc' => 'Jugo de naranja, fresa, banana.', 'precio' => '4.00', 'imagen' => 'https://picsum.photos/seed/lifefit-smoothie-clasico/600/450'],
                ['nombre' => 'Plant Power', 'desc' => 'Leche de almendras, piña, banana, espinaca, mantequilla de maní.', 'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-plant-power/600/450'],
                ['nombre' => 'Blue Magic', 'desc' => 'Jugo de naranja, piña, espirulina azul.', 'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-blue-magic/600/450'],
                ['nombre' => 'Creamy Colada', 'desc' => 'Agua de coco, leche de coco, banana, piña, coco rallado.', 'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-creamy-colada/600/450'],
                ['nombre' => 'Bluenut', 'desc' => 'Jugo de naranja, blueberries, banana, mantequilla de maní.', 'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-bluenut/600/450'],
                ['nombre' => 'Choco Love', 'desc' => 'Leche de almendras, cacao, banana, aguacate, miel.', 'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-choco-love/600/450'],
            ],
        ],
        [
            'id' => 'jugos-vitales', 'nombre' => 'Jugos Vitales', 'icono' => 'gota',
            'items' => [
                ['nombre' => 'Flujo Rojo', 'desc' => 'Remolacha, manzana roja, jengibre fresco, jugo de naranja.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-flujo-rojo/600/450'],
                ['nombre' => 'Energía Mental', 'desc' => 'Té matcha premium, leche de almendras, vainilla natural.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-energia-mental/600/450'],
                ['nombre' => 'Filtro Natural', 'desc' => 'Pepino, piña dulce, perejil fresco.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-filtro-natural/600/450'],
                ['nombre' => 'Azul Alcalino', 'desc' => 'Manzana verde, pepino, espirulina azul.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-azul-alcalino/600/450'],
                ['nombre' => 'Aire Puro', 'desc' => 'Zanahoria, manzana, cúrcuma, pimienta negra.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-aire-puro/600/450'],
                ['nombre' => 'Probiótico Pitaya', 'desc' => 'Pitaya rosada, yogurt natural, fresas.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-probiotico-pitaya/600/450'],
            ],
        ],
        [
            'id' => 'cafe', 'nombre' => 'Café', 'icono' => 'cafe',
            'items' => [
                ['nombre' => 'Café', 'desc' => 'Expresso, Espresso Ristretto, Espresso Macchiato, Americano, Latte, Hazeltella, Cappuccino, Moccaccino, Caramel Latte, Latte Vainilla, Tiramisú Latte.', 'precio' => '2.50', 'imagen' => 'https://picsum.photos/seed/lifefit-cafe/600/450'],
                ['nombre' => 'Frappuccino', 'desc' => 'Clásico, Vainilla, Mocha, Caramelo.', 'precio' => '3.00', 'imagen' => 'https://picsum.photos/seed/lifefit-frappuccino/600/450'],
                ['nombre' => 'Iced Coffee', 'desc' => 'Caramelo, Vainilla, Avellana, Pistachio, Mocha.', 'precio' => '3.00', 'imagen' => 'https://picsum.photos/seed/lifefit-iced-coffee/600/450'],
                ['nombre' => 'Matcha Latte (Sin azúcar)', 'desc' => 'Caliente o Frío.', 'precio' => '3.00', 'imagen' => 'https://picsum.photos/seed/lifefit-matcha-latte/600/450'],
            ],
        ],
        [
            'id' => 'postres-saludables', 'nombre' => 'Postres Saludables', 'icono' => 'postre',
            'items' => [
                ['nombre' => 'Cheesecake de Brownie', 'desc' => 'Postre saludable, gluten free.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-cheesecake-brownie/600/450'],
                ['nombre' => 'Cheesecake de Red Velvet', 'desc' => 'Postre saludable, gluten free.', 'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-cheesecake-redvelvet/600/450'],
                ['nombre' => 'Crumble de Manzana', 'desc' => 'Postre saludable, gluten free.', 'precio' => '3.00', 'imagen' => 'https://picsum.photos/seed/lifefit-crumble-manzana/600/450'],
                ['nombre' => 'Muffin BananaChips', 'desc' => 'Postre saludable, gluten free.', 'precio' => '2.50', 'imagen' => 'https://picsum.photos/seed/lifefit-muffin-bananachips/600/450'],
                ['nombre' => 'Muffin ChocoChips', 'desc' => 'Postre saludable, gluten free.', 'precio' => '2.50', 'imagen' => 'https://picsum.photos/seed/lifefit-muffin-chocochips/600/450'],
                ['nombre' => 'Muffin Zanahoria', 'desc' => 'Postre saludable, gluten free.', 'precio' => '2.50', 'imagen' => 'https://picsum.photos/seed/lifefit-muffin-zanahoria/600/450'],
            ],
        ],
    ];

    // El ejemplo no tiene descuentos: precio_final = precio siempre.
    foreach ($catalogo as &$cat) {
        foreach ($cat['items'] as &$item) {
            $item['precio_final'] = $item['precio'];
            $item['descuento'] = null;
        }
        unset($item);
    }
    unset($cat);

    return $catalogo;
}

function get_info_negocio_ejemplo(): array
{
    return [
        'nombre'      => 'LIFE FIT',
        'eslogan'     => 'Vive bien. Vive saludable. Vive con LifeFit.',
        'ubicacion'   => 'Parque Los Ilustres',
        'horario_semana' => 'Lun – Vie · 12:00 pm – 8:00 pm',
        'horario_finde'  => 'Sáb – Dom · 2:00 pm – 10:00 pm',
        'whatsapp_numero' => '584126448878',
        'instagram'   => 'lifefit',
        'tiktok'      => '',
    ];
}

function get_constructores_ejemplo(): array
{
    $g = static fn(string $nombre, int $min, ?int $max, array $items, ?int $minMini = null, ?int $maxMini = null): array => [
        'nombre' => $nombre, 'min' => $min, 'max' => $max, 'min_mini' => $minMini, 'max_mini' => $maxMini,
        'ingredientes' => array_map(static fn($n) => is_array($n)
            ? ['nombre' => $n[0], 'desc' => $n[1], 'extra' => 0.0]
            : ['nombre' => $n, 'desc' => '', 'extra' => 0.0], $items),
    ];

    $constructores = [
        [
            'id' => 'yogurt', 'nombre' => 'Crea tu Yogurt', 'desc' => 'Base: Banana, fresa y piña.', 'nota' => '',
            'precio' => '3.50', 'imagen' => 'https://picsum.photos/seed/lifefit-crea-yogurt/700/560', 'icono' => 'yogurt',
            'grupos' => [
                $g('Frutos secos', 0, 3, ['Almendras', 'Nueces', 'Merey']),
                $g('Frutas', 0, 3, ['Kiwi', 'Durazno', 'Blueberries']),
                $g('Extras', 0, 3, ['Chía', 'Miel', 'Granola']),
                $g('Frutas deshidratadas', 0, 3, ['Cranberries', 'Cherries', 'Ciruelas', 'Dátiles']),
                $g('Syrups (sin azúcar)', 0, 2, ['Caramelo', 'Pistachio', 'Mantequilla de maní']),
            ],
        ],
        [
            'id' => 'malteadas', 'nombre' => 'Malteadas', 'desc' => 'Elige tu sabor favorito.', 'nota' => '',
            'precio' => '4.50', 'imagen' => 'https://picsum.photos/seed/lifefit-malteadas/700/560', 'icono' => 'fatness',
            'grupos' => [
                $g('Sabor', 1, 1, ['Nutella', 'Ovomaltina', 'Kit Kat', 'Oreo', 'Brownie', "Reese's", 'Snickers', 'Samba', 'M&M', 'Flips', 'Chips Ahoy', 'Cocosette']),
            ],
        ],
        [
            'id' => 'poke', 'nombre' => 'Arma tu Poke', 'desc' => 'Elige base, proteína y toppings para tu poke bowl.',
            'nota' => 'También disponible en versión Mini: 1 proteína / 3 toppings.',
            'precio' => '8.00', 'precio_mini' => '6.00', 'imagen' => 'https://picsum.photos/seed/lifefit-arma-poke/700/560', 'icono' => 'poke',
            'grupos' => [
                $g('Elige tu base', 1, 1, ['Arroz', 'Quinoa']),
                $g('Elige tu proteína (2 max)', 1, 2, ['Pollo', 'Camarón fresco', 'Camarón tempura', 'Salmón'], 1, 1),
                $g('Elige tus toppings (4 max)', 0, 4, ['Edamame', 'Pasta dinamita', 'Wakame', 'Maní Japonés', 'Maíz dulce', 'Aguacate', 'Masago', 'Repollo morado'], null, 3),
            ],
        ],
        [
            'id' => 'salad-bar', 'nombre' => 'Salad Bar', 'desc' => 'Base: Lechuga. Arma tu ensalada a tu gusto.', 'nota' => '',
            'precio' => '6.50', 'imagen' => 'https://picsum.photos/seed/lifefit-salad-bar/700/560', 'icono' => 'ensaladas',
            'grupos' => [
                $g('Proteína', 0, 1, ['Pollo', 'Atún', 'Salmón', 'Camarones']),
                $g('Vegetales', 0, null, ['Zanahoria', 'Tomate', 'Pepino', 'Aguacate', 'Brócoli', 'Pimentón', 'Coliflor', 'Champiñón', 'Aceitunas']),
                $g('Frutas', 0, null, ['Maíz dulce', 'Fresa', 'Piña', 'Kiwi', 'Blueberries', 'Cranberries', 'Cherries']),
                $g('Frutos secos y extras', 0, null, ['Almendras', 'Merey', 'Nueces', 'Chía', 'Mozzarella', 'Parmesano', 'Croutons', 'Tocineta']),
                $g('Aderezo', 1, 1, [
                    ['Zesty Italian', 'Aceite de oliva, pimientos rojos, sal y parmesano.'],
                    ['Classic Cesar', 'Alioli acompañada de quesos madurados y mostaza.'],
                    ['Mostaza Miel', 'Sabor a mostaza deliciosamente dulce.'],
                    ['Aceite de Oliva', 'Extra virgen.'],
                ]),
            ],
        ],
        [
            'id' => 'bowl-acai', 'nombre' => 'Crea tu Bowl', 'desc' => 'Base de açaí, dragon fruit o espirulina azul (incluye banana y fresa congelada).',
            'nota' => '', 'precio' => '7.00', 'imagen' => 'https://picsum.photos/seed/lifefit-crea-bowl/700/560', 'icono' => 'bowl',
            'grupos' => [
                $g('Base', 1, 1, ['Açaí', 'Dragon Fruit', 'Espirulina azul']),
                $g('Frutas', 0, 4, ['Banana', 'Fresa', 'Piña', 'Durazno', 'Kiwi', 'Blueberries']),
                $g('Frutas deshidratadas', 0, 3, ['Cranberries', 'Cherries', 'Ciruela', 'Dátiles']),
                $g('Frutos secos', 0, 3, ['Almendras', 'Nueces', 'Merey']),
                $g('Extras (sin azúcar)', 0, null, ['Chía', 'Coco rallado', 'Granola', 'Gotas de chocolate oscuro', 'Gotas de chocolate blanco', 'Gotas de mantequilla de maní', 'Syrup de mantequilla de maní', 'Syrup de caramelo', 'Syrup de pistacho']),
            ],
        ],
        [
            'id' => 'waffles', 'nombre' => 'Waffles', 'desc' => 'Base: 2 Mini Waffles.',
            'nota' => 'También disponible en versión Gluten Free (harina de avena, huevo y leche + toppings).',
            'precio' => '5.50', 'imagen' => 'https://picsum.photos/seed/lifefit-waffles/700/560', 'icono' => 'waffle',
            'grupos' => [
                $g('Syrup', 0, 2, ['Nutella', 'Miel', 'Leche condensada', 'Ovomaltina']),
                $g('Frutas', 0, 2, ['Fresa', 'Banana']),
                $g('Toppings nacionales', 0, 3, ['Oreo Americana', 'Chips Ahoy', 'Samba', 'Cocosette']),
                $g('Toppings importados', 0, 3, ['M&M', 'Snickers', 'Kit Kat', "Reese's"]),
            ],
        ],
    ];

    // El ejemplo no tiene descuentos: precio_final = precio siempre.
    foreach ($constructores as &$c) {
        $c['precio_final'] = $c['precio'];
        $c['precio_mini'] = $c['precio_mini'] ?? null;
        $c['precio_mini_final'] = $c['precio_mini'];
        $c['descuento'] = null;
        $c['combos'] = [];
    }
    unset($c);

    return $constructores;
}
