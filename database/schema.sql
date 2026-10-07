-- =================================================================
-- LIFE FIT -- esquema de base de datos (MySQL)
-- -----------------------------------------------------------------
-- Pensado para que, mas adelante, un panel de administracion
-- (aparte del sitio publico) pueda gestionar categorias, productos,
-- constructores ("arma tu X") e ingredientes sin tocar codigo PHP.
--
-- El sitio (includes/data.php) intenta leer de esta base de datos
-- y, si esta vacia o la conexion falla, usa un catalogo de ejemplo
-- para que nunca se vea roto. Cuando el panel de administracion
-- este listo, se llenan estas tablas de verdad.
--
-- Como usarlo (por ejemplo en phpMyAdmin de tu hosting):
--   1. Crea la base de datos (o usa la que ya tengas).
--   2. Importa este archivo completo.
--
-- Si ya habias importado una version anterior de este esquema, este
-- script limpia las categorias/productos viejos (de ejemplo) y
-- siembra el menu real (ver los DELETE de mas abajo).
-- =================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------
-- Tabla: categorias  (secciones ESTANDAR, no personalizables)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
  id        VARCHAR(40)  NOT NULL PRIMARY KEY,
  nombre    VARCHAR(80)  NOT NULL,
  icono     VARCHAR(40)  NOT NULL DEFAULT 'bowl', -- clave usada en icons.php
  orden     INT          NOT NULL DEFAULT 0,
  activo    TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: productos  (items de las secciones estandar)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  categoria_id VARCHAR(40)  NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  VARCHAR(255) NOT NULL DEFAULT '',
  precio       DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  imagen       VARCHAR(255) NOT NULL DEFAULT '',
  orden        INT          NOT NULL DEFAULT 0,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_categoria_nombre (categoria_id, nombre),
  CONSTRAINT fk_producto_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: constructores  ("Crea tu Yogurt", "Arma tu Poke", etc.)
