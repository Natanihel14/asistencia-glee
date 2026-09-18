<?php
/**
 * admin/usuarios/index.php — Listado de usuarios con acciones editar/eliminar.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

// Mensaje flash proveniente de crear/editar/eliminar
$flashMsg  = $_SESSION['flash_msg']  ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? 'exito';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

// ── Filtros GET ──────────────────────────────────────────────
$filtroActivo   = $_GET['activo']      ?? '1';   // '1' = solo activos por defecto
$filtroRol      = $_GET['rol']         ?? '';
$filtroSucursal = $_GET['sucursal_id'] ?? '';

// Sanitizar valores para evitar valores arbitrarios
if (!in_array($filtroActivo, ['', '0', '1'], true)) $filtroActivo = '1';
if (!in_array($filtroRol, ['', 'administrador', 'supervisor', 'vendedor', 'bodega'], true)) $filtroRol = '';
$filtroSucursal = (is_numeric($filtroSucursal) && $filtroSucursal !== '') ? (int)$filtroSucursal : '';

// ── Consulta dinámica ─────────────────────────────────────────
$sql    = '
    SELECT u.id, u.nombre_completo, u.username, u.rol, u.tipo_jornada, u.activo,
           COALESCE(s.nombre, "—") AS sucursal_nombre
      FROM usuarios u
      LEFT JOIN sucursales s ON u.sucursal_id = s.id
     WHERE 1=1
';
$params = [];

if ($filtroActivo !== '') {
    $sql    .= ' AND u.activo = ?';
    $params[] = (int)$filtroActivo;
}
if ($filtroRol !== '') {
    $sql    .= ' AND u.rol = ?';
    $params[] = $filtroRol;
}
if ($filtroSucursal !== '') {
    $sql    .= ' AND u.sucursal_id = ?';
    $params[] = $filtroSucursal;
}

$sql .= ' ORDER BY u.id ASC';

$stmtU = $pdo->prepare($sql);
$stmtU->execute($params);
$usuarios = $stmtU->fetchAll();

// Sucursales activas para el selector de filtro
$sucursalesFiltro = $pdo->query(
    'SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY id ASC'
)->fetchAll();

// ¿Hay algún filtro distinto del estado por defecto?
$hayFiltro = ($filtroActivo !== '1' || $filtroRol !== '' || $filtroSucursal !== '');

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios — GLEE</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        <div class="seccion-header">
            <h2><i class="fa-solid fa-users"></i> Gestión de Usuarios</h2>
            <a href="crear.php" class="btn btn-exito">
                <i class="fa-solid fa-plus"></i> Nuevo Usuario
            </a>
        </div>

        <!-- Panel de filtros -->
        <div class="tarjeta" style="margin-bottom:1rem;padding:1rem 1.25rem">
            <form method="GET" action="" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">

                <div class="form-grupo" style="margin:0;flex:1;min-width:140px">
                    <label for="f-activo">Estado</label>
                    <select id="f-activo" name="activo">
                        <option value=""  <?= $filtroActivo === ''  ? 'selected' : '' ?>>Todos</option>
                        <option value="1" <?= $filtroActivo === '1' ? 'selected' : '' ?>>Solo activos</option>
                        <option value="0" <?= $filtroActivo === '0' ? 'selected' : '' ?>>Solo inactivos</option>
                    </select>
                </div>

                <div class="form-grupo" style="margin:0;flex:1;min-width:150px">
                    <label for="f-rol">Rol</label>
                    <select id="f-rol" name="rol">
                        <option value="" <?= $filtroRol === '' ? 'selected' : '' ?>>Todos los roles</option>
                        <option value="administrador" <?= $filtroRol === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                        <option value="supervisor"    <?= $filtroRol === 'supervisor'    ? 'selected' : '' ?>>Supervisor</option>
                        <option value="vendedor"      <?= $filtroRol === 'vendedor'      ? 'selected' : '' ?>>Vendedor</option>
                        <option value="bodega"        <?= $filtroRol === 'bodega'        ? 'selected' : '' ?>>Bodega</option>
                    </select>
                </div>

                <div class="form-grupo" style="margin:0;flex:1;min-width:160px">
                    <label for="f-sucursal">Sucursal</label>
                    <select id="f-sucursal" name="sucursal_id">
                        <option value="" <?= $filtroSucursal === '' ? 'selected' : '' ?>>Todas las sucursales</option>
                        <?php foreach ($sucursalesFiltro as $sf): ?>
                            <option value="<?= $sf['id'] ?>"
                                <?= (string)$filtroSucursal === (string)$sf['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sf['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:.5rem;align-items:flex-end;padding-bottom:0">
                    <button type="submit" class="btn btn-primario btn-sm">
                        <i class="fa-solid fa-magnifying-glass"></i> Filtrar
                    </button>
                    <?php if ($hayFiltro): ?>
                        <a href="?" class="btn btn-gris btn-sm">Limpiar</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <div class="tarjeta">
            <?php if (empty($usuarios)): ?>
                <p style="color:var(--gris);text-align:center;padding:2rem 0">
                    No hay usuarios registrados aún.
                </p>
            <?php else: ?>
                <div class="tabla-contenedor">
                    <div class="table-responsive"><table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre completo</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Jornada</th>
                                <th>Sucursal</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['nombre_completo']) ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $u['rol'] ?>">
                                        <?= ucfirst($u['rol']) ?>
                                    </span>
                                </td>
                                <td><?= ucfirst($u['tipo_jornada'] ?? 'completa') ?></td>
                                <td><?= htmlspecialchars($u['sucursal_nombre']) ?></td>
                                <td>
                                    <?php if ($u['activo']): ?>
                                        <span style="color:#155724; font-weight:600;">Activo</span>
                                    <?php else: ?>
                                        <span style="color:#721c24; font-weight:600;">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="acciones" style="flex-direction: column; gap: 0.25rem; align-items: flex-start;">
                                    <a href="editar.php?id=<?= $u['id'] ?>"
                                       class="btn btn-acento btn-sm" style="width: 105px; text-align: center;">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </a>
                                    <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                                        <?php if ($u['activo']): ?>
                                            <button type="button" class="btn btn-peligro btn-sm"
                                                    style="width: 105px; text-align: center;"
                                                    onclick="abrirModalDesactivar(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nombre_completo'])) ?>')">
                                                <i class="fa-solid fa-user-slash"></i> Desactivar
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:.8rem;color:var(--gris);padding:.35rem .5rem; width: 105px; text-align: center; display: inline-block;">
                                                <i class="fa-solid fa-user-slash"></i> Inactivo
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="font-size:.8rem;color:var(--gris);padding:.35rem .5rem; width: 105px; text-align: center; display: inline-block;">(tú)</span>
                                    <?php endif; ?>
                                </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Form compartido — lo dispara el modal, no cada fila -->
    <form id="forma-desactivar" method="POST" action="eliminar.php">
        <input type="hidden" id="modal-usuario-id" name="id" value="">
    </form>

    <!-- Modal de confirmación de desactivación -->
    <div id="modal-backdrop" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-titulo" hidden>
        <div class="modal-caja">
            <div class="modal-icono">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <h3 id="modal-titulo" class="modal-titulo">¿Desactivar usuario?</h3>
            <p class="modal-nombre" id="modal-nombre-usuario"></p>
            <p class="modal-texto">El usuario no podrá ingresar al sistema pero su historial de asistencia se conserva.</p>
            <div class="modal-acciones">
                <button type="button" class="btn btn-gris" onclick="cerrarModal()">
                    Cancelar
                </button>
                <button type="button" class="btn btn-peligro" onclick="confirmarDesactivar()">
                    <i class="fa-solid fa-user-slash"></i> Desactivar
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
            color: var(--peligro, #c0392b);
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
    const inputId  = document.getElementById('modal-usuario-id');
    const lblNombre = document.getElementById('modal-nombre-usuario');

    function abrirModalDesactivar(id, nombre) {
        inputId.value   = id;
        lblNombre.textContent = nombre;
        backdrop.hidden = false;
        document.getElementById('modal-backdrop')
            .querySelector('.btn-peligro').focus();
    }

    function cerrarModal() {
        backdrop.hidden = true;
    }

    function confirmarDesactivar() {
        document.getElementById('forma-desactivar').submit();
    }

    // Cierra al hacer clic en el fondo oscuro (fuera de la caja)
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) cerrarModal();
    });

    // Cierra con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !backdrop.hidden) cerrarModal();
    });
    </script>

</body>
</html>

