<?php
/**
 * admin/registro_facial.php — Módulo para enrolar rostros de colaboradores.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

// Procesar el POST del JSON biométrico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_rostro') {
    $usuario_id = filter_input(INPUT_POST, 'usuario_id', FILTER_VALIDATE_INT);
    $descriptor = $_POST['descriptor'] ?? ''; // array de 128 floats en JSON
    
    if (!$usuario_id || empty($descriptor)) {
        die(json_encode(['status' => 'error', 'msg' => 'Faltan datos obligatorios.']));
    }

    // Upsert (Insertar o actualizar) el descriptor en la BD
    $stmt = $pdo->prepare('
        INSERT INTO descriptores_faciales (usuario_id, descriptor) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE descriptor = VALUES(descriptor), created_at = CURRENT_TIMESTAMP
    ');
    
    if ($stmt->execute([$usuario_id, $descriptor])) {
        echo json_encode(['status' => 'success', 'msg' => 'Rostro registrado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Error al guardar en base de datos.']);
    }
    exit;
}

// Obtener lista de usuarios para el select (solo Vendedores y Bodega)
$stmt = $pdo->query('
    SELECT u.id, u.nombre_completo, d.id as tiene_rostro 
    FROM usuarios u
    LEFT JOIN descriptores_faciales d ON u.id = d.usuario_id
    WHERE u.rol IN ("vendedor", "bodega") AND u.activo = 1
    ORDER BY u.nombre_completo
');
$usuarios = $stmt->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Facial Biométrico — Admin GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="/assets/js/face-api.min.js"></script>
    <style>
        .cam-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            background: #000;
        }
        video {
            width: 100%;
            height: auto;
            display: block;
        }
        canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .controls {
            margin-top: 1rem;
            text-align: center;
        }
        .status-badge {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .status-loading { background: #fff3cd; color: #856404; }
        .status-ready { background: #d4edda; color: #155724; }
        .form-grupo select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--borde);
            border-radius: 8px;
            font-size: 1rem;
            background: #fff;
            color: var(--texto);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23131313%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem top 50%;
            background-size: 0.65rem auto;
        }
        .form-grupo select:focus {
            outline: none;
            border-color: var(--primario);
            box-shadow: 0 0 0 3px rgba(44,62,80,0.1);
        }
        /* Select2 Custom Styles */
        .select2-container { width: 100% !important; box-sizing: border-box; } .select2-container--default .select2-selection--single {
            height: 48px;
            border-radius: 8px;
            border: 1px solid var(--borde);
            display: flex;
            align-items: center;
            font-size: 1rem;
        }
        .select2-container { width: 100% !important; box-sizing: border-box; } .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="/admin/dashboard.php">GLEE Admin</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/logout.php" class="btn-salir">Salir</a>
        </div>
    </header>

    <main class="contenedor">
        <a class="volver" href="/admin/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>
        
        <h2>Registro Biométrico de Personal</h2>
        <p>Selecciona un colaborador y escanea su rostro para enrolarlo en el sistema de seguridad.</p>

        <div class="form-grupo">
            <label for="usuario_select">Seleccionar Colaborador:</label>
            <select id="usuario_select">
                <option value="">-- Selecciona un colaborador --</option>
                <?php foreach($usuarios as $u): ?>
                    <option value="<?= $u['id'] ?>">
                        <?= htmlspecialchars($u['nombre_completo']) ?> 
                        <?= $u['tiene_rostro'] ? '(Rostro guardado)' : '(Falta registro)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="status" class="status-badge status-loading">Cargando modelos de IA...</div>

        <div class="cam-container" id="cam-container" style="display:none;">
            <video id="video" autoplay muted playsinline></video>
        </div>

        <div class="controls" id="controls" style="display:none;">
            <button id="btn-capturar" class="btn btn-primario" style="font-size:1.2rem; padding: 1rem 2rem;">
                <i class="fa-solid fa-camera-retro"></i> Capturar y Registrar Rostro
            </button>
        </div>

    </main>

    <script>
        const video = document.getElementById('video');
        const status = document.getElementById('status');
        const container = document.getElementById('cam-container');
        const controls = document.getElementById('controls');
        const btnCapturar = document.getElementById('btn-capturar');
        const selectUsuario = document.getElementById('usuario_select');

        $(document).ready(function() {
            $('#usuario_select').select2({
                placeholder: "-- Buscar colaborador --",
                allowClear: true,
                width: '100%'
            });
        });

        // Cargar modelos de face-api.js
        Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/assets/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/assets/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/assets/models')
        ]).then(startVideo).catch(err => {
            status.textContent = "Error cargando modelos de IA.";
            console.error(err);
        });

        function startVideo() {
            navigator.mediaDevices.getUserMedia({ video: {} })
                .then(stream => {
                    video.srcObject = stream;
                    status.textContent = "Modelos cargados. Cámara lista.";
                    status.className = "status-badge status-ready";
                    container.style.display = "block";
                    controls.style.display = "block";
                })
                .catch(err => {
                    status.textContent = "No se pudo acceder a la cámara.";
                    console.error(err);
                });
        }

        // Dibujar detecciones en tiempo real
        video.addEventListener('play', () => {
            const canvas = faceapi.createCanvasFromMedia(video);
            container.append(canvas);
            const displaySize = { width: video.clientWidth, height: video.clientHeight };
            faceapi.matchDimensions(canvas, displaySize);

            setInterval(async () => {
                const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks();
                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
                faceapi.draw.drawDetections(canvas, resizedDetections);
                faceapi.draw.drawFaceLandmarks(canvas, resizedDetections);
            }, 100);
        });

        btnCapturar.addEventListener('click', async () => {
            const usuarioId = selectUsuario.value;
            if (!usuarioId) {
                Swal.fire('Atención', 'Debes seleccionar un colaborador primero.', 'warning');
                return;
            }

            btnCapturar.disabled = true;
            btnCapturar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analizando rostro...';

            // Detectar rostro y extraer descriptor
            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                                           .withFaceLandmarks()
                                           .withFaceDescriptor();

            if (!detection) {
                Swal.fire({
                    icon: 'error',
                    title: 'Rostro no detectado',
                    text: 'Asegúrate de mirar fijamente a la cámara con buena iluminación.'
                });
                btnCapturar.disabled = false;
                btnCapturar.innerHTML = '<i class="fa-solid fa-camera-retro"></i> Capturar y Registrar Rostro';
                return;
            }

            // Convertir el descriptor (Float32Array) a un array normal de JS para poder mandarlo como JSON
            const descriptorArray = Array.from(detection.descriptor);
            
            // Enviar por AJAX al servidor
            const formData = new URLSearchParams();
            formData.append('action', 'guardar_rostro');
            formData.append('usuario_id', usuarioId);
            formData.append('descriptor', JSON.stringify(descriptorArray));

            fetch('registro_facial.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'El rostro ha sido encriptado y guardado en la base de datos.',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.msg, 'error');
                    btnCapturar.disabled = false;
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                console.error(err);
                btnCapturar.disabled = false;
            });
        });
    </script>
</body>
</html>

