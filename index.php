<?php
/**
 * index.php — Página de inicio de sesión.
 * Valida credenciales contra la BD con sentencias preparadas (PDO).
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya tiene sesión activa, redirigir al panel que le corresponde
if (!empty($_SESSION['usuario_id'])) {
    $esAdmin = in_array($_SESSION['rol'], ['administrador', 'supervisor'], true);
    header('Location: ' . ($esAdmin ? '/asistencia-glee/admin/dashboard.php' : '/asistencia-glee/vendedor/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = trim(filter_input(INPUT_POST, 'correo',   FILTER_SANITIZE_EMAIL) ?? '');
    $password = $_POST['password'] ?? '';

    if ($correo === '' || $password === '') {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } else {
        $pdo  = conectarBD();
        $stmt = $pdo->prepare(
            'SELECT id, nombre_completo, password_hash, rol, sucursal_id
               FROM usuarios
              WHERE correo = ? AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Regenerar ID de sesión para prevenir session fixation
            session_regenerate_id(true);
            $_SESSION['usuario_id']      = $usuario['id'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            $_SESSION['rol']             = $usuario['rol'];
            $_SESSION['sucursal_id']     = $usuario['sucursal_id'];

            $esAdmin = in_array($usuario['rol'], ['administrador', 'supervisor'], true);
            header('Location: /asistencia-glee/' . ($esAdmin ? 'admin/dashboard.php' : 'vendedor/dashboard.php'));
            exit;
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

$correoPrevio = htmlspecialchars($_POST['correo'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLEE — Iniciar Sesión</title>
    <link rel="stylesheet" href="/asistencia-glee/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">

    <div class="login-card">
        <div class="login-logo">
            <h1>GLEE</h1>
            <p>Sistema de Control de Asistencia</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alerta alerta-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-grupo">
                <label for="correo">Correo electrónico</label>
                <input
                    type="email"
                    id="correo"
                    name="correo"
                    value="<?= $correoPrevio ?>"
                    placeholder="usuario@glee.com"
                    required
                    autofocus
                >
            </div>

            <div class="form-grupo">
                <label for="password">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primario btn-bloque" style="margin-top:.5rem">
                <i class="fa-solid fa-right-to-bracket"></i>&nbsp; Ingresar
            </button>
        </form>
    </div>

</body>
</html>
