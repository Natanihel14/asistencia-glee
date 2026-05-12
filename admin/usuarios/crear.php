<?php
/**
 * admin/usuarios/crear.php — Formulario para dar de alta un nuevo usuario.
 */
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
requerirRol(['administrador', 'supervisor']);

$pdo = conectarBD();

$sucursales = $pdo->query(
    'SELECT id, nombre FROM sucursales WHERE activo = 1 ORDER BY departamento, nombre'
)->fetchAll();

$errores = [];
$d = [
    'nombre_completo' => '',
    'correo'          => '',
    'rol'             => 'vendedor',
    'tipo_jornada'    => 'completa',
    'sucursal_id'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['nombre_completo'] = trim($_POST['nombre_completo'] ?? '');
    $d['correo']          = trim($_POST['correo']          ?? '');
    $d['rol']             = $_POST['rol']                  ?? '';
    $d['tipo_jornada']    = in_array($_POST['tipo_jornada'] ?? '', ['completa','media'], true)
                            ? $_POST['tipo_jornada'] : 'completa';
    $d['sucursal_id']     = $_POST['sucursal_id']          ?? '';
    $pass                 = $_POST['password']             ?? '';

    if ($d['nombre_completo'] === '') {
        $errores[] = 'El nombre completo es obligatorio.';
    }
    if ($d['correo'] === '' || !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo electrónico válido.';
    }
    if (strlen($pass) < 6) {
        $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    }
    if (!in_array($d['rol'], ['administrador', 'supervisor', 'vendedor', 'bodega'], true)) {
        $errores[] = 'Selecciona un rol válido.';
    }

    if (empty($errores)) {
        $chk = $pdo->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
        $chk->execute([$d['correo']]);
        if ($chk->fetch()) {
            $errores[] = 'Ese correo electrónico ya está registrado en el sistema.';
        }
    }

    if (empty($errores)) {
        $sucursalId = ($d['sucursal_id'] !== '') ? (int)$d['sucursal_id'] : null;

        $stmt = $pdo->prepare('
            INSERT INTO usuarios (nombre_completo, correo, password_hash, rol, tipo_jornada, sucursal_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $d['nombre_completo'],
            $d['correo'],
            password_hash($pass, PASSWORD_DEFAULT),
            $d['rol'],
            $d['tipo_jornada'],
            $sucursalId,
        ]);

        $_SESSION['flash_msg']  = 'Usuario "' . $d['nombre_completo'] . '" creado correctamente.';
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
    <title>Nuevo Usuario — GLEE</title>
    <link rel="stylesheet" href="/asistencia-glee/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

    <header class="topbar">
        <a class="topbar-marca" href="<?= urlDashboard() ?>">GLEE</a>
        <div class="topbar-usuario">
            <span><?= $nombre ?></span>
            <a href="/asistencia-glee/logout.php" class="btn-salir">
                <i class="fa-solid fa-right-from-bracket"></i> Salir
            </a>
        </div>
    </header>

    <main class="contenedor">

        <a class="volver" href="index.php">
            <i class="fa-solid fa-arrow-left"></i> Volver al listado
        </a>

        <div class="seccion-header">
            <h2><i class="fa-solid fa-user-plus"></i> Nuevo Usuario</h2>
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

            <form method="POST" action="" novalidate>

                <div class="form-grupo">
                    <label for="nombre_completo">Nombre completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo"
                           value="<?= htmlspecialchars($d['nombre_completo']) ?>"
                           placeholder="Ej. María García López" maxlength="120" required>
                </div>

                <div class="form-grupo">
                    <label for="correo">Correo electrónico *</label>
                    <input type="email" id="correo" name="correo"
                           value="<?= htmlspecialchars($d['correo']) ?>"
                           placeholder="usuario@glee.com" required>
                </div>

                <div class="form-grupo">
                    <label for="password">
                        Contraseña *
                        <small style="color:var(--gris)">(mínimo 6 caracteres)</small>
                    </label>
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
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

                <div style="display:flex;gap:.75rem;margin-top:1.25rem">
                    <button type="submit" class="btn btn-primario">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Usuario
                    </button>
                    <a href="index.php" class="btn btn-gris">Cancelar</a>
                </div>

            </form>
        </div>

    </main>
</body>
</html>
