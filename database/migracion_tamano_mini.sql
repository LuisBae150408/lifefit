-- ============================================================
-- Migración: tamaño "Mini" en los constructores de comida
-- ------------------------------------------------------------
-- Ejecutar UNA sola vez, después de importar schema.sql.
-- Agrega el precio de la versión Mini de un constructor y los
-- límites de selección alternativos por grupo de ingredientes
-- (ej. Poke Mini: proteína pasa de "2 máx." a "1").
--
-- Si MySQL responde "Duplicate column name", esa columna ya
-- existe y puedes ignorar el error.
-- ============================================================

ALTER TABLE constructores
  ADD COLUMN precio_mini DECIMAL(8,2) NULL AFTER precio_base;

ALTER TABLE grupos_ingredientes
  ADD COLUMN seleccion_min_mini INT NULL AFTER seleccion_max,
  ADD COLUMN seleccion_max_mini INT NULL AFTER seleccion_min_mini;

-- Ejemplo (opcional): activar el tamaño Mini en el constructor 'poke'
-- UPDATE constructores SET precio_mini = 6.00 WHERE id = 'poke';
-- UPDATE grupos_ingredientes SET seleccion_max_mini = 1
--   WHERE constructor_id = 'poke' AND nombre LIKE 'Elige tu prote%';