-- Cada fila es UNA plantilla personalizable, no un producto fijo.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS constructores (
  id            VARCHAR(40)  NOT NULL PRIMARY KEY,  -- ej: 'poke', 'yogurt'
  nombre        VARCHAR(120) NOT NULL,               -- 'Arma tu Poke'
  descripcion   VARCHAR(255) NOT NULL DEFAULT '',     -- 'Base: arroz o quinoa...'
  nota          VARCHAR(255) NOT NULL DEFAULT '',     -- aclaraciones (ej. version mini)
  precio_base   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  imagen        VARCHAR(255) NOT NULL DEFAULT '',
  icono         VARCHAR(40)  NOT NULL DEFAULT 'bowl',
  orden         INT          NOT NULL DEFAULT 0,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: grupos_ingredientes  (ej. "Elige tu proteina (2 max)")
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupos_ingredientes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  constructor_id  VARCHAR(40)  NOT NULL,
  nombre          VARCHAR(120) NOT NULL,              -- 'Proteinas', 'Toppings'
  seleccion_min   INT          NOT NULL DEFAULT 0,
  seleccion_max   INT          NULL,                  -- NULL = sin limite
  orden           INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_constructor_grupo (constructor_id, nombre),
  CONSTRAINT fk_grupo_constructor
    FOREIGN KEY (constructor_id) REFERENCES constructores(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existia de una version anterior (sin esta clave unica),
-- esto se la agrega para que reimportar el .sql nunca duplique grupos.
ALTER TABLE grupos_ingredientes ADD UNIQUE KEY IF NOT EXISTS uniq_constructor_grupo (constructor_id, nombre);

-- -----------------------------------------------------------------
-- Tabla: ingredientes  (cada opcion dentro de un grupo)
-- Editable a futuro: nombre, precio_extra, disponible.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ingredientes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  grupo_id     INT UNSIGNED NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  VARCHAR(255) NOT NULL DEFAULT '',       -- ej. sabor de un aderezo
  precio_extra DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  disponible   TINYINT(1)   NOT NULL DEFAULT 1,
  orden        INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_grupo_ingrediente (grupo_id, nombre),
  CONSTRAINT fk_ingrediente_grupo
    FOREIGN KEY (grupo_id) REFERENCES grupos_ingredientes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Igual que arriba: para que reimportar nunca duplique ingredientes.
ALTER TABLE ingredientes ADD UNIQUE KEY IF NOT EXISTS uniq_grupo_ingrediente (grupo_id, nombre);

-- -----------------------------------------------------------------
-- Tabla: plantillas_constructor  (combinaciones ya armadas -- ej.
-- "Poke Clásico" -- que el cliente aplica con un toque en vez de
-- marcar cada ingrediente a mano). Las carga el admin desde el panel
-- de cada constructor; el cliente solo las ve y las toca.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plantillas_constructor (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  constructor_id VARCHAR(40)  NOT NULL,
  nombre         VARCHAR(120) NOT NULL,
  ingredientes   TEXT         NOT NULL,   -- ids de ingredientes.id separados por coma
  orden          INT          NOT NULL DEFAULT 0,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_constructor_plantilla (constructor_id, nombre),
  CONSTRAINT fk_plantilla_constructor
    FOREIGN KEY (constructor_id) REFERENCES constructores(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ejemplo para que se entienda de una: un "Poke Clásico" con base de
-- arroz, salmón y unos toppings ya elegidos. Se resuelve por nombre
-- (no por id) para no depender de que los ids coincidan con los tuyos.
INSERT INTO plantillas_constructor (constructor_id, nombre, ingredientes, orden)
  SELECT 'poke', 'Poke Clásico', GROUP_CONCAT(i.id ORDER BY i.id), 1
  FROM ingredientes i
  JOIN grupos_ingredientes g ON g.id = i.grupo_id
  WHERE g.constructor_id = 'poke'
    AND i.nombre IN ('Arroz', 'Salmón', 'Aguacate', 'Edamame', 'Maíz dulce', 'Masago')
  HAVING COUNT(*) > 0
  ON DUPLICATE KEY UPDATE ingredientes = VALUES(ingredientes);

-- -----------------------------------------------------------------
-- Tabla: tamanos_producto  (tamaños opcionales de un producto de
-- catalogo estandar -- ej. "Chico" / "Grande" -- cada uno con su
-- propio precio. Si un producto no tiene filas acá, se vende con su
-- precio unico de siempre).
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tamanos_producto (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  nombre      VARCHAR(60)  NOT NULL,
  precio      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  orden       INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_producto_tamano (producto_id, nombre),
  CONSTRAINT fk_tamano_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: configuracion  (clave -> valor, datos generales del negocio)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(60)  NOT NULL PRIMARY KEY,
  valor VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: publicaciones  (banners/publicaciones personalizadas de la
-- landing -- ej. avisar un descuento con una foto que sube el
-- admin). El panel permite hasta 2 filas (crear, editar, borrar).
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS publicaciones (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  titulo         VARCHAR(160) NOT NULL DEFAULT '',
  texto          VARCHAR(500) NOT NULL DEFAULT '',
  imagen         VARCHAR(255) NOT NULL DEFAULT '',
  enlace_texto   VARCHAR(60)  NOT NULL DEFAULT '',
  enlace_url     VARCHAR(255) NOT NULL DEFAULT '',
  activo         TINYINT(1)   NOT NULL DEFAULT 0,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Tabla: admin_usuarios  (panel de administracion)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_usuarios (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario           VARCHAR(60)  NOT NULL UNIQUE,
  password_hash     VARCHAR(255) NOT NULL,
  intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME     NULL,
  ultimo_acceso     DATETIME     NULL,
  creado_en         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existia de una version anterior (sin estas columnas),
-- estas dos lineas se las agregan. Requieren MySQL 8.0.29+ / MariaDB
-- 10.0+; si tu hosting es mas viejo y estas lineas fallan, no pasa
-- nada: el resto del script sigue funcionando igual, solo no tendras
-- el bloqueo por intentos fallidos hasta que las agregues a mano.
ALTER TABLE admin_usuarios ADD COLUMN IF NOT EXISTS intentos_fallidos INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE admin_usuarios ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL;
ALTER TABLE admin_usuarios ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL;

-- -----------------------------------------------------------------
-- Tabla: descuentos
-- -----------------------------------------------------------------
-- Un descuento aplica a un "alcance" (todo el menu, una categoria,
-- un producto estandar, un constructor completo, un ingrediente
-- especifico dentro de un constructor, o un "combo": un grupo de
-- ingredientes puntuales de UN constructor que, elegidos TODOS
-- juntos, disparan el descuento -- ej. "Yogurt con granola + miel +
-- fresa: 15% off") y, opcionalmente, solo en ciertos dias de la
-- semana (NULL = todos los dias).
--
--   alcance='global'       -> referencia_id se ignora (NULL)
--   alcance='categoria'    -> referencia_id = categorias.id
--   alcance='producto'     -> referencia_id = productos.id
--   alcance='constructor'  -> referencia_id = constructores.id
--   alcance='ingrediente'  -> referencia_id = ingredientes.id
--   alcance='combo'        -> referencia_id = constructores.id,
--                              combo_ingredientes = "12,14,20" (ids
--                              de ingredientes.id que deben estar
--                              TODOS seleccionados; se evalua en el
--                              navegador, ver assets/js/main.js)
--
-- dias: lista separada por comas con codigos de 3 letras
-- (lun,mar,mie,jue,vie,sab,dom). NULL o vacio = todos los dias.
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS descuentos (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(120) NOT NULL,
  tipo                ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje',
  valor               DECIMAL(8,2) NOT NULL,
  alcance             ENUM('global','categoria','producto','constructor','ingrediente','combo') NOT NULL,
  referencia_id       VARCHAR(60)  NULL,
  combo_ingredientes  TEXT         NULL,
  dias                VARCHAR(40)  NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si la tabla ya existia de una version anterior (sin "combo"), esto
-- la actualiza. Requiere MySQL 8.0.29+ / MariaDB 10.0+; si tu
-- hosting es mas viejo y esta linea falla, agregala a mano.
ALTER TABLE descuentos ADD COLUMN IF NOT EXISTS combo_ingredientes TEXT NULL AFTER referencia_id;
ALTER TABLE descuentos MODIFY COLUMN alcance ENUM('global','categoria','producto','constructor','ingrediente','combo') NOT NULL;

-- =================================================================
-- Migracion: limpia el catalogo de EJEMPLO de versiones anteriores
-- (categorias/productos ficticios) para sembrar el menu real.
-- =================================================================
DELETE FROM categorias WHERE id IN ('bowls','yogures','postres','coffee','yogurt','bowl','ensaladas','fatness','smoothies','peanut');
DELETE FROM constructores WHERE id IN ('yogurt','malteadas','poke','salad-bar','bowl-acai','waffles');

-- =================================================================
-- SECCIONES ESTANDAR (no personalizables) -- menu real
-- =================================================================

INSERT INTO categorias (id, nombre, icono, orden) VALUES
  ('smoothies', 'Smoothies', 'smoothies', 1),
  ('jugos-vitales', 'Jugos Vitales', 'gota', 2),
  ('cafe', 'Café', 'cafe', 3),
  ('postres-saludables', 'Postres Saludables', 'postre', 4)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), icono = VALUES(icono), orden = VALUES(orden);

INSERT INTO productos (categoria_id, nombre, descripcion, precio, imagen, orden) VALUES
  ('smoothies', 'Clásico', 'Jugo de naranja, fresa, banana.', 4.00, 'https://picsum.photos/seed/lifefit-smoothie-clasico/600/450', 1),
  ('smoothies', 'Plant Power', 'Leche de almendras, piña, banana, espinaca, mantequilla de maní.', 4.50, 'https://picsum.photos/seed/lifefit-plant-power/600/450', 2),
  ('smoothies', 'Blue Magic', 'Jugo de naranja, piña, espirulina azul.', 4.50, 'https://picsum.photos/seed/lifefit-blue-magic/600/450', 3),
  ('smoothies', 'Creamy Colada', 'Agua de coco, leche de coco, banana, piña, coco rallado.', 4.50, 'https://picsum.photos/seed/lifefit-creamy-colada/600/450', 4),
  ('smoothies', 'Bluenut', 'Jugo de naranja, blueberries, banana, mantequilla de maní.', 4.50, 'https://picsum.photos/seed/lifefit-bluenut/600/450', 5),
  ('smoothies', 'Choco Love', 'Leche de almendras, cacao, banana, aguacate, miel.', 4.50, 'https://picsum.photos/seed/lifefit-choco-love/600/450', 6),

  ('jugos-vitales', 'Flujo Rojo', 'Remolacha, manzana roja, jengibre fresco, jugo de naranja.', 3.50, 'https://picsum.photos/seed/lifefit-flujo-rojo/600/450', 1),
  ('jugos-vitales', 'Energía Mental', 'Té matcha premium, leche de almendras, vainilla natural.', 3.50, 'https://picsum.photos/seed/lifefit-energia-mental/600/450', 2),
  ('jugos-vitales', 'Filtro Natural', 'Pepino, piña dulce, perejil fresco.', 3.50, 'https://picsum.photos/seed/lifefit-filtro-natural/600/450', 3),
  ('jugos-vitales', 'Azul Alcalino', 'Manzana verde, pepino, espirulina azul.', 3.50, 'https://picsum.photos/seed/lifefit-azul-alcalino/600/450', 4),
  ('jugos-vitales', 'Aire Puro', 'Zanahoria, manzana, cúrcuma, pimienta negra.', 3.50, 'https://picsum.photos/seed/lifefit-aire-puro/600/450', 5),
  ('jugos-vitales', 'Probiótico Pitaya', 'Pitaya rosada, yogurt natural, fresas.', 3.50, 'https://picsum.photos/seed/lifefit-probiotico-pitaya/600/450', 6),

  ('cafe', 'Café', 'Expresso, Espresso Ristretto, Espresso Macchiato, Americano, Latte, Hazeltella, Cappuccino, Moccaccino, Caramel Latte, Latte Vainilla, Tiramisú Latte.', 2.50, 'https://picsum.photos/seed/lifefit-cafe/600/450', 1),
  ('cafe', 'Frappuccino', 'Clásico, Vainilla, Mocha, Caramelo.', 3.00, 'https://picsum.photos/seed/lifefit-frappuccino/600/450', 2),
  ('cafe', 'Iced Coffee', 'Caramelo, Vainilla, Avellana, Pistachio, Mocha.', 3.00, 'https://picsum.photos/seed/lifefit-iced-coffee/600/450', 3),
  ('cafe', 'Matcha Latte (Sin azúcar)', 'Caliente o Frío.', 3.00, 'https://picsum.photos/seed/lifefit-matcha-latte/600/450', 4),

  ('postres-saludables', 'Cheesecake de Brownie', 'Postre saludable, gluten free.', 3.50, 'https://picsum.photos/seed/lifefit-cheesecake-brownie/600/450', 1),
  ('postres-saludables', 'Cheesecake de Red Velvet', 'Postre saludable, gluten free.', 3.50, 'https://picsum.photos/seed/lifefit-cheesecake-redvelvet/600/450', 2),
  ('postres-saludables', 'Crumble de Manzana', 'Postre saludable, gluten free.', 3.00, 'https://picsum.photos/seed/lifefit-crumble-manzana/600/450', 3),
  ('postres-saludables', 'Muffin BananaChips', 'Postre saludable, gluten free.', 2.50, 'https://picsum.photos/seed/lifefit-muffin-bananachips/600/450', 4),
  ('postres-saludables', 'Muffin ChocoChips', 'Postre saludable, gluten free.', 2.50, 'https://picsum.photos/seed/lifefit-muffin-chocochips/600/450', 5),
  ('postres-saludables', 'Muffin Zanahoria', 'Postre saludable, gluten free.', 2.50, 'https://picsum.photos/seed/lifefit-muffin-zanahoria/600/450', 6)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), precio = VALUES(precio), imagen = VALUES(imagen);

-- =================================================================
-- CONSTRUCTORES ("arma tu X") -- personalizables
-- =================================================================

INSERT INTO constructores (id, nombre, descripcion, nota, precio_base, imagen, icono, orden) VALUES
  ('yogurt', 'Crea tu Yogurt', 'Base: Banana, fresa y piña.', '', 3.50, 'https://picsum.photos/seed/lifefit-crea-yogurt/700/560', 'yogurt', 1),
  ('malteadas', 'Malteadas', 'Elige tu sabor favorito.', '', 4.50, 'https://picsum.photos/seed/lifefit-malteadas/700/560', 'fatness', 2),
  ('poke', 'Arma tu Poke', 'Elige base, proteína y toppings para tu poke bowl.', 'También disponible en versión Mini: 1 proteína / 3 toppings.', 8.00, 'https://picsum.photos/seed/lifefit-arma-poke/700/560', 'bowl', 3),
  ('salad-bar', 'Salad Bar', 'Base: Lechuga. Arma tu ensalada a tu gusto.', '', 6.50, 'https://picsum.photos/seed/lifefit-salad-bar/700/560', 'ensaladas', 4),
  ('bowl-acai', 'Crea tu Bowl', 'Base de açaí, dragon fruit o espirulina azul (incluye banana y fresa congelada).', '', 7.00, 'https://picsum.photos/seed/lifefit-crea-bowl/700/560', 'bowl', 5),
  ('waffles', 'Waffles', 'Base: 2 Mini Waffles.', 'También disponible en versión Gluten Free (harina de avena, huevo y leche + toppings).', 5.50, 'https://picsum.photos/seed/lifefit-waffles/700/560', 'waffle', 6)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), nota = VALUES(nota), precio_base = VALUES(precio_base), imagen = VALUES(imagen), icono = VALUES(icono), orden = VALUES(orden);

-- ---------- Crea tu Yogurt ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('yogurt', 'Frutos secos', 0, 3, 1),
  ('yogurt', 'Frutas', 0, 3, 2),
  ('yogurt', 'Extras', 0, 3, 3),
  ('yogurt', 'Frutas deshidratadas', 0, 3, 4),
  ('yogurt', 'Syrups (sin azúcar)', 0, 2, 5)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Almendras' nombre, 1 orden UNION ALL SELECT 'Nueces', 2 UNION ALL SELECT 'Merey', 3
  ) v ON g.constructor_id = 'yogurt' AND g.nombre = 'Frutos secos'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Kiwi' nombre, 1 orden UNION ALL SELECT 'Durazno', 2 UNION ALL SELECT 'Blueberries', 3
  ) v ON g.constructor_id = 'yogurt' AND g.nombre = 'Frutas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Chía' nombre, 1 orden UNION ALL SELECT 'Miel', 2 UNION ALL SELECT 'Granola', 3
  ) v ON g.constructor_id = 'yogurt' AND g.nombre = 'Extras'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Cranberries' nombre, 1 orden UNION ALL SELECT 'Cherries', 2 UNION ALL SELECT 'Ciruelas', 3 UNION ALL SELECT 'Dátiles', 4
  ) v ON g.constructor_id = 'yogurt' AND g.nombre = 'Frutas deshidratadas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Caramelo' nombre, 1 orden UNION ALL SELECT 'Pistachio', 2 UNION ALL SELECT 'Mantequilla de maní', 3
  ) v ON g.constructor_id = 'yogurt' AND g.nombre = 'Syrups (sin azúcar)'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- ---------- Malteadas ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('malteadas', 'Sabor', 1, 1, 1)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Nutella' nombre, 1 orden UNION ALL SELECT 'Ovomaltina', 2 UNION ALL SELECT 'Kit Kat', 3
    UNION ALL SELECT 'Oreo', 4 UNION ALL SELECT 'Brownie', 5 UNION ALL SELECT 'Reese''s', 6
    UNION ALL SELECT 'Snickers', 7 UNION ALL SELECT 'Samba', 8 UNION ALL SELECT 'M&M', 9
    UNION ALL SELECT 'Flips', 10 UNION ALL SELECT 'Chips Ahoy', 11 UNION ALL SELECT 'Cocosette', 12
  ) v ON g.constructor_id = 'malteadas' AND g.nombre = 'Sabor'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- ---------- Arma tu Poke ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('poke', 'Elige tu base', 1, 1, 1),
  ('poke', 'Elige tu proteína (2 max)', 1, 2, 2),
  ('poke', 'Elige tus toppings (4 max)', 0, 4, 3)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (SELECT 'Arroz' nombre, 1 orden UNION ALL SELECT 'Quinoa', 2) v
  ON g.constructor_id = 'poke' AND g.nombre = 'Elige tu base'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Pollo' nombre, 1 orden UNION ALL SELECT 'Camarón fresco', 2
    UNION ALL SELECT 'Camarón tempura', 3 UNION ALL SELECT 'Salmón', 4
  ) v ON g.constructor_id = 'poke' AND g.nombre = 'Elige tu proteína (2 max)'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Edamame' nombre, 1 orden UNION ALL SELECT 'Pasta dinamita', 2 UNION ALL SELECT 'Wakame', 3
    UNION ALL SELECT 'Maní Japonés', 4 UNION ALL SELECT 'Maíz dulce', 5 UNION ALL SELECT 'Aguacate', 6
    UNION ALL SELECT 'Masago', 7 UNION ALL SELECT 'Repollo morado', 8
  ) v ON g.constructor_id = 'poke' AND g.nombre = 'Elige tus toppings (4 max)'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- ---------- Salad Bar ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('salad-bar', 'Proteína', 0, 1, 1),
  ('salad-bar', 'Vegetales', 0, NULL, 2),
  ('salad-bar', 'Frutas', 0, NULL, 3),
  ('salad-bar', 'Frutos secos y extras', 0, NULL, 4),
  ('salad-bar', 'Aderezo', 1, 1, 5)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Pollo' nombre, 1 orden UNION ALL SELECT 'Atún', 2 UNION ALL SELECT 'Salmón', 3 UNION ALL SELECT 'Camarones', 4
  ) v ON g.constructor_id = 'salad-bar' AND g.nombre = 'Proteína'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Zanahoria' nombre, 1 orden UNION ALL SELECT 'Tomate', 2 UNION ALL SELECT 'Pepino', 3
    UNION ALL SELECT 'Aguacate', 4 UNION ALL SELECT 'Brócoli', 5 UNION ALL SELECT 'Pimentón', 6
    UNION ALL SELECT 'Coliflor', 7 UNION ALL SELECT 'Champiñón', 8 UNION ALL SELECT 'Aceitunas', 9
  ) v ON g.constructor_id = 'salad-bar' AND g.nombre = 'Vegetales'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Maíz dulce' nombre, 1 orden UNION ALL SELECT 'Fresa', 2 UNION ALL SELECT 'Piña', 3
    UNION ALL SELECT 'Kiwi', 4 UNION ALL SELECT 'Blueberries', 5 UNION ALL SELECT 'Cranberries', 6 UNION ALL SELECT 'Cherries', 7
  ) v ON g.constructor_id = 'salad-bar' AND g.nombre = 'Frutas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Almendras' nombre, 1 orden UNION ALL SELECT 'Merey', 2 UNION ALL SELECT 'Nueces', 3
    UNION ALL SELECT 'Chía', 4 UNION ALL SELECT 'Mozzarella', 5 UNION ALL SELECT 'Parmesano', 6
    UNION ALL SELECT 'Croutons', 7 UNION ALL SELECT 'Tocineta', 8
  ) v ON g.constructor_id = 'salad-bar' AND g.nombre = 'Frutos secos y extras'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, descripcion, orden)
  SELECT id, v.nombre, v.descripcion, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Zesty Italian' nombre, 'Aceite de oliva, pimientos rojos, sal y parmesano.' descripcion, 1 orden
    UNION ALL SELECT 'Classic Cesar', 'Alioli acompañada de quesos madurados y mostaza.', 2
    UNION ALL SELECT 'Mostaza Miel', 'Sabor a mostaza deliciosamente dulce.', 3
    UNION ALL SELECT 'Aceite de Oliva', 'Extra virgen.', 4
  ) v ON g.constructor_id = 'salad-bar' AND g.nombre = 'Aderezo'
  ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), orden = VALUES(orden);

