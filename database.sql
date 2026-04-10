-- ============================================================
-- GLEE Sistema de Control de Asistencia
-- Script completo de base de datos
-- ============================================================

CREATE DATABASE IF NOT EXISTS glee_asistencia
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE glee_asistencia;

-- Eliminar tablas en orden inverso de dependencias
DROP TABLE IF EXISTS marcas_asistencia;
DROP TABLE IF EXISTS descriptores_faciales;
DROP TABLE IF EXISTS horarios;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS sucursales;

-- --------------------------------------------------------
-- Tabla: sucursales
-- --------------------------------------------------------
CREATE TABLE sucursales (
    id                    INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    nombre                VARCHAR(100)    NOT NULL,
    departamento          VARCHAR(60)     NOT NULL,
    latitud               DECIMAL(10,8)   NOT NULL,
    longitud              DECIMAL(11,8)   NOT NULL,
    radio_geocerca_metros INT UNSIGNED    NOT NULL DEFAULT 50,
    activo                TINYINT(1)      NOT NULL DEFAULT 1,
    created_at            TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: usuarios
-- --------------------------------------------------------
CREATE TABLE usuarios (
    id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    nombre_completo  VARCHAR(120)   NOT NULL,
    correo           VARCHAR(120)   NOT NULL UNIQUE,
    password_hash    VARCHAR(255)   NOT NULL,
    rol              ENUM('administrador','supervisor','vendedor','bodega')
                                    NOT NULL DEFAULT 'vendedor',
    tipo_jornada     ENUM('completa','media')              NOT NULL DEFAULT 'completa',
    sucursal_id      INT UNSIGNED   NULL,
    activo           TINYINT(1)     NOT NULL DEFAULT 1,
    created_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_sucursal
        FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: descriptores_faciales (para face-api.js)
-- --------------------------------------------------------
CREATE TABLE descriptores_faciales (
    id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED  NOT NULL UNIQUE,
    descriptor  JSON          NOT NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_descriptor_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: horarios (soporta turnos rotativos)
-- --------------------------------------------------------
CREATE TABLE horarios (
    id                   INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    usuario_id           INT UNSIGNED     NOT NULL,
    dia_semana           TINYINT UNSIGNED NOT NULL COMMENT '0=Dom 1=Lun 2=Mar 3=Mié 4=Jue 5=Vie 6=Sáb',
    hora_entrada         TIME             NOT NULL,
    hora_salida_comida   TIME             NULL COMMENT 'NULL = media jornada o sin pausa de almuerzo',
    hora_regreso_comida  TIME             NULL,
    hora_salida          TIME             NOT NULL,
    activo               TINYINT(1)       NOT NULL DEFAULT 1,
    UNIQUE KEY uq_usuario_dia (usuario_id, dia_semana),
    CONSTRAINT fk_horario_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabla: marcas_asistencia
-- --------------------------------------------------------
CREATE TABLE marcas_asistencia (
    id                 INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    usuario_id         INT UNSIGNED  NOT NULL,
    sucursal_id        INT UNSIGNED  NOT NULL,
    tipo               ENUM('entrada','salida_comida','regreso_comida','salida') NOT NULL,
    -- El timestamp SIEMPRE lo genera el servidor, nunca el cliente
    fecha_hora         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    latitud            DECIMAL(10,8) NULL,
    longitud           DECIMAL(11,8) NULL,
    dentro_geocerca    TINYINT(1)    NULL COMMENT '1=dentro 0=fuera NULL=no verificado',
    minutos_variacion  INT           NULL COMMENT 'Positivo=retardo | Negativo=anticipado',
    foto_path          VARCHAR(255)  NULL,
    CONSTRAINT fk_marca_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_marca_sucursal
        FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS DE PRUEBA — Sucursales
-- NOTA: Las coordenadas son aproximadas para la demo.
--       Reemplazar con coordenadas GPS reales antes de usar
--       la funcionalidad de geocerca en producción.
-- ============================================================
INSERT INTO sucursales (nombre, departamento, latitud, longitud, radio_geocerca_metros) VALUES
('Tienda Jalapa / Bodega Central', 'Jalapa',    14.53540000, -89.99290000, 50),
('Arenita Morena',                 'Jalapa',    14.53200000, -89.99100000, 50),
('Tienda Quo',                     'Guatemala', 14.63490000, -90.50690000, 50),
('Parque las Américas',            'Guatemala', 14.58970000, -90.52270000, 50);

-- --------------------------------------------------------
-- Tabla: incidencias (permisos y justificaciones)
-- --------------------------------------------------------
CREATE TABLE incidencias (
    id              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED  NOT NULL,
    fecha           DATE          NOT NULL              COMMENT 'Fecha del evento (no de la solicitud)',
    tipo            ENUM('permiso','retardo_justificado','falta_justificada','salida_anticipada')
                                  NOT NULL,
    motivo          TEXT          NOT NULL,
    estado          ENUM('pendiente','aprobada','rechazada')
                                  NOT NULL DEFAULT 'pendiente',
    respondido_por  INT UNSIGNED  NULL                 COMMENT 'Admin que procesó la solicitud',
    respuesta       TEXT          NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incidencia_usuario
        FOREIGN KEY (usuario_id)    REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_incidencia_admin
        FOREIGN KEY (respondido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


