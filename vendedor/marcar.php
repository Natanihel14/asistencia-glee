<?php
/**
 * vendedor/marcar.php — Marcaje de asistencia con secuencia de 4 pasos (o 2 para media jornada).
 * Secuencia completa: entrada → salida_comida → regreso_comida → salida
 * Secuencia media:    entrada → salida
 * El servidor valida que el tipo enviado sea el siguiente esperado en la secuencia.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requerirRol(['vendedor', 'bodega']);

$pdo       = conectarBD();
$usuarioId = (int)$_SESSION['usuario_id'];

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

// Tipo de jornada, sucursal asignada y descriptor facial del colaborador
$stmtUser = $pdo->prepare('
    SELECT u.tipo_jornada, u.sucursal_id, d.descriptor 
    FROM usuarios u
    LEFT JOIN descriptores_faciales d ON u.id = d.usuario_id
    WHERE u.id = ? LIMIT 1
');
$stmtUser->execute([$usuarioId]);
$userInfo      = $stmtUser->fetch();
$tipoJornada   = $userInfo['tipo_jornada'] ?? 'completa';
$sucursalIdDef = (int)($userInfo['sucursal_id'] ?? 0);
$descriptorBiometrico = $userInfo['descriptor'] ?? null;

// Todas las sucursales activas para el selector
$todasSucursales = $pdo->query(
    'SELECT id, nombre, latitud, longitud FROM sucursales WHERE activo = 1 ORDER BY nombre'
)->fetchAll();

// Última marca del colaborador HOY (la secuencia se reinicia cada día)
$stmtU = $pdo->prepare(
    'SELECT tipo, fecha_hora FROM marcas_asistencia
      WHERE usuario_id = ? AND DATE(fecha_hora) = CURDATE()
      ORDER BY fecha_hora DESC LIMIT 1'
);
$stmtU->execute([$usuarioId]);
$ultimaMarca = $stmtU->fetch();

// Secuencias según tipo de jornada
$secuencias = [
    'completa' => ['entrada', 'salida_comida', 'regreso_comida', 'salida'],
    'media'    => ['entrada', 'salida'],
];
$secuencia = $secuencias[$tipoJornada] ?? $secuencias['completa'];

// Calcular el próximo tipo esperado en la secuencia
if ($ultimaMarca) {
    $pos = array_search($ultimaMarca['tipo'], $secuencia);
    // Si no está en la secuencia actual o llegó al final, reinicia con entrada
    $proximoTipo = ($pos === false || $pos >= count($secuencia) - 1)
        ? $secuencia[0]
        : $secuencia[$pos + 1];
} else {
    $proximoTipo = 'entrada';
}

// Configuración visual y textual por tipo de marca
$tiposConfig = [
    'entrada'        => ['label' => 'Marcar Entrada',    'etiqueta' => 'Entrada',          'icono' => 'fa-arrow-right-to-bracket',  'clase' => 'btn-t-entrada'],
    'salida_comida'  => ['label' => 'Salida a Comer',    'etiqueta' => 'Salida a comer',   'icono' => 'fa-utensils',               'clase' => 'btn-t-comida'],
    'regreso_comida' => ['label' => 'Regreso de Comer',  'etiqueta' => 'Regreso de comer', 'icono' => 'fa-rotate-left',            'clase' => 'btn-t-regreso'],
    'salida'         => ['label' => 'Marcar Salida',     'etiqueta' => 'Salida',           'icono' => 'fa-arrow-right-from-bracket','clase' => 'btn-t-salida'],
];

$cfgActual = $tiposConfig[$proximoTipo] ?? $tiposConfig['entrada'];

/**
 * Convierte "HH:MM" o "HH:MM:SS" a minutos totales desde medianoche.
 */
function tiempoAMinutos(string $t): int
{
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}

/**
 * Haversine: true si (lat1,lon1) está dentro del radio de (lat2,lon2).
 */
function dentroDeGeocerca(float $lat1, float $lon1, float $lat2, float $lon2, int $radioMetros): bool
{
    $r    = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return ($r * 2 * atan2(sqrt($a), sqrt(1 - $a))) <= $radioMetros;
}