-- ---------- Crea tu Bowl (açai) ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('bowl-acai', 'Base', 1, 1, 1),
  ('bowl-acai', 'Frutas', 0, 4, 2),
  ('bowl-acai', 'Frutas deshidratadas', 0, 3, 3),
  ('bowl-acai', 'Frutos secos', 0, 3, 4),
  ('bowl-acai', 'Extras (sin azúcar)', 0, NULL, 5)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (SELECT 'Açaí' nombre, 1 orden UNION ALL SELECT 'Dragon Fruit', 2 UNION ALL SELECT 'Espirulina azul', 3) v
  ON g.constructor_id = 'bowl-acai' AND g.nombre = 'Base'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Banana' nombre, 1 orden UNION ALL SELECT 'Fresa', 2 UNION ALL SELECT 'Piña', 3
    UNION ALL SELECT 'Durazno', 4 UNION ALL SELECT 'Kiwi', 5 UNION ALL SELECT 'Blueberries', 6
  ) v ON g.constructor_id = 'bowl-acai' AND g.nombre = 'Frutas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Cranberries' nombre, 1 orden UNION ALL SELECT 'Cherries', 2 UNION ALL SELECT 'Ciruela', 3 UNION ALL SELECT 'Dátiles', 4
  ) v ON g.constructor_id = 'bowl-acai' AND g.nombre = 'Frutas deshidratadas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (SELECT 'Almendras' nombre, 1 orden UNION ALL SELECT 'Nueces', 2 UNION ALL SELECT 'Merey', 3) v
  ON g.constructor_id = 'bowl-acai' AND g.nombre = 'Frutos secos'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Chía' nombre, 1 orden UNION ALL SELECT 'Coco rallado', 2 UNION ALL SELECT 'Granola', 3
    UNION ALL SELECT 'Gotas de chocolate oscuro', 4 UNION ALL SELECT 'Gotas de chocolate blanco', 5
    UNION ALL SELECT 'Gotas de mantequilla de maní', 6 UNION ALL SELECT 'Syrup de mantequilla de maní', 7
    UNION ALL SELECT 'Syrup de caramelo', 8 UNION ALL SELECT 'Syrup de pistacho', 9
  ) v ON g.constructor_id = 'bowl-acai' AND g.nombre = 'Extras (sin azúcar)'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- ---------- Waffles ----------
