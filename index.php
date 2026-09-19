<?php
require_once __DIR__ . "/config.php";

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 2592000);
    ini_set('session.cookie_lifetime', 2592000);
    session_save_path(__DIR__ . '/sessions');
    session_start();
    
    // Forzar actualización de la cookie en el navegador
    setcookie(session_name(), session_id(), time() + 2592000, "/");
}

if (empty($_SESSION["usuario_id"]) && !empty($_COOKIE['remember_user'])) {
    $pdo = conectarBD();
    $stmt = $pdo->prepare("SELECT id, nombre_completo, rol, sucursal_id FROM usuarios WHERE id = ? AND activo = 1");
    $stmt->execute([$_COOKIE['remember_user']]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['usuario_id'] = $u['id'];
        $_SESSION['nombre_completo'] = $u['nombre_completo'];
        $_SESSION['rol'] = $u['rol'];
        $_SESSION['sucursal_id'] = $u['sucursal_id'];
    }
}

if (!empty($_SESSION["usuario_id"])) {
    $esAdmin = in_array($_SESSION["rol"] ?? "", ["administrador", "supervisor"], true);
    header("Location: /" . ($esAdmin ? "admin/dashboard.php" : "vendedor/dashboard.php"));
    exit;
}

$error = $_SESSION["login_error"] ?? "";
unset($_SESSION["login_error"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $_SESSION["login_error"] = "Por favor ingresa tu usuario y contraseña.";
        header("Location: /index.php");
        exit;
    } else {
        $pdo  = conectarBD();
        $stmt = $pdo->prepare("SELECT id, nombre_completo, password_hash, rol, sucursal_id FROM usuarios WHERE username = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario["password_hash"])) {
            session_regenerate_id(true);
            setcookie(session_name(), session_id(), time() + 2592000, "/");
            
            // Auto-login persistente (Remember Me)
            setcookie('remember_user', $usuario["id"], time() + 2592000, "/");
            
            $_SESSION["usuario_id"]      = $usuario["id"];
            $_SESSION["nombre_completo"] = $usuario["nombre_completo"];
            $_SESSION["rol"]             = $usuario["rol"];
            $_SESSION["sucursal_id"]     = $usuario["sucursal_id"];

            $esAdmin = in_array($usuario["rol"], ["administrador", "supervisor"], true);
            header("Location: /" . ($esAdmin ? "admin/dashboard.php" : "vendedor/dashboard.php"));
            exit;
        } else {
            $_SESSION["login_error"] = "Usuario o contraseña incorrectos.";
            $_SESSION["username_previo"] = $username;
            header("Location: /index.php");
            exit;
        }
    }
}
$usernamePrevio = htmlspecialchars($_SESSION["username_previo"] ?? "");
unset($_SESSION["username_previo"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLEE - Iniciar Sesión</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- PWA y Favicon -->
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="apple-touch-icon" href="/assets/img/logo.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#1e293b">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body.login-body {
            background: url('/assets/img/bg-login.jpg') no-repeat center center / cover !important;
        }
        .login-card {
            background: rgba(0, 0, 0, 0.6) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5) !important;
            color: #fff !important;
        }
        .login-card h1 {
            color: #fff !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
        .login-card p {
            color: #cbd5e1 !important;
        }
        .login-card .form-grupo label {
            color: #e2e8f0 !important;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }
        .login-card input {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            color: #fff !important;
        }
        .login-card input::placeholder {
            color: rgba(255, 255, 255, 0.4) !important;
        }
        .login-card input:focus {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: #fff !important;
        }
    </style>
</head>
<body class="login-body">

    <div class="login-card">
        <div class="login-logo" style="margin-bottom: 2rem;">
            <h1 style="margin:0; font-family: 'Georgia', serif; font-size: 2.5rem; letter-spacing: 6px; font-weight: bold; color: var(--primario);">GLEE</h1>
            <p style="margin-top:0.5rem; font-size: 0.85rem; letter-spacing: 2px; color: var(--gris); text-transform: uppercase;">Control de Asistencia</p>
        </div>

        <form method="POST" action="/index.php" novalidate>
            <div class="form-grupo">
                <label for="username">Usuario</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= $usernamePrevio ?>"
                    placeholder="ej. juanperez"
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

    <?php if ($error !== ""): ?>
        <script>
            Swal.fire({
                icon: "error",
                title: "Error de acceso",
                text: "<?= htmlspecialchars($error) ?>",
                confirmButtonColor: "var(--primario)"
            });
        </script>
    <?php endif; ?>

    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js').then(registration => {
            console.log('SW registrado: ', registration);
          }).catch(registrationError => {
            console.log('Error en registro de SW: ', registrationError);
          });
        });
      }
    </script>
</body>
</html>