// Procesar POST
$errorMarca = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo           = $_POST['tipo']    ?? '';
    $latPost        = $_POST['latitud'] ?? '';
    $lonPost        = $_POST['longitud']?? '';
    $sucursalIdPost = filter_input(INPUT_POST, 'sucursal_id', FILTER_VALIDATE_INT);

    if (!array_key_exists($tipo, $tiposConfig)) {
        $errorMarca = 'Tipo de marca inválido.';
    } elseif ($tipo !== $proximoTipo) {
        // Alguien intentó enviar un tipo diferente al esperado (manipulación de form)
        $errorMarca = 'Marca fuera de secuencia. Se esperaba: ' . $tiposConfig[$proximoTipo]['etiqueta'] . '.';
    } elseif (!$sucursalIdPost) {
        $errorMarca = 'Selecciona una sucursal antes de marcar.';
    } else {
        // ── Candado Anti-Spam (Debounce) ──────────────
        if ($ultimaMarca && strtotime($ultimaMarca['fecha_hora']) > time() - 180) {
            $errorMarca = 'Anti-Spam: Acabas de realizar un marcaje hace unos instantes. Por favor, espera 3 minutos antes de volver a marcar.';
        }
        
        if ($errorMarca === '') {
        $stmtSel = $pdo->prepare('SELECT * FROM sucursales WHERE id = ? AND activo = 1 LIMIT 1');
        $stmtSel->execute([$sucursalIdPost]);
        $sucursalSel = $stmtSel->fetch();

        if (!$sucursalSel) {
            $errorMarca = 'Sucursal seleccionada no válida.';
        } else {
            $latitud   = ($latPost !== '') ? (float)$latPost : null;
            $longitud  = ($lonPost !== '') ? (float)$lonPost : null;
            $dentroGeo = null;

            if ($latitud !== null && $longitud !== null) {
                $dentroGeo = dentroDeGeocerca(
                    $latitud, $longitud,
                    (float)$sucursalSel['latitud'],
                    (float)$sucursalSel['longitud'],
                    (int)$sucursalSel['radio_geocerca_metros']
                ) ? 1 : 0;
            }

            // ── Algoritmo de Bolsa de Horas (Flexible Schedule) ──────────────
            // El balance solo se calcula al finalizar el día (cuando marca salida).
            // Positivo = Deuda de tiempo (Retardo general). Negativo = Horas extra. 0 = Perfecto.
            $minutosVariacion = null;

            if ($tipo === 'salida') {
                $rowNow    = $pdo->query('SELECT DAYOFWEEK(NOW()) - 1 AS dia')->fetch();
                $diaSemana = (int)$rowNow['dia'];

                $stmtHor = $pdo->prepare(
                    'SELECT hora_entrada, hora_salida_comida, hora_regreso_comida, hora_salida
                       FROM horarios
                      WHERE usuario_id = ? AND dia_semana = ? AND activo = 1
                      LIMIT 1'
                );
                $stmtHor->execute([$usuarioId, $diaSemana]);
                $horario = $stmtHor->fetch();

                // 1. Calcular minutos obligatorios del día
                if ($horario && $horario['hora_entrada'] && $horario['hora_salida']) {
                    $minutosRequeridos = tiempoAMinutos($horario['hora_salida']) - tiempoAMinutos($horario['hora_entrada']);
                    if ($horario['hora_salida_comida'] && $horario['hora_regreso_comida']) {
                        $minutosRequeridos -= (tiempoAMinutos($horario['hora_regreso_comida']) - tiempoAMinutos($horario['hora_salida_comida']));
                    }
                } else {
                    // Fallback a jornada estándar si no tiene horario específico asignado
                    $minutosRequeridos = ($usuario['tipo_jornada'] === 'media') ? 4 * 60 : 8 * 60;
                }

                // 2. Extraer tiempos reales del usuario hoy
                $marcasHoy = $pdo->prepare('
                    SELECT tipo, fecha_hora FROM marcas_asistencia 
                     WHERE usuario_id = ? AND DATE(fecha_hora) = CURDATE()
                     ORDER BY fecha_hora ASC
                ');
                $marcasHoy->execute([$usuarioId]);
                $mh = $marcasHoy->fetchAll();

                $tEntrada       = 0;
                $tSalidaComida  = 0;
                $tRegresoComida = 0;
                $tSalida        = time(); // La salida es ahora mismo

                foreach ($mh as $m) {
                    $tm = strtotime($m['fecha_hora']);
                    if ($m['tipo'] === 'entrada')       $tEntrada       = $tm;
                    if ($m['tipo'] === 'salida_comida') $tSalidaComida  = $tm;
                    if ($m['tipo'] === 'regreso_comida')$tRegresoComida = $tm;
                }

                // 3. Sumatoria de bloques trabajados
                $minutosTrabajados = 0;
                if ($tEntrada) {
                    if ($tSalidaComida && $tRegresoComida) {
                        $bloque1 = ($tSalidaComida - $tEntrada) / 60;
                        $bloque2 = ($tSalida - $tRegresoComida) / 60;
                        $minutosTrabajados = $bloque1 + $bloque2;
                    } else {
                        // Se saltó la comida (jornada corrida)
                        $minutosTrabajados = ($tSalida - $tEntrada) / 60;
                    }
                }

                // 4. Varianza Final
                // + = debe tiempo. - = trabajó extra.
                $minutosVariacion = (int)round($minutosRequeridos - $minutosTrabajados);
            }

            // ── Bloqueo estricto por Geocerca ──────────────
            if ($dentroGeo === 0) {
                $errorMarca = 'No estás dentro de la sucursal. Acércate a la tienda para poder marcar tu asistencia.';
            }

            if ($errorMarca === '') {
                // El timestamp lo genera el servidor (DEFAULT CURRENT_TIMESTAMP)
                $stmt = $pdo->prepare('
                    INSERT INTO marcas_asistencia
                           (usuario_id, sucursal_id, tipo, latitud, longitud, dentro_geocerca, minutos_variacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$usuarioId, $sucursalIdPost, $tipo, $latitud, $longitud, $dentroGeo, $minutosVariacion]);

                $etq = $tiposConfig[$tipo]['etiqueta'];
                $_SESSION['flash_msg']  = $etq . ' registrada correctamente a las ' . date('H:i:s') . '.';
                $_SESSION['flash_tipo'] = 'exito';
                
                header('Location: ' . APP_URL . '/vendedor/exito.php');
                exit;
            }
        }
        } // Cierra if ($errorMarca === '')
    }
}