INSERT INTO grupos_ingredientes (constructor_id, nombre, seleccion_min, seleccion_max, orden) VALUES
  ('waffles', 'Syrup', 0, 2, 1),
  ('waffles', 'Frutas', 0, 2, 2),
  ('waffles', 'Toppings nacionales', 0, 3, 3),
  ('waffles', 'Toppings importados', 0, 3, 4)
  ON DUPLICATE KEY UPDATE seleccion_min = VALUES(seleccion_min), seleccion_max = VALUES(seleccion_max), orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Nutella' nombre, 1 orden UNION ALL SELECT 'Miel', 2 UNION ALL SELECT 'Leche condensada', 3 UNION ALL SELECT 'Ovomaltina', 4
  ) v ON g.constructor_id = 'waffles' AND g.nombre = 'Syrup'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (SELECT 'Fresa' nombre, 1 orden UNION ALL SELECT 'Banana', 2) v
  ON g.constructor_id = 'waffles' AND g.nombre = 'Frutas'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'Oreo Americana' nombre, 1 orden UNION ALL SELECT 'Chips Ahoy', 2 UNION ALL SELECT 'Samba', 3 UNION ALL SELECT 'Cocosette', 4
  ) v ON g.constructor_id = 'waffles' AND g.nombre = 'Toppings nacionales'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

