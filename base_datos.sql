-- ============================================================
--  BASE DE DATOS ÚNICA del sitio (CRUD de ventas + Encuesta UPVM)
--  Importa este archivo UNA vez en phpMyAdmin (pestaña "Importar"),
--  dentro de la base de datos que creaste en InfinityFree.
--
--  Tablas:
--    usuarios, productos, ventas  -> CRUD de ventas
--    respuestas                   -> Encuesta UPVM
-- ============================================================

-- ---------- CRUD de ventas ----------
CREATE TABLE IF NOT EXISTS usuarios (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre    VARCHAR(120) NOT NULL,
  correo    VARCHAR(160) NOT NULL,
  telefono  VARCHAR(30)  DEFAULT NULL,
  activo    TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  nombre      VARCHAR(120)  NOT NULL,
  descripcion TEXT          DEFAULT NULL,
  precio      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  stock       INT           NOT NULL DEFAULT 0,
  imagen      VARCHAR(255)  DEFAULT NULL,
  activo      TINYINT(1)    NOT NULL DEFAULT 1,
  creado_en   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ventas (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  usuario_id      INT UNSIGNED  NOT NULL,
  producto_id     INT UNSIGNED  NOT NULL,
  cantidad        INT           NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  descuento       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,  -- porcentaje
  tasa_impuesto   DECIMAL(5,2)  NOT NULL DEFAULT 16.00, -- porcentaje (IVA)
  subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- los calcula MySQL
  monto_descuento DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- los calcula MySQL
  monto_impuesto  DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- los calcula MySQL
  total_final     DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- los calcula MySQL
  fecha           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ventas_usuario  (usuario_id),
  KEY idx_ventas_producto (producto_id),
  CONSTRAINT fk_ventas_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios  (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ventas_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Encuesta UPVM ----------
CREATE TABLE IF NOT EXISTS respuestas (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  profesores  VARCHAR(30)  NOT NULL,   -- pregunta 1 (select)
  instalacion VARCHAR(30)  NOT NULL,   -- pregunta 2 (select)
  wifi        VARCHAR(40)  NOT NULL,   -- pregunta 3 (select)
  biblioteca  VARCHAR(20)  NOT NULL,   -- pregunta 4 (opción múltiple)
  sugerencia  VARCHAR(300) NOT NULL,   -- pregunta 5 (texto libre)
  fecha       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
