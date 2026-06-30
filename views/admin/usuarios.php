<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

// Crear usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'crear') {
    $nombres   = limpiar($_POST['nombres'] ?? '');
    $apellidos = limpiar($_POST['apellidos'] ?? '');
    $correo    = limpiar($_POST['correo'] ?? '');
    $rol_id    = intval($_POST['rol_id'] ?? 1);
    $dni       = limpiar($_POST['dni'] ?? '');
    $telefono  = limpiar($_POST['telefono'] ?? '');
    $pass = trim($_POST['contrasena'] ?? '');
    if (empty($pass)) $pass = 'Credisol123';

    if (!$nombres || !$apellidos || !$correo || !$dni) {
        setMensaje("Completa todos los campos obligatorios.", "error");
    } else {
        $check = $conn->prepare("SELECT id FROM usuarios WHERE correo=? OR dni=?");
        $check->bind_param("ss", $correo, $dni);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            setMensaje("Ya existe un usuario con ese correo o DNI.", "error");
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare(
                "INSERT INTO usuarios (rol_id, dni, nombres, apellidos, correo, contrasena_hash, telefono, activo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->bind_param("issssss", $rol_id, $dni, $nombres, $apellidos, $correo, $hash, $telefono);
            if ($stmt->execute()) {
                $roles = [1=>'Cliente', 2=>'Asesor', 3=>'Admin'];
                setMensaje("Usuario {$roles[$rol_id]} creado: $nombres $apellidos. Contraseña: $pass", "exito");
            } else {
                setMensaje("Error al crear el usuario.", "error");
            }
        }
    }
    header("Location: usuarios.php");
    exit;
}

// Bloquear/activar usuario
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $uid    = intval($_GET['id']);
    $activo = intval($_GET['toggle']);
    $conn->query("UPDATE usuarios SET activo=$activo WHERE id=$uid AND rol_id != 3");
    setMensaje($activo ? "Usuario activado." : "Usuario bloqueado.", $activo ? "exito" : "advertencia");
    header("Location: usuarios.php");
    exit;
}

