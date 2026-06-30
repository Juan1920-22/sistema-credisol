<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([2]);

$id       = $_SESSION['usuario_id'];
$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

$usuario = $conn->query("SELECT * FROM usuarios WHERE id=$id")->fetch_assoc();

// Actualizar datos
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'actualizar') {
    $tel = limpiar($_POST['telefono'] ?? '');
    $dir = limpiar($_POST['direccion'] ?? '');
    $conn->query("UPDATE usuarios SET telefono='$tel', direccion='$dir' WHERE id=$id");

    // Cambiar contraseña
    $pass_actual = $_POST['pass_actual'] ?? '';
    $pass_nueva  = $_POST['pass_nueva']  ?? '';
    if ($pass_actual && $pass_nueva) {
        if (password_verify($pass_actual, $usuario['contrasena_hash'])) {
            if (strlen($pass_nueva) >= 8) {
                $hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
                $conn->query("UPDATE usuarios SET contrasena_hash='$hash' WHERE id=$id");
                setMensaje("Perfil y contraseña actualizados.", "exito");
            } else {
                setMensaje("La nueva contraseña debe tener al menos 8 caracteres.", "error");
            }
        } else {
            setMensaje("La contraseña actual no es correcta.", "error");
        }
    } else {
        setMensaje("Perfil actualizado correctamente.", "exito");
    }
    header("Location: perfil.php");
    exit;
}

