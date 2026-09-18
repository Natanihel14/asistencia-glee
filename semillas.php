<?php
// ============================================================
// semillas.php — Inserta usuarios de prueba con contraseñas
// hasheadas correctamente con password_hash().
//
// ¡EJECUTAR UNA SOLA VEZ y luego ELIMINAR este archivo!
// URL: http://localhost/semillas.php
// ============================================================

require_once __DIR__ . '/config.php';

$pdo = conectarBD();

// Usuarios de prueba
$usuarios = [
    [
        'nombre_completo' => 'Administrador GLEE',
        'correo'          => 'admin',
        'password'        => 'Admin123',
        'rol'             => 'administrador',
        'sucursal_id'     => null,          // el admin no pertenece a una sola sucursal
    ],
    [
        'nombre_completo' => 'Vendedor de Prueba',
        'correo'          => 'vendedor',
        'password'        => 'Vendedor123',
        'rol'             => 'vendedor',
        'sucursal_id'     => 1,             // Tienda Jalapa / Bodega Central
    ],
];

$stmtBuscar = $pdo->prepare(
    'SELECT id FROM usuarios WHERE correo = :correo'
);

$stmtInsertar = $pdo->prepare('
    INSERT INTO usuarios (nombre_completo, correo, password_hash, rol, sucursal_id)
    VALUES (:nombre, :correo, :hash, :rol, :sucursal_id)
');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Semillas — GLEE</title>
    <style>
        body  { font-family: monospace; padding: 2rem; background: #f5f5f5; }
        h2    { color: #333; }
        .ok   { color: green; }
        .warn { color: #b8860b; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem;
                max-width: 520px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        a     { color: #0066cc; }
    </style>
</head>
<body>
<div class="card">
    <h2>Semillas — Sistema GLEE</h2>
    <?php foreach ($usuarios as $u): ?>
        <?php
            $stmtBuscar->execute([':correo' => $u['correo']]);
            if ($stmtBuscar->fetch()):
        ?>
            <p class="warn">
                ⚠️ <strong><?= htmlspecialchars($u['correo']) ?></strong>
                ya existe — se omitió.
            </p>
        <?php else:
            $hash = password_hash($u['password'], PASSWORD_DEFAULT);
            $stmtInsertar->execute([
                ':nombre'      => $u['nombre_completo'],
                ':correo'      => $u['correo'],
                ':hash'        => $hash,
                ':rol'         => $u['rol'],
                ':sucursal_id' => $u['sucursal_id'],
            ]);
        ?>
            <p class="ok">
                ✅ <strong><?= htmlspecialchars($u['correo']) ?></strong>
                (<?= $u['rol'] ?>) — insertado correctamente.
            </p>
        <?php endif; ?>
    <?php endforeach; ?>

    <hr>
    <p><strong>Credenciales de prueba:</strong></p>
    <ul>
        <li>Admin: <code>admin</code> / <code>Admin123</code></li>
        <li>Vendedor: <code>vendedor</code> / <code>Vendedor123</code></li>
    </ul>
    <p style="color:#c00">
        ⚠️ <strong>Elimina este archivo</strong> después de usarlo.
    </p>
    <p><a href="index.php">→ Ir al Login</a></p>
</div>
</body>
</html>