// Obtener usuarios
$filtro_rol = intval($_GET['rol'] ?? 0);
$sql = "SELECT u.*, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.rol_id != 3";
if ($filtro_rol) $sql .= " AND u.rol_id = $filtro_rol";
$sql .= " ORDER BY u.creado_en DESC";
$usuarios = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Gestionar Usuarios</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:260px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#fcd34d;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;}
        .menu-lbl{padding:10px 20px 4px;font-size:.62rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;}
        .sb-menu a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.86rem;transition:all .15s;border-left:3px solid transparent;}
        .sb-menu a:hover,.sb-menu a.activo{background:rgba(255,255,255,.07);color:#fff;border-left-color:#f59e0b;}
        .sb-menu a.activo{font-weight:600;}
        .sb-menu a svg{width:16px;height:16px;flex-shrink:0;}
        .sb-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.83rem;font-weight:600;text-decoration:none;}
        .topbar{position:fixed;top:0;left:260px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#92400e;}
        .contenido{margin-left:260px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:none;border:none;cursor:pointer;color:#64748b;padding:4px;}
        .menu-btn svg{width:22px;height:22px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        .grid2{display:grid;grid-template-columns:340px 1fr;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        .form-grupo{margin-bottom:14px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input,.form-grupo select{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#f9fafb;outline:none;color:#111827;transition:border .2s;}
        .form-grupo input:focus,.form-grupo select:focus{border-color:#1d4ed8;background:#fff;}
        .info-pass{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:.8rem;color:#1e40af;margin-bottom:14px;}

        .btn-crear{background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:.92rem;font-weight:700;cursor:pointer;width:100%;}
        .btn-crear:hover{opacity:.9;}

        /* FILTROS */
        .filtros{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
        .filtro-btn{padding:7px 16px;border-radius:20px;font-size:.8rem;font-weight:600;text-decoration:none;border:1.5px solid #e2e8f0;color:#64748b;background:#fff;transition:all .2s;}
        .filtro-btn:hover,.filtro-btn.activo{background:#1d4ed8;color:#fff;border-color:#1d4ed8;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .b-cliente{background:#dbeafe;color:#1e40af;}
        .b-asesor{background:#d1fae5;color:#065f46;}
        .b-admin{background:#fef3c7;color:#92400e;}
        .b-activo{background:#d1fae5;color:#065f46;}
        .b-bloq{background:#fee2e2;color:#991b1b;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-block{background:#fee2e2;color:#991b1b;}
        .btn-unblock{background:#d1fae5;color:#065f46;}

        .empty{text-align:center;padding:32px;color:#94a3b8;font-size:.86rem;}

        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="cerrarMenu()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <div><h2>CREDISOL</h2><span>Panel de Administración</span></div>
    </div>
    <div class="sb-user">
        <div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>&#9733; Administrador General</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Dashboard</a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Todas las Solicitudes</a>
        <a href="aprobaciones.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Aprobaciones Finales</a>
        <a href="desembolsos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Desembolsos</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
        <a href="ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Gestionar Ahorros</a>
        <a href="pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Registrar Pagos</a>
        <div class="menu-lbl">Usuarios</div>
        <a href="usuarios.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Gestionar Usuarios</a>
        <div class="menu-lbl">Sistema</div>
        <a href="reportes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <h1>Gestionar Usuarios</h1>
    </div>
    <div class="uchip">
        <div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <span><?= htmlspecialchars($nombre) ?></span>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="grid2">

        <!-- FORMULARIO CREAR USUARIO -->
        <div class="card">
            <h3>Crear Nuevo Usuario</h3>
            <div class="info-pass">
                Si no escribes contraseña, se usará <strong>Credisol123</strong> por defecto. El usuario puede cambiarla desde su perfil.
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="form-grupo">
                    <label>Tipo de usuario *</label>
                    <select name="rol_id" required>
                        <option value="1">Cliente</option>
                        <option value="2">Asesor de Crédito</option>
                    </select>
                </div>
                <div class="form-grupo">
                    <label>DNI *</label>
                    <input type="text" name="dni" placeholder="12345678" maxlength="8" required>
                </div>
                <div class="form-grupo">
                    <label>Nombres *</label>
                    <input type="text" name="nombres" placeholder="Juan Carlos" required>
                </div>
                <div class="form-grupo">
                    <label>Apellidos *</label>
                    <input type="text" name="apellidos" placeholder="Pérez García" required>
                </div>
                <div class="form-grupo">
                    <label>Correo electrónico *</label>
                    <input type="email" name="correo" placeholder="correo@email.com" required>
                </div>
                <div class="form-grupo">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" placeholder="987654321">
                </div>
                <div class="form-grupo">
                    <label>Contraseña (opcional)</label>
                    <input type="text" name="contrasena" placeholder="Dejar vacío para usar Credisol123">
                </div>
                <button type="submit" class="btn-crear">Crear Usuario</button>
            </form>
        </div>

        <!-- LISTA DE USUARIOS -->
        <div class="card">
            <h3>Usuarios del Sistema</h3>

            <!-- FILTROS -->
            <div class="filtros">
                <a href="usuarios.php" class="filtro-btn <?= !$filtro_rol?'activo':'' ?>">Todos</a>
                <a href="usuarios.php?rol=1" class="filtro-btn <?= $filtro_rol==1?'activo':'' ?>">Clientes</a>
                <a href="usuarios.php?rol=2" class="filtro-btn <?= $filtro_rol==2?'activo':'' ?>">Asesores</a>
            </div>

            <?php if (empty($usuarios)): ?>
            <div class="empty">No hay usuarios registrados.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($u['nombres'].' '.$u['apellidos']) ?></div>
                        <div style="font-size:.73rem;color:#94a3b8;">DNI: <?= $u['dni'] ?></div>
                    </td>
                    <td style="color:#64748b;font-size:.78rem;"><?= htmlspecialchars($u['correo']) ?></td>
                    <td>
                        <?php
                        $rc = ['1'=>'b-cliente','2'=>'b-asesor','3'=>'b-admin'];
                        $rt = ['1'=>'Cliente','2'=>'Asesor','3'=>'Admin'];
                        ?>
                        <span class="badge <?= $rc[$u['rol_id']]??'' ?>"><?= $rt[$u['rol_id']]??'' ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $u['activo']?'b-activo':'b-bloq' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Bloqueado' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['activo']): ?>
                        <a href="usuarios.php?id=<?= $u['id'] ?>&toggle=0"
                           class="btn-sm btn-block"
                           onclick="return confirm('¿Bloquear a <?= htmlspecialchars($u['nombres']) ?>?')">
                           Bloquear
                        </a>
                        <?php else: ?>
                        <a href="usuarios.php?id=<?= $u['id'] ?>&toggle=1"
                           class="btn-sm btn-unblock">
                           Activar
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>
</body>
</html>