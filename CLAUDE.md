# CLAUDE.md — Sistema Web de Control de Asistencia "GLEE"

> Este archivo es el contexto permanente del proyecto. Claude Code lo lee automáticamente
> al inicio de cada sesión. Mantenerlo actualizado conforme avance el proyecto.

## 1. Objetivo del proyecto

Sistema web centralizado para el **control de asistencia del personal** de la cadena de
boutiques **GLEE**, integrando **biometría facial** y **geolocalización GPS**, con el fin de:

- Eliminar el fraude por suplantación de identidad ("buddy punching").
- Confirmar quién marca (rostro) y dónde marca (GPS dentro de la sucursal).
- Automatizar el cálculo de retardos y tiempo extra en turnos rotativos.
- Consolidar la información de las 4 sucursales para el cálculo de nómina.

Es un **Proyecto de Graduación**. La primera meta es una **DEMO funcional (mín. 50%)**
que debe **correr de verdad** (no solo mostrar código).

## 2. Contexto de la empresa

- Cadena de boutiques **GLEE**, 4 sucursales: **2 en Jalapa** y **2 en Ciudad de Guatemala**.
- Aproximadamente **12 a 15 colaboradores**.
- Cada sucursal tiene un **smartphone corporativo** con internet (el sistema corre ahí).

## 3. Stack tecnológico (NO cambiar — es el aprobado)

- **Backend:** PHP (arquitectura cliente–servidor).
- **Frontend:** HTML, CSS, JavaScript. Diseño **mobile-first** (se usa desde celular).
- **Base de datos:** MySQL (relacional).
- **Biometría facial:** `face-api.js` (se ejecuta en el navegador, del lado del cliente).
- **Geolocalización:** API de Geolocalización nativa de HTML5 (geocercas).
- **PWA:** la app web debe ser instalable como app nativa (manifest + service worker).

## 4. Entorno de desarrollo

- **Local:** Laragon (PHP + MySQL + Apache) en Windows. Se corre en `localhost`.
- **Importante:** la cámara (`face-api.js`) y el GPS (geolocalización) **solo funcionan en
  `localhost` o en HTTPS**. En `localhost` funcionan sin problema, así que desarrollar y
  hacer la demo en local es lo correcto.
- **Producción final (después de la demo):** hosting en la nube (Hostinger). No es necesario aún.

## 5. Roles y permisos

| Rol            | Descripción                          | Accesos principales                                   |
|----------------|--------------------------------------|-------------------------------------------------------|
| Administrador  | Control total del sistema            | CRUD usuarios, CRUD sucursales, ver/exportar reportes |
| Supervisor     | Supervisión regional                 | Ver asistencia de su sucursal, reportes               |
| Vendedor       | Personal operativo de tienda         | Marcar entrada/salida, ver sus propias marcas         |
| Bodega         | Personal de bodega (turnos rotativos)| Marcar entrada/salida, ver sus propias marcas         |

El sistema debe **mostrar opciones distintas según el rol** (control de acceso por rol).

## 6. Modelo de datos principal (orientativo)

- **sucursales**: id, nombre, departamento, latitud, longitud, radio_geocerca_metros, activo.
- **usuarios**: id, nombre_completo, usuario/correo, password_hash, rol, sucursal_id, activo.
- **descriptores_faciales**: id, usuario_id, descriptor (JSON del vector de face-api.js).
- **horarios**: id, usuario_id, dia/fecha, hora_entrada, hora_salida (soporta turnos rotativos).
- **marcas_asistencia**: id, usuario_id, sucursal_id, tipo (entrada|salida), fecha_hora
  (timestamp del servidor), latitud, longitud, dentro_geocerca (bool), minutos_variacion,
  foto_path (opcional).

## 7. Funcionalidades núcleo

1. **Login con roles** — sesiones PHP, control de acceso por rol.
2. **Marcaje de asistencia** — un botón principal de entrada/salida (interfaz simple).
3. **Biometría facial** — captura por cámara, genera/compara el descriptor con `face-api.js`.
4. **Geocerca GPS** — valida que el colaborador esté dentro del radio físico de su sucursal.
5. **Algoritmo de varianza de tiempo** — compara el timestamp real contra el horario asignado
   (turnos rotativos) y calcula minutos de retardo o tiempo extra automáticamente.
6. **Reportes** — totalización mensual de horas y minutos por colaborador y sucursal.

## 8. Reglas y restricciones del negocio

- **Retención de evidencias fotográficas: máximo 60 días** (depuración automática).
- El cálculo se limita a varianza de tiempo (retardos / horas). **No** hay integración con
  software contable de terceros ni pasarelas bancarias.
- El timestamp oficial siempre lo genera **el servidor**, no el dispositivo del cliente.

## 9. Convenciones de código y seguridad (obligatorias)

- **Contraseñas:** siempre con `password_hash()` / `password_verify()`. Nunca texto plano.
- **Consultas a BD:** siempre con **sentencias preparadas (PDO o mysqli)**. Nunca concatenar
  variables en SQL (prevención de inyección SQL).
- **Sesiones:** validar rol en cada página protegida; redirigir si no hay sesión.
- **Validación:** validar y sanitizar toda entrada del usuario (servidor y cliente).
- **Código comentado en español** y nombres de variables/tablas descriptivos en español.
- **Estructura clara de carpetas:** separar config, vistas, lógica y assets.
- Credenciales de BD en un archivo de configuración aparte (`config.php`), no quemadas en cada archivo.

## 10. Estado actual

- **Fase:** Desarrollo del prototipo / DEMO (login, roles y CRUD).
- **Punto de partida:** proyecto desde cero.

## 11. Cómo trabajar conmigo (instrucciones para Claude Code)

- Antes de programar algo grande, **propón un plan** y espera confirmación.
- Trabaja en **incrementos pequeños y probables**: que cada paso se pueda correr y ver.
- Después de cada bloque, indica **cómo probarlo en el navegador** (qué URL, qué usuario).
- Si una decisión técnica tiene varias opciones, **explícalas brevemente** antes de elegir.
- Prioriza que la demo **se vea funcionando**, no la perfección del código.
