<?php
require_once __DIR__ . '/includes/auth.php';
requerirLogin();

require_once __DIR__ . '/includes/almacenes.php';

$esAdmin = (($_SESSION['usuario_nombre'] ?? '') === 'Administrador') || ((int)($_SESSION['usuario_id'] ?? 0) === 1);
if (!$esAdmin) {
    http_response_code(403);
    header('Location: index.php');
    exit;
}

$mensaje = '';
$error = '';

$pdo = getDB();

// Crear almacén
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_almacen') {
    $nombre = trim((string)($_POST['nombre_almacen'] ?? ''));
    if ($nombre === '') {
        $error = 'Indique el nombre del almacén.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO almacenes (nombre) VALUES (?)');
            $stmt->execute([$nombre]);
            $mensaje = 'Almacén creado.';
        } catch (Throwable $e) {
            $error = 'No se pudo crear el almacén: ' . $e->getMessage();
        }
    }
}

// Guardar asignaciones usuarios -> almacén
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_asignaciones') {
    $idsUsuarios = $_POST['usuario_id'] ?? [];
    $almacenPorUsuario = $_POST['almacen_id'] ?? [];

    if (!is_array($idsUsuarios) || !is_array($almacenPorUsuario)) {
        $error = 'Datos inválidos para asignaciones.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE usuarios SET almacen_id = ? WHERE id = ?');
            foreach ($idsUsuarios as $id) {
                $id = (int)$id;
                if ($id <= 0) continue;
                $almId = isset($almacenPorUsuario[(string)$id]) ? (int)$almacenPorUsuario[(string)$id] : 0;
                if ($almId <= 0) $almId = null;
                $stmt->execute([$almId, $id]);
            }
            $pdo->commit();
            $mensaje = 'Asignaciones guardadas.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'No se pudo guardar: ' . $e->getMessage();
        }
    }
}

// Crear usuario (personal) y asignarlo a almacén
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_usuario') {
    $usuarioLogin = trim((string)($_POST['usuario_login'] ?? ''));
    $nombrePersona = trim((string)($_POST['nombre_persona'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    $almacenId = (int)($_POST['almacen_id_usuario'] ?? 0);

    if ($usuarioLogin === '' || $nombrePersona === '' || $clave === '') {
        $error = 'Complete usuario, nombre y contraseña.';
    } elseif ($nombrePersona === 'Administrador') {
        $error = 'El nombre "Administrador" es reservado.';
    } elseif ($almacenId <= 0) {
        $error = 'Seleccione un almacén para el personal.';
    } elseif (strlen($clave) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            // Validar que el usuario_login no exista.
            $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
            $stmtCheck->execute([$usuarioLogin]);
            if ($stmtCheck->fetch()) {
                $error = 'Ya existe un usuario con ese login.';
            } else {
                $hash = password_hash($clave, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (usuario, clave, nombre, almacen_id) VALUES (?, ?, ?, ?)');
                $stmt->execute([$usuarioLogin, $hash, $nombrePersona, $almacenId]);
                $mensaje = 'Personal creado correctamente.';
            }
        } catch (Throwable $e) {
            $error = 'No se pudo crear el personal: ' . $e->getMessage();
        }
    }
}

$almacenes = listarAlmacenes();

// Usuarios y su almacén
$stmtUsuarios = $pdo->query("
    SELECT u.id, u.usuario, u.nombre,
           a.nombre AS almacen_nombre, u.almacen_id
    FROM usuarios u
    LEFT JOIN almacenes a ON a.id = u.almacen_id
    ORDER BY u.nombre ASC, u.usuario ASC
");
$usuarios = $stmtUsuarios->fetchAll();

// Mantener almacén activo si existe.
$almacenActivo = (int)($_SESSION['almacen_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración de almacenes - Sistema de Almacén</title>
  <link rel="icon" type="image/webp" href="assets/css/img/logo_cecyte_grande.webp">
  <link rel="stylesheet" href="assets/css/style.css?v=1">
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">
        <img src="assets/css/img/logo_cecyte_grande.webp" alt="Cecyte" class="logo-img">
        <span>Sistema de Almacén</span>
      </div>
      <div class="header-actions">
        <a href="index.php" class="btn btn-secondary">Volver al panel</a>
        <a href="logout.php" class="btn btn-secondary">Salir</a>
      </div>
    </header>

    <?php include __DIR__ . '/includes/nav.php'; ?>

    <main style="margin-top:1rem;">
      <header class="page-header">
        <div class="page-header-texto">
          <h1 class="page-title">Almacenes y asignaciones</h1>
          <p class="page-header-subtitulo">Cree almacenes y asigne cada persona a su almacén.</p>
        </div>
      </header>

      <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <section class="form-layout-dual" style="align-items:flex-start;">
        <div class="form-card form-card--datos">
          <div class="form-card-header">
            <h3 class="form-card-title">Nuevo almacén</h3>
          </div>
          <form method="post">
            <input type="hidden" name="accion" value="crear_almacen">
            <div class="form-group">
              <label>Nombre del almacén</label>
              <input type="text" name="nombre_almacen" required placeholder="Ej. Almacén Norte">
            </div>
            <button type="submit" class="btn btn-primary">Crear</button>
          </form>

          <hr style="margin:1rem 0; border:none; border-top:1px solid #e6e6e6;">

          <div class="form-card-header">
            <h3 class="form-card-title">Nuevo personal</h3>
          </div>
          <form method="post">
            <input type="hidden" name="accion" value="crear_usuario">
            <div class="form-group">
              <label>Login (usuario)</label>
              <input type="text" name="usuario_login" required placeholder="Ej. juan.perez">
            </div>
            <div class="form-group">
              <label>Nombre</label>
              <input type="text" name="nombre_persona" required placeholder="Ej. Juan Pérez">
            </div>
            <div class="form-group">
              <label>Contraseña</label>
              <input type="password" name="clave" required placeholder="Mínimo 6 caracteres">
            </div>
            <div class="form-group">
              <label>Almacén</label>
              <select name="almacen_id_usuario" required>
                <?php foreach ($almacenes as $a): ?>
                  <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-primary">Crear personal</button>
          </form>
        </div>

        <div class="form-card form-card--wide form-card--detalle">
          <div class="form-card-header">
            <h3 class="form-card-title">Asignar usuarios a almacén</h3>
          </div>

          <form method="post">
            <input type="hidden" name="accion" value="guardar_asignaciones">

            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Almacén</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($usuarios)): ?>
                    <tr><td colspan="3" class="empty-msg">No hay usuarios.</td></tr>
                  <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                      <?php $uid = (int)($u['id'] ?? 0); ?>
                      <tr>
                        <td><?= htmlspecialchars($u['usuario'] ?? '') ?></td>
                        <td><?= htmlspecialchars($u['nombre'] ?? '') ?></td>
                        <td>
                          <select name="almacen_id[<?= $uid ?>]" aria-label="Almacén de usuario">
                            <option value="">— (sin asignar) —</option>
                            <?php foreach ($almacenes as $a): ?>
                              <option value="<?= (int)$a['id'] ?>" <?= ((int)($u['almacen_id'] ?? 0) === (int)$a['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nombre']) ?><?= ((int)($u['almacen_id'] ?? 0) === $almacenActivo) ? ' (activo)' : '' ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                      </tr>
                      <input type="hidden" name="usuario_id[]" value="<?= $uid ?>">
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="form-actions" style="margin-top:1rem;">
              <button type="submit" class="btn btn-primary">Guardar asignaciones</button>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>
</body>
</html>