INSERT INTO ingredientes (grupo_id, nombre, orden)
  SELECT id, v.nombre, v.orden FROM grupos_ingredientes g
  JOIN (
    SELECT 'M&M' nombre, 1 orden UNION ALL SELECT 'Snickers', 2 UNION ALL SELECT 'Kit Kat', 3 UNION ALL SELECT 'Reese''s', 4
  ) v ON g.constructor_id = 'waffles' AND g.nombre = 'Toppings importados'
  ON DUPLICATE KEY UPDATE orden = VALUES(orden);

-- =================================================================
-- Configuracion general del negocio
-- =================================================================
DELETE FROM configuracion WHERE clave = 'horario';

-- El campo 'whatsapp' guardaba antes un link completo; ahora se
-- guarda solo el numero (whatsapp_numero) y el link se arma en el
-- codigo. Si venis de una instalacion vieja, esto migra el numero
-- de pruebas que ya estaba en uso a la clave nueva.
INSERT INTO configuracion (clave, valor)
  SELECT 'whatsapp_numero', valor FROM configuracion WHERE clave = 'whatsapp'
  ON DUPLICATE KEY UPDATE valor = VALUES(valor);
DELETE FROM configuracion WHERE clave = 'whatsapp';

INSERT INTO configuracion (clave, valor) VALUES
  ('nombre',    'LIFE FIT'),
  ('eslogan',   'Vive bien. Vive saludable. Vive con LifeFit.'),
  ('ubicacion', 'Parque Los Ilustres'),
  ('horario_semana', 'Lun – Vie · 12:00 pm – 8:00 pm'),
  ('horario_finde',  'Sáb – Dom · 2:00 pm – 10:00 pm'),
  ('whatsapp_numero', '584126448878'),
  ('instagram', 'lifefit'),
  ('tiktok',    '')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

SET FOREIGN_KEY_CHECKS = 1;