$nombre         = htmlspecialchars($_SESSION['nombre_completo']);
$ultimaEtiqueta = $ultimaMarca
    ? ($tiposConfig[$ultimaMarca['tipo']]['etiqueta'] ?? ucfirst($ultimaMarca['tipo']))
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcar Asistencia — GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="/assets/js/face-api.min.js"></script>
    <style>
        .marca-card    { max-width: 440px; margin: 0 auto; text-align: center; }
        .reloj         { font-size: 3.2rem; font-weight: 800; color: var(--primario);
                         letter-spacing: .04em; margin: 1.25rem 0 .2rem; }
        .fecha-hoy     { color: var(--gris); font-size: .95rem; margin-bottom: 1.25rem; }
        .gps-badge     { display: inline-flex; align-items: center; gap: .4rem;
                         font-size: .82rem; padding: .3rem .9rem; border-radius: 20px;
                         margin-bottom: 1.25rem; }
        .gps-esperando { background:#fff3cd; color:#7d5a00; }
        .gps-ok        { background:#d4f4e0; color:#155724; }
        .gps-sin-gps   { background:#f8d7da; color:#721c24; }
        .btn-marca     { width:100%; padding:1.3rem; font-size:1.2rem; border-radius:14px;
                         font-weight:700; letter-spacing:.02em; border:none; cursor:pointer; margin:1rem 0; }
        .btn-t-entrada  { background:#27ae60; color:#fff; }
        .btn-t-comida   { background:#e67e22; color:#fff; }
        .btn-t-regreso  { background:#2980b9; color:#fff; }
        .btn-t-salida   { background:#c0392b; color:#fff; }
        .ultima-marca   { margin-top:.75rem; font-size:.88rem; color:var(--gris);
                          background:var(--fondo); border-radius:var(--radio-sm); padding:.65rem 1rem; }
        /* Indicador de progreso de la jornada */
        .pasos         { display:flex; justify-content:center; gap:.4rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .paso          { font-size:.75rem; padding:.25rem .65rem; border-radius:12px;
                         background:var(--borde); color:var(--gris); font-weight:500; }
        .paso.actual   { background:var(--primario); color:#fff; }
        .paso.hecho    { background:#d4f4e0; color:#155724; }
        /* Burbuja de cámara en vivo para biometría */
        #cam-container { 
            position: fixed; 
            bottom: 20px; 
            right: 20px; 
            width: 120px; 
            height: 120px; 
            border-radius: 50%; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            border: 3px solid var(--primario);
            background: #000;
            z-index: 1000;
            display: none;
        }
        #cam-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Modo espejo */
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/logout.php" class="btn-salir">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <main class="contenedor">

        <a class="volver" href="/vendedor/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <?php if ($flashMsg !== ''): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flashTipo) ?>"
                 style="max-width:440px;margin:0 auto 1rem">
                <?= htmlspecialchars($flashMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMarca !== ''): ?>
            <div class="alerta alerta-error" style="max-width:440px;margin:0 auto 1rem">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($errorMarca) ?>
            </div>
        <?php endif; ?>

        <div class="marca-card">

            <div class="reloj" id="reloj">--:--:--</div>
            <div class="fecha-hoy" id="fecha-hoy"></div>

            <!-- Barra de progreso de la jornada -->
            <div class="pasos">
                <?php
                $posProximo = array_search($proximoTipo, $secuencia);
                foreach ($secuencia as $i => $t):
                    if ($i < $posProximo)      $cls = 'paso hecho';
                    elseif ($i === $posProximo) $cls = 'paso actual';
                    else                        $cls = 'paso';
                ?>
                    <span class="<?= $cls ?>">
                        <?php if ($i < $posProximo): ?><i class="fa-solid fa-check"></i> <?php endif; ?>
                        <?= htmlspecialchars($tiposConfig[$t]['etiqueta']) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <div>
                <span class="gps-badge gps-esperando" id="gps-badge">
                    <i class="fa-solid fa-location-dot"></i> Obteniendo ubicación...
                </span>
            </div>

            <?php if (!empty($todasSucursales)): ?>
                <form method="POST" action="" id="forma-marca">
                    <input type="hidden" name="tipo"     value="<?= htmlspecialchars($proximoTipo) ?>">
                    <input type="hidden" name="latitud"  id="latitud"  value="">
                    <input type="hidden" name="longitud" id="longitud" value="">

                    <div class="form-grupo" style="text-align:left">
                        <label for="sucursal_id" style="font-size:.85rem">
                            <i class="fa-solid fa-store"></i> Sucursal donde estás ahora
                        </label>
                        <select id="sucursal_id" name="sucursal_id" required>
                            <?php foreach ($todasSucursales as $s): ?>
                                <option value="<?= $s['id'] ?>"
                                    <?= (int)$s['id'] === $sucursalIdDef ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-marca <?= htmlspecialchars($cfgActual['clase']) ?>">
                        <i class="fa-solid <?= $cfgActual['icono'] ?>"></i>
                        <?= htmlspecialchars($cfgActual['label']) ?>
                    </button>
                </form>
            <?php else: ?>
                <div class="alerta alerta-advertencia" style="text-align:left;margin-top:1rem">
                    No hay sucursales activas. Contacta al administrador.
                </div>
            <?php endif; ?>

            <div class="ultima-marca">
                <?php if ($ultimaMarca): ?>
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    Última marca: <strong><?= htmlspecialchars($ultimaEtiqueta) ?></strong>
                    &mdash; <?= date('d/m/Y H:i:s', strtotime($ultimaMarca['fecha_hora'])) ?>
                <?php else: ?>
                    <i class="fa-solid fa-circle-info"></i> Sin marcas previas registradas.
                <?php endif; ?>
            </div>

        </div>
        
        <div id="cam-container">
            <video id="video" autoplay muted playsinline></video>
        </div>
    </main>

    <script>
    // 1. Reloj en tiempo real
    (function tick() {
        const ahora   = new Date();
        const reloj   = document.getElementById('reloj');
        const fechaEl = document.getElementById('fecha-hoy');
        if (reloj) {
            reloj.textContent = ahora.toLocaleTimeString('es-GT',
                { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        }
        if (fechaEl && fechaEl.textContent === '') {
            const dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
            const meses = ['enero','febrero','marzo','abril','mayo','junio',
                           'julio','agosto','septiembre','octubre','noviembre','diciembre'];
            fechaEl.textContent = dias[ahora.getDay()] + ', ' + ahora.getDate() +
                ' de ' + meses[ahora.getMonth()] + ' de ' + ahora.getFullYear();
        }
        setTimeout(tick, 1000);
    })();

    // 2. Geolocalización
    const badge  = document.getElementById('gps-badge');
    const inpLat = document.getElementById('latitud');
    const inpLon = document.getElementById('longitud');
    const selectSucursal = document.getElementById('sucursal_id');
    const sucursalesData = <?= json_encode($todasSucursales) ?>;

    // Fórmula de Haversine para distancia en km
    function getDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; 
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    if (navigator.geolocation && inpLat) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const userLat = pos.coords.latitude;
                const userLon = pos.coords.longitude;
                inpLat.value = userLat;
                inpLon.value = userLon;
                const prec = Math.round(pos.coords.accuracy);
                badge.innerHTML = '<i class="fa-solid fa-location-dot"></i> GPS obtenido (&plusmn;' + prec + '&nbsp;m)';
                badge.className = 'gps-badge gps-ok';

                // Auto-seleccionar sucursal más cercana
                if (sucursalesData && selectSucursal) {
                    let closestId = null;
                    let minDist = Infinity;
                    sucursalesData.forEach(s => {
                        if (s.latitud && s.longitud) {
                            let dist = getDistance(userLat, userLon, parseFloat(s.latitud), parseFloat(s.longitud));
                            if (dist < minDist) {
                                minDist = dist;
                                closestId = s.id;
                            }
                        }
                    });
                    if (closestId) {
                        selectSucursal.value = closestId;
                    }
                }
            },
            function() {
                badge.innerHTML = '<i class="fa-solid fa-location-slash"></i> GPS no disponible — se marcará sin coordenadas';
                badge.className = 'gps-badge gps-sin-gps';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else if (badge) {
        badge.innerHTML = '<i class="fa-solid fa-location-slash"></i> GPS no soportado';
        badge.className = 'gps-badge gps-sin-gps';
    }

    // 3. Validación Biométrica (face-api.js)
    const form = document.getElementById('forma-marca');
    const video = document.getElementById('video');
    const descriptorRegistradoJson = <?= $descriptorBiometrico ? json_encode($descriptorBiometrico) : 'null' ?>;
    let modelosCargados = false;

    if (descriptorRegistradoJson) {
        // Cargar modelos y prender cámara
        Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/assets/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/assets/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/assets/models')
        ]).then(() => {
            modelosCargados = true;
            navigator.mediaDevices.getUserMedia({ video: {} }).then(stream => {
                video.srcObject = stream;
                document.getElementById('cam-container').style.display = 'block';
            }).catch(err => console.error("Error activando cámara para biometría", err));
        }).catch(err => console.error("Error cargando modelos face-api", err));
    }

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');

            if (!descriptorRegistradoJson) {
                Swal.fire('Atención', 'No tienes un rostro registrado en el sistema. Contacta al administrador.', 'warning');
                return;
            }

            if (!modelosCargados || !video.srcObject) {
                Swal.fire('Espera', 'La cámara o la Inteligencia Artificial aún se están inicializando. Intenta en unos segundos.', 'info');
                return;
            }

            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Validando Rostro...';
            btn.disabled = true;

            try {
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                                               .withFaceLandmarks()
                                               .withFaceDescriptor();
                
                if (!detection) {
                    Swal.fire('Intento de Suplantación', 'No se detectó un rostro claro en la cámara. Mírala fijamente.', 'error');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                    return; 
                }

                const descriptorDBArray = JSON.parse(descriptorRegistradoJson);
                const descriptorDB = new Float32Array(Object.values(descriptorDBArray));
                const distance = faceapi.euclideanDistance(detection.descriptor, descriptorDB);
                
                if (distance < 0.55) {
                    form.submit();
                } else {
                    Swal.fire('Intento de Suplantación', 'El rostro detectado NO coincide con el tuyo.', 'error');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch(err) {
                Swal.fire('Error', 'Hubo un fallo al procesar la biometría.', 'error');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        });
    }
    </script>
</body>
</html>