// Estadísticas del asesor
$stats = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(estado IN ('aprobada_asesor','aprobada','desembolsada')) AS aprobadas,
    SUM(estado IN ('rechazada_asesor','rechazada')) AS rechazadas,
    SUM(estado IN ('pendiente','en_evaluacion')) AS pendientes
    FROM solicitudes WHERE asesor_id=$id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Mi Perfil</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:200;overflow:hidden;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#6ee7b7;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;overflow-y:auto;}
        .menu-lbl{padding:10px 20px 4px;font-size:.62rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;}
        .sb-menu a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.88rem;transition:all .15s;border-left:3px solid transparent;}
        .sb-menu a:hover,.sb-menu a.activo{background:rgba(255,255,255,.07);color:#fff;border-left-color:#10b981;}
        .sb-menu a.activo{font-weight:600;}
        .sb-menu a svg{width:17px;height:17px;flex-shrink:0;}
        .sb-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.88rem;font-weight:700;text-decoration:none;}
        .topbar{position:fixed;top:0;left:250px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#065f46;}
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;align-items:center;gap:6px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        .perfil-header{background:linear-gradient(135deg,#065f46,#059669,#10b981);border-radius:14px;padding:28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:22px;}
        .perfil-avatar{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:#fff;flex-shrink:0;}
        .perfil-header h2{font-size:1.2rem;font-weight:800;}
        .perfil-header p{font-size:.82rem;opacity:.8;margin-top:3px;}

        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);text-align:center;border-top:3px solid transparent;}
        .stat.az{border-top-color:#3b82f6;}.stat.ve{border-top-color:#10b981;}.stat.na{border-top-color:#f59e0b;}.stat.ro{border-top-color:#ef4444;}
        .stat .num{font-size:1.6rem;font-weight:800;color:#0f172a;}
        .stat .lbl{font-size:.72rem;color:#64748b;margin-top:3px;}

        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}
        .form-grupo{margin-bottom:16px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#f9fafb;outline:none;transition:border .2s;}
        .form-grupo input:focus{border-color:#059669;background:#fff;}
        .form-grupo input:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed;}
        .campo-pass{position:relative;}
        .campo-pass input{padding-right:44px;}
        .ojo-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;}
        .ojo-btn svg{width:17px;height:17px;}
        .btn-guardar{background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;padding:11px 28px;font-size:.92rem;font-weight:700;cursor:pointer;}
        .btn-guardar:hover{opacity:.9;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.abierto{transform:translateX(0);}
            .topbar{left:0;}.contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
            .stats{grid-template-columns:1fr 1fr;}
            .uchip{display:none;}
            .btn-salir-movil{display:block !important;}
        }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="cerrarMenu()"></div>
<aside class="sidebar" id="sidebar">
    <div class="sb-brand"><img src="../../public/img/logo.png" alt="CREDISOL"><div><h2>CREDISOL</h2><span>Panel del Asesor</span></div></div>
    <div class="sb-user">
        <div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Asesor de Crédito</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
        <div class="menu-lbl">Mi Cuenta</div>
        <a href="perfil.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Mi Perfil</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>

    <!-- HEADER -->
    <div class="perfil-header">
        <div class="perfil-avatar"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div>
            <h2><?= htmlspecialchars($nombre.' '.$apellido) ?></h2>
            <p><?= htmlspecialchars($usuario['correo']) ?> &nbsp;|&nbsp; Asesor de Crédito</p>
            <p style="font-size:.72rem;opacity:.7;margin-top:4px;">DNI: <?= $usuario['dni'] ?></p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat az"><div class="num"><?= $stats['total']??0 ?></div><div class="lbl">Total Asignadas</div></div>
        <div class="stat na"><div class="num"><?= $stats['pendientes']??0 ?></div><div class="lbl">Pendientes</div></div>
        <div class="stat ve"><div class="num"><?= $stats['aprobadas']??0 ?></div><div class="lbl">Aprobadas</div></div>
        <div class="stat ro"><div class="num"><?= $stats['rechazadas']??0 ?></div><div class="lbl">Rechazadas</div></div>
    </div>

    <form method="POST">
        <input type="hidden" name="accion" value="actualizar">
        <div class="grid2">
            <!-- DATOS -->
            <div class="card">
                <h3>Mis Datos</h3>
                <div class="form-grupo"><label>Nombres</label><input type="text" value="<?= htmlspecialchars($usuario['nombres']) ?>" disabled></div>
                <div class="form-grupo"><label>Apellidos</label><input type="text" value="<?= htmlspecialchars($usuario['apellidos']) ?>" disabled></div>
                <div class="form-grupo"><label>DNI</label><input type="text" value="<?= $usuario['dni'] ?>" disabled></div>
                <div class="form-grupo"><label>Correo</label><input type="email" value="<?= htmlspecialchars($usuario['correo']) ?>" disabled></div>
                <div class="form-grupo"><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($usuario['telefono']??'') ?>" placeholder="987654321"></div>
                <div class="form-grupo"><label>Dirección</label><input type="text" name="direccion" value="<?= htmlspecialchars($usuario['direccion']??'') ?>" placeholder="Av. Los Próceres 123"></div>
            </div>
            <!-- CONTRASEÑA -->
            <div class="card">
                <h3>Cambiar Contraseña</h3>
                <p style="font-size:.82rem;color:#64748b;margin-bottom:16px;">Deja estos campos vacíos si no quieres cambiar tu contraseña.</p>
                <div class="form-grupo">
                    <label>Contraseña actual</label>
                    <div class="campo-pass">
                        <input type="password" name="pass_actual" id="p1" placeholder="Ingresa tu contraseña actual">
                        <button type="button" class="ojo-btn" onmousedown="show('p1')" onmouseup="hide('p1')" onmouseleave="hide('p1')">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-grupo">
                    <label>Nueva contraseña</label>
                    <div class="campo-pass">
                        <input type="password" name="pass_nueva" id="p2" placeholder="Mínimo 8 caracteres">
                        <button type="button" class="ojo-btn" onmousedown="show('p2')" onmouseup="hide('p2')" onmouseleave="hide('p2')">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </div>
                <div style="margin-top:24px;">
                    <button type="submit" class="btn-guardar">Guardar cambios</button>
                </div>
            </div>
        </div>
    </form>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('abierto');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('abierto');document.getElementById('overlay').classList.remove('show');}
function show(id){document.getElementById(id).type='text';}
function hide(id){document.getElementById(id).type='password';}
</script>
</body>
</html>