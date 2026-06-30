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

$solicitudes = [];
$r = $conn->query(
    "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes
     FROM solicitudes s JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     WHERE s.cliente_id=$id ORDER BY s.fecha_solicitud DESC"
);
if ($r) $solicitudes = $r->fetch_all(MYSQLI_ASSOC);

// Detalle seleccionado
$detalle = null;
$documentos = [];
$mensajes = [];
if (isset($_GET['id'])) {
    $sid = intval($_GET['id']);
    $r2  = $conn->query("SELECT s.*, tp.nombre AS tipo FROM solicitudes s JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id WHERE s.id=$sid AND s.cliente_id=$id");
    if ($r2) $detalle = $r2->fetch_assoc();
    if ($detalle) {
        $documentos = $conn->query("SELECT * FROM documentos WHERE solicitud_id=$sid ORDER BY subido_en DESC")->fetch_all(MYSQLI_ASSOC);
        $mensajes   = $conn->query("SELECT m.*, CONCAT(u.nombres,' ',u.apellidos) AS autor FROM mensajes_solicitud m JOIN usuarios u ON m.usuario_id=u.id WHERE m.solicitud_id=$sid ORDER BY m.creado_en ASC")->fetch_all(MYSQLI_ASSOC);
        // Marcar mensajes del asesor como leídos
        $conn->query("UPDATE mensajes_solicitud SET leido=1 WHERE solicitud_id=$sid AND tipo='asesor'");
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Mis Solicitudes</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow:hidden;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#93c5fd;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;overflow-y:auto;}
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
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:18px;height:18px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        .grid2{display:grid;grid-template-columns:1fr 400px;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:16px;}
        .card-title{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
        .card-title a{font-size:.76rem;color:#3b82f6;font-weight:600;text-decoration:none;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.sel{background:#eff6ff;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.ba{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}.bd{background:#a7f3d0;color:#064e3b;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-ver{background:#eff6ff;color:#1d4ed8;}

        /* CHAT */
        .chat-box{background:#f8fafc;border-radius:10px;padding:14px;max-height:280px;overflow-y:auto;margin-bottom:12px;display:flex;flex-direction:column;gap:10px;}
        .msg{max-width:85%;padding:10px 14px;border-radius:12px;font-size:.84rem;line-height:1.5;}
        .msg.asesor{background:#dbeafe;color:#1e40af;align-self:flex-start;border-bottom-left-radius:4px;}
        .msg.cliente{background:#d1fae5;color:#065f46;align-self:flex-end;border-bottom-right-radius:4px;}
        .msg .autor{font-size:.7rem;font-weight:700;margin-bottom:4px;opacity:.8;}
        .msg .hora{font-size:.68rem;opacity:.6;margin-top:4px;text-align:right;}

        /* DOCUMENTOS */
        .doc-item{display:flex;align-items:center;gap:10px;padding:10px;background:#f8fafc;border-radius:8px;margin-bottom:8px;border:1px solid #e2e8f0;}
        .doc-icono{width:36px;height:36px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .doc-icono svg{width:18px;height:18px;color:#1d4ed8;}
        .doc-info .nombre{font-size:.82rem;font-weight:600;color:#0f172a;}
        .doc-info .tipo{font-size:.72rem;color:#64748b;}

        /* SUBIR DOCUMENTO */
        .upload-area{border:2px dashed #d1d5db;border-radius:10px;padding:20px;text-align:center;background:#f9fafb;transition:border .2s;}
        .upload-area:hover{border-color:#1d4ed8;background:#eff6ff;}
        .upload-area input[type="file"]{display:none;}
        .upload-label{cursor:pointer;display:block;}
        .upload-label svg{width:32px;height:32px;color:#94a3b8;margin:0 auto 8px;}
        .upload-label p{font-size:.84rem;color:#64748b;}
        .upload-label strong{color:#1d4ed8;}

        .form-grupo{margin-bottom:12px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo select,.form-grupo input,.form-grupo textarea{width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;background:#f9fafb;outline:none;}
        .form-grupo select:focus,.form-grupo input:focus,.form-grupo textarea:focus{border-color:#1d4ed8;background:#fff;}
        .form-grupo textarea{resize:vertical;min-height:70px;}

        .btn-subir{width:100%;padding:11px;background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:8px;font-size:.92rem;font-weight:700;cursor:pointer;}
        .btn-enviar{width:100%;padding:10px;background:#f0fdf4;color:#065f46;border:1.5px solid #86efac;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;}

        .alerta-docs{background:#fef9c3;border:1.5px solid #fde047;border-radius:8px;padding:12px 14px;font-size:.84rem;color:#713f12;margin-bottom:14px;}
        .alerta-docs strong{font-weight:700;}

        .sin-sel{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-sel svg{width:52px;height:52px;margin:0 auto 14px;display:block;color:#cbd5e1;}
        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.84rem;}

        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}.contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
            .uchip{display:none;}
            .btn-salir-movil{display:block !important;}
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
        <?= avatar($nombre, $_SESSION['foto']??null, 38) ?>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Cliente</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Menu Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <a href="solicitar.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Solicitar Préstamo</a>
        <a href="mis_solicitudes.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <a href="mis_ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Mis Ahorros</a>
        <a href="mis_pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mis Pagos</a>
        <a href="mi_perfil.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Mis Solicitudes</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        
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
        <div class="uchip"><?= avatar($nombre, $_SESSION['foto']??null, 26) ?><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="grid2">

        <!-- LISTA SOLICITUDES -->
        <div>
            <div class="card">
                <div class="card-title">
                    Mis Solicitudes
                    <a href="solicitar.php">+ Nueva</a>
                </div>
                <?php if (empty($solicitudes)): ?>
                <div class="empty">No tienes solicitudes. <a href="solicitar.php" style="color:#1d4ed8;font-weight:600;">Solicitar préstamo</a></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>Código</th><th>Monto</th><th>Estado</th><th>Ver</th></tr></thead>
                    <tbody>
                    <?php
                    $bc=['pendiente'=>'bp','en_evaluacion'=>'be','aprobada_asesor'=>'be','aprobada'=>'ba','rechazada'=>'br','rechazada_asesor'=>'br','desembolsada'=>'bd'];
                    $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación','aprobada_asesor'=>'En revisión','aprobada'=>'Aprobada','rechazada'=>'Rechazada','rechazada_asesor'=>'Rechazada','desembolsada'=>'Desembolsada'];
                    foreach ($solicitudes as $s):
                        // Contar mensajes no leídos
                        $no_leidos = $conn->query("SELECT COUNT(*) AS t FROM mensajes_solicitud WHERE solicitud_id={$s['id']} AND tipo='asesor' AND leido=0")->fetch_assoc()['t']??0;
                    ?>
                    <tr class="<?= ($detalle && $detalle['id']==$s['id'])?'sel':'' ?>">
                        <td>
                            <div style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></div>
                            <div style="font-size:.72rem;color:#94a3b8;"><?= $s['tipo'] ?></div>
                        </td>
                        <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                        <td>
                            <span class="badge <?= $bc[$s['estado']]??'' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span>
                            <?php if($no_leidos>0): ?>
                            <span style="background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:20px;margin-left:4px;"><?= $no_leidos ?> nuevo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="mis_solicitudes.php?id=<?= $s['id'] ?>" class="btn-sm btn-ver">Ver</a>
                            <a href="comprobante.php?id=<?= $s['id'] ?>" target="_blank" class="btn-sm" style="background:#f0fdf4;color:#065f46;margin-left:4px;" title="Imprimir comprobante">🖨️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL DETALLE -->
        <div>
            <?php if (!$detalle): ?>
            <div class="card">
                <div class="sin-sel">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p>Selecciona una solicitud para ver el detalle.</p>
                </div>
            </div>
            <?php else: ?>

            <!-- INFO SOLICITUD -->
            <div class="card">
                <div class="card-title"><?= $detalle['codigo'] ?> <span class="badge <?= $bc[$detalle['estado']]??'' ?>"><?= $bt[$detalle['estado']]??$detalle['estado'] ?></span></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.84rem;">
                    <div><span style="color:#64748b;">Tipo:</span> <strong><?= $detalle['tipo'] ?></strong></div>
                    <div><span style="color:#64748b;">Monto:</span> <strong><?= soles($detalle['monto_solicitado']) ?></strong></div>
                    <div><span style="color:#64748b;">Plazo:</span> <strong><?= $detalle['plazo_meses'] ?> meses</strong></div>
                    <div><span style="color:#64748b;">Cuota:</span> <strong><?= soles($detalle['cuota_estimada']) ?></strong></div>
                </div>
            </div>

            <!-- MENSAJES DEL ASESOR -->
            <div class="card">
                <div class="card-title">Comunicación con el Asesor</div>

                <?php if (!empty($mensajes)): ?>
                <div class="chat-box" id="chatBox">
                    <?php foreach ($mensajes as $m): ?>
                    <div class="msg <?= $m['tipo'] ?>">
                        <div class="autor"><?= $m['tipo']=='asesor' ? '🧑‍💼 '.$m['autor'] : '👤 Tú' ?></div>
                        <?= nl2br(htmlspecialchars($m['mensaje'])) ?>
                        <div class="hora"><?= fechaCorta($m['creado_en']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="font-size:.84rem;color:#94a3b8;text-align:center;padding:16px 0;">No hay mensajes aún.</p>
                <?php endif; ?>

                <form action="../../controllers/DocumentoController.php" method="POST">
                    <input type="hidden" name="accion" value="enviar_mensaje">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">
                    <div class="form-grupo">
                        <textarea name="mensaje" placeholder="Escribe un mensaje al asesor..." required></textarea>
                    </div>
                    <button type="submit" class="btn-enviar">Enviar mensaje</button>
                </form>
            </div>

            <!-- DOCUMENTOS -->
            <?php if (in_array($detalle['estado'], ['en_evaluacion','pendiente','aprobada_asesor'])): ?>
            <div class="card">
                <div class="card-title">Documentos Requeridos</div>

                <div class="alerta-docs">
                    <strong>El asesor puede solicitar documentos.</strong> Sube tu DNI, recibos de servicios o boletas de pago para agilizar tu evaluación.
                </div>

                <!-- DOCUMENTOS YA SUBIDOS -->
                <?php if (!empty($documentos)): ?>
                <div style="margin-bottom:14px;">
                    <div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">Documentos subidos</div>
                    <?php
                    $tipos_doc = ['dni'=>'DNI','recibo_ingreso'=>'Boleta/Ingreso','recibo_servicio'=>'Recibo Servicios','otro'=>'Otro'];
                    foreach ($documentos as $doc): ?>
                    <div class="doc-item">
                        <div class="doc-icono">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="doc-info">
                            <div class="nombre"><?= htmlspecialchars($doc['nombre_archivo']) ?></div>
                            <div class="tipo"><?= $tipos_doc[$doc['tipo']]??$doc['tipo'] ?> — <?= fechaCorta($doc['subido_en']) ?></div>
                        </div>
                        <span style="margin-left:auto;background:#d1fae5;color:#065f46;font-size:.7rem;font-weight:700;padding:3px 8px;border-radius:20px;">Subido</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- SUBIR DOCUMENTO -->
                <form action="../../controllers/DocumentoController.php" method="POST" enctype="multipart/form-data" id="formDoc">
                    <input type="hidden" name="accion" value="subir_documento">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">

                    <div class="form-grupo">
                        <label>Tipo de documento *</label>
                        <select name="tipo" required>
                            <option value="">— Elige el tipo —</option>
                            <option value="dni">DNI (ambas caras)</option>
                            <option value="recibo_ingreso">Boleta de pago / Constancia de ingresos</option>
                            <option value="recibo_servicio">Recibo de luz o agua</option>
                            <option value="otro">Otro documento</option>
                        </select>
                    </div>

                    <div class="form-grupo">
                        <label>Archivo (JPG, PNG o PDF, máx 5MB) *</label>
                        <label for="archivoInput" class="upload-area" style="cursor:pointer;display:block;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;color:#94a3b8;margin:0 auto 8px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                            <p id="nombreArchivo" style="font-size:.84rem;color:#64748b;text-align:center;">Toca aquí para seleccionar archivo</p>
                            <p style="font-size:.74rem;color:#94a3b8;text-align:center;margin-top:4px;"><strong>JPG, PNG o PDF</strong> — máx 5MB</p>
                            <input type="file" id="archivoInput" name="archivo"
                                   accept=".jpg,.jpeg,.png,.pdf"
                                   style="display:none;"
                                   onchange="document.getElementById('nombreArchivo').textContent = this.files[0].name"
                                   required>
                        </label>
                    </div>

                    <button type="submit" class="btn-subir">Subir Documento</button>
                </form>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function mostrarNombre(input){
    if(input.files && input.files[0]){
        document.getElementById('nombreArchivo').textContent = input.files[0].name;
    }
}
// Scroll al último mensaje del chat
var chat = document.getElementById('chatBox');
if(chat) chat.scrollTop = chat.scrollHeight;
</script>
</body>
</html>