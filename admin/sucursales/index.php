<?php
/**
 * admin/sucursales/index.php — Listado de sucursales con opción de editar.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

$pdo = conectarBD();

$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

$sucursales = $pdo->query('
    SELECT s.id, s.nombre, s.departamento, s.latitud, s.longitud,
           s.radio_geocerca_metros, s.activo,
           COUNT(u.id) AS total_usuarios
      FROM sucursales s
      LEFT JOIN usuarios u ON u.sucursal_id = s.id AND u.activo = 1
     GROUP BY s.id
     ORDER BY s.id ASC
')->fetchAll();

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sucursales — GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .contenedor { max-width: 1100px !important; }
    </style>
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

        <a class="volver" href="/admin/dashboard.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al panel
        </a>

        <?php if ($flashMsg !== ''): ?>
            <div class="alerta alerta-<?= htmlspecialchars($flashTipo) ?>">
                <i class="fa-solid fa-circle-info"></i>
                <?= htmlspecialchars($flashMsg) ?>
            </div>
        <?php endif; ?>

        <div class="seccion-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2><i class="fa-solid fa-store"></i> Sucursales GLEE</h2>
            <a href="crear.php" class="btn btn-primario"><i class="fa-solid fa-plus"></i> Nueva Sucursal</a>
        </div>

        <div class="tarjeta">
            <div class="tabla-contenedor">
                <div class="table-responsive"><table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sucursal</th>
                            <th>Departamento</th>
                            <th>Geocerca</th>
                            <th>Colaboradores activos</th>
                            <th>Coordenadas GPS</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sucursales as $s): ?>
                        <tr>
                            <td><?= $s['id'] ?></td>
                            <td><strong><?= htmlspecialchars($s['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($s['departamento']) ?></td>
                            <td><?= $s['radio_geocerca_metros'] ?> m</td>
                            <td style="text-align:center"><?= $s['total_usuarios'] ?></td>
                            <td style="font-size:.82rem;color:var(--gris)">
                                <?= $s['latitud'] ?>, <?= $s['longitud'] ?>
                            </td>
                            <td>
                                <?php if ($s['activo']): ?>
                                    <span class="badge" style="background:#d4f4e0;color:#155724">Activa</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f8d7da;color:#721c24">Inactiva</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:0.5rem; white-space:nowrap;">
                                    <a href="editar.php?id=<?= $s['id'] ?>" class="btn btn-acento btn-sm">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </a>
                                    
                                    <?php if ($s['activo']): ?>
                                        <button type="button" class="btn btn-peligro btn-sm" onclick="abrirModalToggle(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nombre'])) ?>', 0)"><i class="fa-solid fa-ban"></i> Desactivar</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primario btn-sm" style="background:#1d6f42; border-color:#1d6f42;" onclick="abrirModalToggle(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['nombre'])) ?>', 1)"><i class="fa-solid fa-check"></i> Activar</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div>

        </div>

    </main>
    <!-- Form compartido -->
    <form id="forma-toggle" method="POST" action="eliminar.php">
        <input type="hidden" id="modal-sucursal-id" name="id" value="">
    </form>

    <!-- Modal de confirmación -->
    <div id="modal-backdrop" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-titulo" hidden>
        <div class="modal-caja">
            <div class="modal-icono" id="modal-icon-container">
                <i class="fa-solid fa-ban" id="modal-icon"></i>
            </div>
            <h3 id="modal-titulo" class="modal-titulo">¿Desactivar sucursal?</h3>
            <p class="modal-nombre" id="modal-nombre-sucursal"></p>
            <p class="modal-texto" id="modal-texto-sucursal">Los empleados no podrán registrar asistencia aquí.</p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-gris" onclick="cerrarModal()">
                    Cancelar
                </button>
                <button type="button" id="btn-confirmar" class="btn btn-peligro" onclick="confirmarToggle()">
                    <i class="fa-solid fa-ban"></i> Desactivar
                </button>
            </div>
        </div>
    </div>

    <style>
        .modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            display: flex; align-items: center; justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }
        .modal-backdrop[hidden] { display: none; }
        .modal-caja {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0,0,0,.18);
            padding: 2rem 2rem 1.5rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
            animation: modalEntrar .18s ease;
        }
        @keyframes modalEntrar {
            from { transform: scale(.93); opacity: 0; }
            to   { transform: scale(1);  opacity: 1; }
        }
        .modal-icono {
            font-size: 2.2rem;
            margin-bottom: .75rem;
        }
        .modal-titulo {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--texto, #1a1a2e);
            margin: 0 0 .4rem;
        }
        .modal-nombre {
            font-weight: 600;
            font-size: 1rem;
            color: var(--primario, #1e3a5f);
            margin: 0 0 .6rem;
        }
        .modal-texto {
            font-size: .9rem;
            color: var(--gris, #6b7280);
            margin: 0 0 1.5rem;
            line-height: 1.5;
        }
        .modal-acciones {
            display: flex;
            gap: .75rem;
            justify-content: center;
        }
        .modal-acciones .btn { min-width: 120px; }
    </style>

    <script>
    const backdrop = document.getElementById('modal-backdrop');
    const inputId  = document.getElementById('modal-sucursal-id');
    const lblNombre = document.getElementById('modal-nombre-sucursal');
    const lblTitulo = document.getElementById('modal-titulo');
    const lblTexto = document.getElementById('modal-texto-sucursal');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const iconContainer = document.getElementById('modal-icon-container');

    function abrirModalToggle(id, nombre, nuevoEstado) {
        inputId.value = id;
        lblNombre.textContent = nombre;
        
        if (nuevoEstado === 1) {
            lblTitulo.textContent = '¿Activar sucursal?';
            lblTexto.textContent = 'Los empleados podrán registrar su asistencia en esta ubicación nuevamente.';
            btnConfirmar.className = 'btn btn-primario';
            btnConfirmar.style.backgroundColor = '#1d6f42';
            btnConfirmar.style.borderColor = '#1d6f42';
            btnConfirmar.innerHTML = '<i class="fa-solid fa-check"></i> Activar';
            iconContainer.style.color = '#1d6f42';
            iconContainer.innerHTML = '<i class="fa-solid fa-store"></i>';
        } else {
            lblTitulo.textContent = '¿Desactivar sucursal?';
            lblTexto.textContent = 'Los empleados ya no podrán registrar asistencia aquí, pero el historial se conserva.';
            btnConfirmar.className = 'btn btn-peligro';
            btnConfirmar.style.backgroundColor = '';
            btnConfirmar.style.borderColor = '';
            btnConfirmar.innerHTML = '<i class="fa-solid fa-ban"></i> Desactivar';
            iconContainer.style.color = 'var(--peligro, #c0392b)';
            iconContainer.innerHTML = '<i class="fa-solid fa-store-slash"></i>';
        }
        
        backdrop.hidden = false;
        btnConfirmar.focus();
    }

    function cerrarModal() {
        backdrop.hidden = true;
    }

    function confirmarToggle() {
        document.getElementById('forma-toggle').submit();
    }

    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) cerrarModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !backdrop.hidden) cerrarModal();
    });
    </script>
</body>
</html>
