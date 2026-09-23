<?php
/**
 * admin/usuarios/editar.php — Modificar un usuario existente.
 * La contraseña es opcional: vacía = no se cambia.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador']);

$pdo = conectarBD();

$id = filter_input(INPUT_GET,  'id', FILTER_VALIDATE_INT)
   ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

$stmtU = $pdo->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
$stmtU->execute([$id]);
$usuario = $stmtU->fetch();

if (!$usuario) {
    $_SESSION['flash_msg']  = 'Usuario no encontrado.';
    $_SESSION['flash_tipo'] = 'error';
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

$sucursales = $pdo->query(
    'SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY departamento, nombre'
)->fetchAll();

$errores = [];
$d = [
    'nombre_completo' => $usuario['nombre_completo'],
    'username'          => $usuario['username'],
    'rol'             => $usuario['rol'],
    'tipo_jornada'    => $usuario['tipo_jornada'] ?? 'completa',
    'sucursal_id'     => $usuario['sucursal_id'] ?? '',
    'activo'          => $usuario['activo'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
    $d['username']          = trim($_POST['username']          ?? '');
    $d['rol']             = $_POST['rol']                  ?? '';
    $d['tipo_jornada']    = in_array($_POST['tipo_jornada'] ?? '', ['completa','media'], true)
                            ? $_POST['tipo_jornada'] : 'completa';
    $d['sucursal_id']     = $_POST['sucursal_id']          ?? '';
    $d['activo']          = (int)($_POST['activo']         ?? 1);
    $d['password']        = $_POST['password']             ?? '';

    if ($d['nombre_completo'] === '') {
        $errores[] = 'El nombre completo es obligatorio.';
    }
    if ($d['username'] === '' || preg_match("/\s/", $d['username'])) {
        $errores[] = 'El usuario no puede contener espacios y es obligatorio.';
    }

    // Si se escribió un password, validarlo
    if ($d['password'] !== '' && strlen($d['password']) < 6) {
        $errores[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
    }
    if (!in_array($d['rol'], ['administrador', 'supervisor', 'vendedor', 'bodega'], true)) {
        $errores[] = 'Selecciona un rol válido.';
    }

    if (empty($errores)) {
        // Verificar que el correo no lo tenga OTRO usuario
        $chk = $pdo->prepare('SELECT 1 FROM usuarios WHERE username = ? AND id != ?');
        $chk->execute([$d['username'], $id]);
        if ($chk->fetch()) {
            $errores[] = 'Ese usuario ya está registrado por otra persona.';
        }
    }

    if (empty($errores)) {
        $sucursalId = ($d['sucursal_id'] !== '') ? (int)$d['sucursal_id'] : null;

        if ($d['password'] !== '') {
            $stmt = $pdo->prepare('
                UPDATE usuarios
                   SET nombre_completo = ?, username = ?, password_hash = ?,
                       rol = ?, tipo_jornada = ?, sucursal_id = ?, activo = ?
                 WHERE id = ?
            ');
            $stmt->execute([
                $d['nombre_completo'], $d['username'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['rol'], $d['tipo_jornada'], $sucursalId, $d['activo'], $id,
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE usuarios
                   SET nombre_completo = ?, username = ?,
                       rol = ?, tipo_jornada = ?, sucursal_id = ?, activo = ?
                 WHERE id = ?
            ');
            $stmt->execute([
                $d['nombre_completo'], $d['username'],
                $d['rol'], $d['tipo_jornada'], $sucursalId, $d['activo'], $id,
            ]);
        }

        $_SESSION['flash_msg']  = 'Usuario "' . $d['nombre_completo'] . '" actualizado correctamente.';
        $_SESSION['flash_tipo'] = 'exito';
        header('Location: ' . APP_URL . '/admin/usuarios/index.php');
        exit;
    }
}

$nombre = htmlspecialchars($_SESSION['nombre_completo']);
$roles  = ['administrador' => 'Administrador', 'supervisor' => 'Supervisor',
           'vendedor' => 'Vendedor', 'bodega' => 'Bodega'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario — GLEE</title>
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

        <a class="volver" href="index.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al listado
        </a>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-user-pen"></i> Editar Usuario</h2>
        </div>

        <div class="tarjeta" style="max-width:560px">

            <?php if (!empty($errores)): ?>
                <div class="alerta alerta-error">
                    <strong><i class="fa-solid fa-circle-exclamation"></i> Corrige los siguientes errores:</strong>
                    <ul style="margin:.5rem 0 0 1.2rem">
                        <?php foreach ($errores as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="editar.php?id=<?= $id ?>" novalidate>
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="form-grupo">
                    <label for="nombre_completo">Nombre completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo"
                           value="<?= htmlspecialchars($d['nombre_completo']) ?>"
                           maxlength="120" required>
                </div>

                <div class="form-grupo">
                    <label for="username">Usuario *</label>
                    <input type="text" id="username" name="username"
                           value="<?= htmlspecialchars($d['username']) ?>" required>
                </div>

                <div class="form-grupo">
                    <label for="password">
                        Nueva contraseña
                        <small style="color:var(--gris)">(dejar en blanco para mantener la actual)</small>
                    </label>
                    <input type="password" id="password" name="password" placeholder="••••••••">
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:.75rem">
                    <div class="form-grupo" style="margin:0">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" required>
                            <?php foreach ($roles as $valor => $etiqueta): ?>
                                <option value="<?= $valor ?>"
                                    <?= $d['rol'] === $valor ? 'selected' : '' ?>>
                                    <?= $etiqueta ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo" style="margin:0">
                        <label for="tipo_jornada">Jornada *</label>
                        <select id="tipo_jornada" name="tipo_jornada">
                            <option value="completa"
                                <?= $d['tipo_jornada'] === 'completa' ? 'selected' : '' ?>>
                                Completa (4 marcas)
                            </option>
                            <option value="media"
                                <?= $d['tipo_jornada'] === 'media' ? 'selected' : '' ?>>
                                Media (2 marcas)
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-grupo" style="margin-top:1rem">
                    <label for="sucursal_id">Sucursal asignada</label>
                    <select id="sucursal_id" name="sucursal_id">
                        <option value="">— Sin sucursal asignada —</option>
                        <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id'] ?>"
                                <?= (string)$d['sucursal_id'] === (string)$s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grupo">
                    <label for="activo">Estado</label>
                    <select id="activo" name="activo">
                        <option value="1" <?= (int)$d['activo'] === 1 ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= (int)$d['activo'] === 0 ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>

                <div style="display:flex;gap:.75rem;margin-top:1.25rem">
                    <button type="submit" class="btn btn-primario">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                    <a href="index.php" class="btn btn-gris">Cancelar</a>
                </div>

            </form>
        </div>

    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rolSelect = document.getElementById('rol');
            const jornadaContainer = document.getElementById('tipo_jornada').closest('.form-grupo');

            function toggleCampos() {
                const isManagement = ['administrador', 'supervisor'].includes(rolSelect.value);
                jornadaContainer.style.display = isManagement ? 'none' : 'block';
            }

            rolSelect.addEventListener('change', toggleCampos);
            toggleCampos(); // run on load
        });
    </script>
</body>
</html>

