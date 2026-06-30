<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1]);

$id       = $_SESSION['usuario_id'];
$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

// Cargar foto de perfil desde BD si no está en sesión
if (empty($_SESSION['foto'])) {
    $u = $conn->query("SELECT foto FROM usuarios WHERE id=$id")->fetch_assoc();
    if ($u && $u['foto']) $_SESSION['foto'] = $u['foto'];
}

$usuario = $conn->query("SELECT * FROM usuarios WHERE id = $id")->fetch_assoc();

// Subir foto de perfil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
    $archivo = $_FILES['foto'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp']) && $archivo['size'] <= 3*1024*1024) {
        $carpeta = __DIR__ . "/../../public/uploads/fotos/";
        if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
        $nombre_foto = "foto_" . $id . "." . $ext;
        if (move_uploaded_file($archivo['tmp_name'], $carpeta . $nombre_foto)) {
            $ruta_foto = "public/uploads/fotos/" . $nombre_foto;
            $conn->query("UPDATE usuarios SET foto='$ruta_foto' WHERE id=$id");
            $usuario['foto'] = $ruta_foto;
            $_SESSION['foto'] = $ruta_foto; // guardar en sesión
            setMensaje("Foto de perfil actualizada.", "exito");
        }
    } else {
        setMensaje("Solo JPG/PNG/WEBP hasta 3MB.", "error");
    }
    header("Location: mi_perfil.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tel  = limpiar($_POST['telefono'] ?? '');
    $dir  = limpiar($_POST['direccion'] ?? '');
    $ocu  = limpiar($_POST['ocupacion'] ?? '');
    $ing  = floatval($_POST['ingreso_mensual'] ?? 0);

    $stmt = $conn->prepare("UPDATE usuarios SET telefono=?, direccion=?, ocupacion=?, ingreso_mensual=? WHERE id=?");
    $stmt->bind_param("sssdi", $tel, $dir, $ocu, $ing, $id);
    $stmt->execute();

    // Cambiar contraseña si se llenó
    $pass_actual = $_POST['pass_actual'] ?? '';
    $pass_nueva  = $_POST['pass_nueva']  ?? '';
    if ($pass_actual && $pass_nueva) {
        if (password_verify($pass_actual, $usuario['contrasena_hash'])) {
            if (strlen($pass_nueva) >= 8) {
                $hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
                $conn->query("UPDATE usuarios SET contrasena_hash='$hash' WHERE id=$id");
                setMensaje("Perfil y contraseña actualizados correctamente.", "exito");
            } else {
                setMensaje("La nueva contraseña debe tener al menos 8 caracteres.", "error");
            }
        } else {
            setMensaje("La contraseña actual no es correcta.", "error");
        }
    } else {
        setMensaje("Perfil actualizado correctamente.", "exito");
    }

    header("Location: mi_perfil.php");
    exit;
}
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
        .sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .avatar-g{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#93c5fd;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;}
        .menu-lbl{padding:10px 20px 4px;font-size:.62rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;}
        .sb-menu a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.86rem;transition:all .15s;border-left:3px solid transparent;}
        .sb-menu a:hover,.sb-menu a.activo{background:rgba(255,255,255,.07);color:#fff;border-left-color:#3b82f6;}
        .sb-menu a.activo{font-weight:600;}
        .sb-menu a svg{width:16px;height:16px;flex-shrink:0;}
        .sb-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.83rem;font-weight:600;text-decoration:none;}
        .topbar{position:fixed;top:0;left:250px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:5px 10px;}
        .uchip .av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#0f172a;}
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        .perfil-header{background:linear-gradient(135deg,#0a2463,#1d4ed8);border-radius:14px;padding:28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:22px;}
        .perfil-avatar{width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;color:#fff;flex-shrink:0;}
        .perfil-header h2{font-size:1.2rem;font-weight:800;}
        .perfil-header p{font-size:.82rem;opacity:.8;margin-top:3px;}

        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}
        .form-grupo{margin-bottom:16px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#f9fafb;outline:none;color:#111827;transition:border .2s;}
        .form-grupo input:focus{border-color:#1d4ed8;background:#fff;}
        .form-grupo input:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed;}
        .btn-guardar{background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:8px;padding:11px 28px;font-size:.92rem;font-weight:700;cursor:pointer;transition:opacity .2s;}
        .btn-guardar:hover{opacity:.9;}
        .campo-pass{position:relative;}
        .campo-pass input{padding-right:44px;}
        .ojo-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;}
        .ojo-btn svg{width:17px;height:17px;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
        }

        /* BOTÓN CERRAR SESIÓN FIJO EN MÓVIL */
        .btn-logout-movil{
            display:none;
            position:fixed;
            bottom:16px; right:16px;
            background:#ef4444;
            color:#fff;
            border:none;
            border-radius:50px;
            padding:12px 20px;
            font-size:.85rem;
            font-weight:700;
            cursor:pointer;
            z-index:150;
            box-shadow:0 4px 12px rgba(239,68,68,.4);
            text-decoration:none;
            align-items:center;
            gap:8px;
        }
        .btn-logout-movil svg{width:16px;height:16px;}
        @media(max-width:768px){
            .btn-logout-movil{display:flex;}
        }

        /* CAMPANITA */
        .notif-wrap{position:relative;}
        .notif-btn{background:none;border:none;cursor:pointer;position:relative;padding:6px;color:#64748b;display:flex;align-items:center;border-radius:8px;transition:background .2s;}
        .notif-btn:hover{background:#f1f5f9;}
        .notif-badge{position:absolute;top:0;right:0;background:#ef4444;color:#fff;font-size:.62rem;font-weight:700;padding:2px 5px;border-radius:20px;min-width:18px;text-align:center;}
        .notif-panel{position:fixed;right:16px;top:70px;width:320px;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);z-index:9999;border:1px solid #e2e8f0;overflow:hidden;}
        .notif-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #f1f5f9;font-size:.85rem;font-weight:700;color:#0f172a;}
        .notif-lista{max-height:320px;overflow-y:auto;}
        .notif-item{display:flex;gap:10px;padding:11px 14px;border-bottom:1px solid #f8fafc;cursor:pointer;transition:background .15s;}
        .notif-item:hover{background:#f8fafc;}
        .notif-item.no-leida{background:#eff6ff;}
        .notif-ico{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.95rem;}
        .notif-ico.exito{background:#d1fae5;}.notif-ico.error{background:#fee2e2;}.notif-ico.info{background:#dbeafe;}.notif-ico.advertencia{background:#fef3c7;}
        .notif-titulo{font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:2px;}
        .notif-msg{font-size:.74rem;color:#64748b;line-height:1.4;}
        .notif-hora{font-size:.67rem;color:#94a3b8;margin-top:3px;}
        @media(max-width:480px){.notif-panel{width:260px;right:-40px;}}
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="cerrarMenu()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <div><h2>CREDISOL</h2><span>Cooperativa de Ahorro y Crédito</span></div>
    </div>
    <div class="sb-user">
        <?= avatar($nombre, $usuario['foto']??null, 38) ?>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Cliente</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Menu Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <a href="solicitar.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Solicitar Préstamo</a>
        <a href="mis_solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <a href="mis_ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Mis Ahorros</a>
        <a href="mis_pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mis Pagos</a>
        <a href="mi_perfil.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <h1>Mi Perfil</h1>
    </div>
    
<!-- CAMPANITA NOTIFICACIONES -->
<div class="notif-wrap" id="notifWrap">
    <button class="notif-btn" onclick="toggleNotif()" title="Notificaciones">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span class="notif-badge" id="notifBadge" style="display:none;">0</span>
    </button>
    <div class="notif-panel" id="notifPanel" style="display:none;">
        <div class="notif-header">
            <span>Notificaciones</span>
            <button onclick="leerTodas()" style="font-size:.72rem;color:#3b82f6;background:none;border:none;cursor:pointer;font-weight:600;">Leídas</button>
        </div>
        <div id="notifLista"><div style="text-align:center;padding:20px;color:#94a3b8;font-size:.82rem;">Cargando...</div></div>
    </div>
</div>
        <div class="uchip">
        <?= avatar($nombre, $usuario['foto']??null, 26) ?>
        <span><?= htmlspecialchars($nombre) ?></span>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>

    <!-- HEADER PERFIL -->
    <div class="perfil-header">
        <!-- FOTO DE PERFIL -->
        <div style="position:relative;flex-shrink:0;">
            <?php if (!empty($usuario['foto']) && file_exists(__DIR__.'/../../'.$usuario['foto'])): ?>
            <img src="../../<?= $usuario['foto'] ?>?v=<?= time() ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,.3);">
            <?php else: ?>
            <div class="perfil-avatar"><?= strtoupper(substr($nombre,0,1)) ?></div>
            <?php endif; ?>
            <!-- Botón cambiar foto -->
            <label for="fotoInput" style="position:absolute;bottom:0;right:0;width:24px;height:24px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.2);" title="Cambiar foto">
                <svg fill="none" viewBox="0 0 24 24" stroke="#1d4ed8" stroke-width="2.5" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </label>
            <form method="POST" enctype="multipart/form-data" id="fotoForm">
                <input type="file" id="fotoInput" name="foto" accept=".jpg,.jpeg,.png,.webp"
                       style="display:none;" onchange="document.getElementById('fotoForm').submit()">
            </form>
        </div>
        <div>
            <h2><?= htmlspecialchars($nombre.' '.$apellido) ?></h2>
            <p><?= htmlspecialchars($usuario['correo']) ?> &nbsp;|&nbsp; Cliente CREDISOL</p>
            <p style="font-size:.72rem;opacity:.7;margin-top:4px;">Toca el ícono de cámara para cambiar tu foto</p>
        </div>
    </div>

    <form method="POST">
    <div class="grid2">

        <!-- DATOS PERSONALES -->
        <div class="card">
            <h3>Datos Personales</h3>
            <div class="form-grupo">
                <label>Nombres</label>
                <input type="text" value="<?= htmlspecialchars($usuario['nombres']) ?>" disabled>
            </div>
            <div class="form-grupo">
                <label>Apellidos</label>
                <input type="text" value="<?= htmlspecialchars($usuario['apellidos']) ?>" disabled>
            </div>
            <div class="form-grupo">
                <label>Correo electrónico</label>
                <input type="email" value="<?= htmlspecialchars($usuario['correo']) ?>" disabled>
            </div>
            <div class="form-grupo">
                <label>Teléfono</label>
                <input type="text" name="telefono" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" placeholder="987654321">
            </div>
            <div class="form-grupo">
                <label>Dirección</label>
                <input type="text" name="direccion" value="<?= htmlspecialchars($usuario['direccion'] ?? '') ?>" placeholder="Av. Los Próceres 123">
            </div>
            <div class="form-grupo">
                <label>Ocupación</label>
                <input type="text" name="ocupacion" value="<?= htmlspecialchars($usuario['ocupacion'] ?? '') ?>" placeholder="Contador, Comerciante...">
            </div>
            <div class="form-grupo">
                <label>Ingreso Mensual (S/)</label>
                <input type="number" name="ingreso_mensual" value="<?= $usuario['ingreso_mensual'] ?? '' ?>" placeholder="2500.00" step="0.01" min="0">
            </div>
        </div>

        <!-- CAMBIAR CONTRASEÑA -->
        <div class="card">
            <h3>Cambiar Contraseña</h3>
            <p style="font-size:.82rem;color:#64748b;margin-bottom:16px;">Deja estos campos vacíos si no quieres cambiar tu contraseña.</p>
            <div class="form-grupo">
                <label>Contraseña actual</label>
                <div class="campo-pass">
                    <input type="password" name="pass_actual" id="p1" placeholder="Ingresa tu contraseña actual">
                    <button type="button" class="ojo-btn" onmousedown="show('p1')" onmouseup="hide('p1')" onmouseleave="hide('p1')" ontouchstart="show('p1')" ontouchend="hide('p1')">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-grupo">
                <label>Nueva contraseña</label>
                <div class="campo-pass">
                    <input type="password" name="pass_nueva" id="p2" placeholder="Mínimo 8 caracteres">
                    <button type="button" class="ojo-btn" onmousedown="show('p2')" onmouseup="hide('p2')" onmouseleave="hide('p2')" ontouchstart="show('p2')" ontouchend="hide('p2')">
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
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function show(id){document.getElementById(id).type='text';}
function hide(id){document.getElementById(id).type='password';}
</script>

<!-- BOTÓN CERRAR SESIÓN MÓVIL -->
<a href="../../controllers/AuthController.php?accion=logout" class="btn-logout-movil">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
    </svg>
    Cerrar Sesión
</a>
</body>
</html>