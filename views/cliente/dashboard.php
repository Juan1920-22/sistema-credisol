<?php
ini_set('display_errors', 0);
session_start();
// En TODOS los archivos del sistema se usa require_once
// require_once garantiza que PHP ejecute el archivo
// UNA SOLA VEZ sin importar cuántas veces se llame

// dashboard.php del cliente
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1]);
// La variable $conn es SIEMPRE la misma conexión
// No se crean múltiples conexiones — eso es Singleton
$id       = $_SESSION['usuario_id'];
$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

// Cargar foto de perfil desde BD si no está en sesión
if (empty($_SESSION['foto'])) {
    $u = $conn->query("SELECT foto FROM usuarios WHERE id=$id")->fetch_assoc();
    if ($u && $u['foto']) $_SESSION['foto'] = $u['foto'];
}

$r = $conn->query("SELECT COUNT(*) AS total, SUM(estado IN ('aprobada','desembolsada')) AS aprobadas, SUM(estado IN ('pendiente','en_evaluacion','aprobada_asesor')) AS pendientes FROM solicitudes WHERE cliente_id = $id");
$totales = $r ? $r->fetch_assoc() : ['total'=>0,'aprobadas'=>0,'pendientes'=>0];

$r2 = $conn->query("SELECT COALESCE(SUM(saldo_pendiente),0) AS deuda FROM cartera_prestamos WHERE cliente_id = $id AND estado='vigente'");
$deuda = $r2 ? $r2->fetch_assoc() : ['deuda'=>0];

$r6 = $conn->query("SELECT saldo, numero_cuenta FROM cuentas_ahorro WHERE cliente_id = $id AND estado='activa'");
$ahorro = $r6 ? $r6->fetch_assoc() : null;

$solicitudes = [];
$r3 = $conn->query("SELECT s.codigo, s.monto_solicitado, s.estado, tp.nombre AS tipo FROM solicitudes s JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id WHERE s.cliente_id = $id ORDER BY s.fecha_solicitud DESC LIMIT 5");
if ($r3) $solicitudes = $r3->fetch_all(MYSQLI_ASSOC);

$reciente = null;
$r4 = $conn->query("SELECT * FROM solicitudes WHERE cliente_id = $id ORDER BY fecha_solicitud DESC LIMIT 1");
if ($r4) $reciente = $r4->fetch_assoc();

$totalNotif = 0;
$r5 = $conn->query("SELECT COUNT(*) AS t FROM notificaciones WHERE usuario_id = $id AND leido = 0");
if ($r5) $totalNotif = $r5->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Mi Panel</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#93c5fd;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;}
        .menu-lbl{padding:10px 20px 4px;font-size:.62rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;}
        .sb-menu a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.86rem;transition:all .15s;border-left:3px solid transparent;}
        .sb-menu a:hover,.sb-menu a.activo{background:rgba(255,255,255,.07);color:#fff;border-left-color:#3b82f6;}
        .sb-menu a.activo{font-weight:600;}
        .sb-menu a svg{width:16px;height:16px;flex-shrink:0;}
        .bnot{margin-left:auto;background:#ef4444;color:#fff;font-size:.67rem;font-weight:700;padding:2px 6px;border-radius:20px;}
        .sb-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.83rem;font-weight:600;text-decoration:none;}
        .topbar{position:fixed;top:0;left:250px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .tb-right{display:flex;align-items:center;gap:14px;}
        .notif{position:relative;background:none;border:none;cursor:pointer;padding:7px;border-radius:8px;color:#64748b;}
        .notif svg{width:20px;height:20px;display:block;}
        .nbadge{position:absolute;top:3px;right:3px;width:15px;height:15px;background:#ef4444;border-radius:50%;font-size:.58rem;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:5px 10px;}
        .uchip .av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#0f172a;}
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        /* BANNER HERO */
        .banner-hero{background:linear-gradient(135deg,#0a2463,#1d4ed8);border-radius:16px;overflow:hidden;display:flex;min-height:190px;margin-bottom:22px;position:relative;}
        .bh-texto{flex:1;padding:32px 36px;color:#fff;display:flex;flex-direction:column;justify-content:center;position:relative;z-index:1;}
        .bh-texto h2{font-size:1.5rem;font-weight:800;margin-bottom:8px;line-height:1.3;}
        .bh-texto p{font-size:.88rem;opacity:.85;margin-bottom:20px;line-height:1.6;}
        .bh-texto a{background:#fff;color:#1d4ed8;padding:10px 24px;border-radius:8px;font-weight:700;font-size:.88rem;text-decoration:none;display:inline-block;width:fit-content;}
        .bh-img{width:260px;flex-shrink:0;overflow:hidden;}
        .bh-img img{width:100%;height:100%;object-fit:cover;object-position:center top;}
        /* STATS */
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-top:3px solid transparent;}
        .stat.az{border-top-color:#3b82f6;}.stat.ve{border-top-color:#10b981;}.stat.na{border-top-color:#f59e0b;}.stat.ro{border-top-color:#ef4444;}
        .stat .etq{font-size:.72rem;color:#64748b;font-weight:600;margin-bottom:7px;text-transform:uppercase;letter-spacing:.05em;}
        .stat .num{font-size:1.8rem;font-weight:800;color:#0f172a;}
        .stat .sub{font-size:.72rem;color:#94a3b8;margin-top:3px;}
        /* AHORRO */
        .ahorro-b{border-radius:12px;padding:20px 24px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;}
        .ahorro-b.con{background:linear-gradient(135deg,#059669,#10b981);color:#fff;}
        .ahorro-b.sin{background:#f0fdf4;border:2px dashed #86efac;}
        .ahorro-b .bta{padding:10px 20px;border-radius:8px;font-weight:700;font-size:.85rem;text-decoration:none;white-space:nowrap;}
        .con .bta{background:rgba(255,255,255,.2);color:#fff;}
        .sin .bta{background:#059669;color:#fff;}
        /* SERVICIOS */
        .servicios{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .srv{background:#fff;border-radius:12px;padding:18px 14px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06);text-decoration:none;transition:transform .2s,box-shadow .2s;border-top:3px solid transparent;}
        .srv:hover{transform:translateY(-3px);box-shadow:0 4px 12px rgba(0,0,0,.1);}
        .srv.s1{border-top-color:#3b82f6;}.srv.s2{border-top-color:#10b981;}.srv.s3{border-top-color:#f59e0b;}.srv.s4{border-top-color:#8b5cf6;}
        .srv .ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
        .srv .ico svg{width:22px;height:22px;}
        .srv.s1 .ico{background:#dbeafe;color:#1d4ed8;}.srv.s2 .ico{background:#d1fae5;color:#059669;}.srv.s3 .ico{background:#fef3c7;color:#d97706;}.srv.s4 .ico{background:#ede9fe;color:#7c3aed;}
        .srv h4{font-size:.83rem;font-weight:700;color:#0f172a;margin-bottom:3px;}
        .srv p{font-size:.73rem;color:#64748b;line-height:1.4;}
        /* BANNERS PRODUCTOS */
        .prod-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px;}
        .prod-card{border-radius:14px;overflow:hidden;position:relative;min-height:200px;display:flex;flex-direction:column;justify-content:flex-end;text-decoration:none;transition:transform .2s;}
        .prod-card:hover{transform:translateY(-3px);}
        .prod-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
        .prod-card .ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(10,36,99,.88) 40%,rgba(10,36,99,.15));}
        .prod-card .info{position:relative;z-index:1;padding:20px;color:#fff;}
        .prod-card .info h4{font-size:1rem;font-weight:800;margin-bottom:4px;}
        .prod-card .info p{font-size:.78rem;opacity:.85;margin-bottom:10px;}
        .prod-card .info .tg{background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;display:inline-block;}
        /* GRID 2 */
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px;}
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .ct{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
        .ct a{font-size:.76rem;color:#3b82f6;font-weight:600;text-decoration:none;}
        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.72rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.ba{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}.bd{background:#a7f3d0;color:#064e3b;}
        /* TRACK */
        .track{display:flex;align-items:flex-start;margin-top:14px;}
        .tp{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
        .tp:not(:last-child)::after{content:'';position:absolute;top:13px;left:50%;width:100%;height:2px;background:#e2e8f0;z-index:0;}
        .tp.done:not(:last-child)::after,.tp.act:not(:last-child)::after{background:#3b82f6;}
        .tc{width:26px;height:26px;border-radius:50%;background:#e2e8f0;color:#94a3b8;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;position:relative;z-index:1;border:2px solid #e2e8f0;}
        .tp.done .tc{background:#3b82f6;color:#fff;border-color:#3b82f6;}
        .tp.act .tc{background:#fff;color:#3b82f6;border-color:#3b82f6;}
        .tl{font-size:.66rem;color:#94a3b8;margin-top:5px;text-align:center;font-weight:500;}
        .tp.act .tl{color:#3b82f6;font-weight:700;}.tp.done .tl{color:#3b82f6;}
        /* BANNER ASESOR */
        .b-asesor{background:linear-gradient(135deg,#1e3a8a,#0a2463);border-radius:16px;overflow:hidden;display:flex;margin-bottom:22px;min-height:150px;}
        .ba-txt{flex:1;padding:28px 32px;color:#fff;display:flex;flex-direction:column;justify-content:center;}
        .ba-txt h3{font-size:1.1rem;font-weight:800;margin-bottom:6px;}
        .ba-txt p{font-size:.84rem;opacity:.82;margin-bottom:16px;line-height:1.5;}
        .ba-txt a{background:#3b82f6;color:#fff;padding:9px 20px;border-radius:8px;font-weight:700;font-size:.84rem;text-decoration:none;display:inline-block;width:fit-content;}
        .ba-img{width:200px;flex-shrink:0;overflow:hidden;}
        .ba-img img{width:100%;height:100%;object-fit:cover;object-position:center top;}
        .empty{text-align:center;padding:28px 16px;color:#94a3b8;font-size:.86rem;}
        .empty a{color:#3b82f6;font-weight:600;text-decoration:none;}
        .sec-titulo{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .stats{grid-template-columns:1fr 1fr;}
            .servicios{grid-template-columns:1fr 1fr;}
            .prod-grid{grid-template-columns:1fr;}
            .grid2{grid-template-columns:1fr;}
            .bh-img{display:none;}
            .ba-img{display:none;}
            .bh-texto h2{font-size:1.2rem;}
        }
    
        .nav-bottom{display:none;position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e2e8f0;padding:8px 0 12px;z-index:150;box-shadow:0 -2px 10px rgba(0,0,0,.08);}
        .nav-bottom-inner{display:flex;justify-content:space-around;align-items:center;}
        .nav-item{display:flex;flex-direction:column;align-items:center;gap:3px;text-decoration:none;color:#64748b;font-size:.65rem;font-weight:600;padding:4px 8px;border-radius:8px;min-width:60px;}
        .nav-item.activo{color:#1d4ed8;}
        .nav-item svg,.nav-menu-btn svg{width:22px;height:22px;}
        .nav-menu-btn{display:flex;flex-direction:column;align-items:center;gap:3px;background:none;border:none;cursor:pointer;color:#64748b;font-size:.65rem;font-weight:600;padding:4px 8px;}
        @media(max-width:768px){.nav-bottom{display:block !important;}.contenido{padding-bottom:80px !important;}}

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
        <?= avatar($nombre, $_SESSION['foto']??null, 38) ?>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Cliente</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Menu Principal</div>
        <a href="dashboard.php" class="activo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <a href="solicitar.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Solicitar Préstamo</a>
        <a href="mis_solicitudes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes
            <?php if(($totales['pendientes']??0)>0): ?><span class="bnot"><?= $totales['pendientes'] ?></span><?php endif; ?></a>
        <a href="mis_ahorros.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Mis Ahorros</a>
        <a href="mis_pagos.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mis Pagos</a>
        <a href="mi_perfil.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menú
        </button>
        <h1>Panel del Cliente</h1>
    </div>
    <div class="tb-right">
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
            <?= avatar($nombre, $_SESSION['foto']??null, 26) ?>
            <span><?= htmlspecialchars($nombre) ?></span>
        </div>
    </div>
</header>

<main class="contenido">

    <!-- HERO BANNER -->
    <div class="banner-hero">
        <div class="bh-texto">
            <h2>Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
            <p>Gestiona tus préstamos y ahorros desde aquí.<br>En CREDISOL estamos comprometidos con tu bienestar financiero.</p>
            <a href="solicitar.php">+ Solicitar Préstamo</a>
        </div>
        <div class="bh-img">
            <img src="../../public/img/ejecutiva.png" alt="CREDISOL">
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat az"><div class="etq">Solicitudes Totales</div><div class="num"><?= $totales['total']??0 ?></div><div class="sub">Historial completo</div></div>
        <div class="stat ve"><div class="etq">Aprobadas</div><div class="num"><?= $totales['aprobadas']??0 ?></div><div class="sub">Aprobadas o desembolsadas</div></div>
        <div class="stat na"><div class="etq">En Proceso</div><div class="num"><?= $totales['pendientes']??0 ?></div><div class="sub">Pendientes o en evaluación</div></div>
        <div class="stat ro"><div class="etq">Deuda Actual</div><div class="num" style="font-size:1.2rem;"><?= soles($deuda['deuda']??0) ?></div><div class="sub">Saldo pendiente</div></div>
    </div>

    <!-- AHORRO BANNER -->
    <?php if ($ahorro): ?>
    <div class="ahorro-b con">
        <div>
            <div style="font-size:.78rem;opacity:.8;margin-bottom:4px;">Cuenta de Ahorros — <?= $ahorro['numero_cuenta'] ?></div>
            <div style="font-size:1.8rem;font-weight:800;"><?= soles($ahorro['saldo']) ?></div>
            <div style="font-size:.75rem;opacity:.75;margin-top:3px;">Saldo disponible</div>
        </div>
        <a href="mis_ahorros.php" class="bta">Ver movimientos →</a>
    </div>
    <?php else: ?>
    <div class="ahorro-b sin">
        <div>
            <div style="font-weight:700;color:#065f46;margin-bottom:4px;">No tienes cuenta de ahorros aún</div>
            <div style="font-size:.85rem;color:#16a34a;">Abre tu cuenta hoy y empieza a ahorrar con el 3.50% anual</div>
        </div>
        <a href="mis_ahorros.php" class="bta">Abrir cuenta →</a>
    </div>
    <?php endif; ?>

    <!-- ACCESOS RÁPIDOS -->
    <div class="servicios">
        <a href="solicitar.php" class="srv s1">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></div>
            <h4>Solicitar Préstamo</h4><p>Accede a financiamiento rápido y seguro</p>
        </a>
        <a href="mis_ahorros.php" class="srv s2">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
            <h4>Mis Ahorros</h4><p>Consulta tu saldo y movimientos</p>
        </a>
        <a href="mis_solicitudes.php" class="srv s3">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
            <h4>Mis Solicitudes</h4><p>Revisa el estado de tus préstamos</p>
        </a>
        <a href="mis_pagos.php" class="srv s4">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <h4>Mis Pagos</h4><p>Consulta tus cuotas y pagos</p>
        </a>
    </div>

    <!-- PRODUCTOS CON IMÁGENES -->
    <div class="sec-titulo">Nuestros Productos Financieros</div>
    <div class="prod-grid">
        <a href="solicitar.php" class="prod-card">
            <img src="../../public/img/banner2.png" alt="Préstamos">
            <div class="ov"></div>
            <div class="info">
                <h4>Préstamos Personales y Empresariales</h4>
                <p>Financia tus proyectos con las mejores tasas del mercado</p>
                <span class="tg">Desde 14% anual</span>
            </div>
        </a>
        <a href="mis_ahorros.php" class="prod-card">
            <img src="../../public/img/reunion.png" alt="Ahorros">
            <div class="ov"></div>
            <div class="info">
                <h4>Cuenta de Ahorros CREDISOL</h4>
                <p>Haz crecer tu dinero con nuestra tasa preferencial</p>
                <span class="tg">3.50% anual</span>
            </div>
        </a>
    </div>

    <!-- SOLICITUDES Y SEGUIMIENTO -->
    <div class="grid2">
        <div class="card">
            <div class="ct">Mis Solicitudes Recientes <a href="mis_solicitudes.php">Ver todas</a></div>
            <?php if(empty($solicitudes)): ?>
            <div class="empty">Aún no tienes solicitudes.<br><a href="solicitar.php">Solicita tu primer préstamo</a></div>
            <?php else: ?>
            <table>
                <thead><tr><th>Código</th><th>Tipo</th><th>Monto</th><th>Estado</th></tr></thead>
                <tbody>
                <?php
                $bc=['pendiente'=>'bp','en_evaluacion'=>'be','aprobada_asesor'=>'be','aprobada'=>'ba','rechazada'=>'br','rechazada_asesor'=>'br','desembolsada'=>'bd'];
                $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación','aprobada_asesor'=>'Aprob. Asesor','aprobada'=>'Aprobada','rechazada'=>'Rechazada','rechazada_asesor'=>'Rechazada','desembolsada'=>'Desembolsada'];
                foreach($solicitudes as $s): ?>
                <tr>
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td><?= $s['tipo'] ?></td>
                    <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                    <td><span class="badge <?= $bc[$s['estado']]??'' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="ct">Estado de mi Solicitud más Reciente</div>
            <?php if(!$reciente): ?>
            <div class="empty">No tienes solicitudes activas.</div>
            <?php else:
                $pasos=['pendiente','en_evaluacion','aprobada','desembolsada'];
                $pi=array_search($reciente['estado'],$pasos);
                if($pi===false) $pi=0;
                $txts=['pendiente'=>'Tu solicitud fue recibida y espera ser asignada a un asesor.','en_evaluacion'=>'Un asesor está revisando tu solicitud.','aprobada_asesor'=>'Aprobada por el asesor. Espera aprobación final.','aprobada'=>'Aprobada. Pronto se realizará el desembolso.','rechazada'=>'Tu solicitud no fue aprobada.','rechazada_asesor'=>'Rechazada por el asesor.','desembolsada'=>'El préstamo fue desembolsado exitosamente.'];
            ?>
            <div style="background:#f8fafc;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-weight:700;color:#0f172a;"><?= $reciente['codigo'] ?></div>
                <div style="font-size:.82rem;color:#64748b;margin-top:3px;"><?= $txts[$reciente['estado']]??'' ?></div>
            </div>
            <div class="track">
                <?php $ets=['Enviada','En evaluación','Aprobada','Desembolsada'];
                foreach($ets as $i=>$et): $c=''; if($i<$pi) $c='done'; elseif($i==$pi) $c='act'; ?>
                <div class="tp <?= $c ?>"><div class="tc"><?= $i<$pi?'✓':($i+1) ?></div><div class="tl"><?= $et ?></div></div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:14px;"><a href="mis_solicitudes.php" style="font-size:.83rem;color:#1d4ed8;font-weight:600;text-decoration:none;">Ver detalle completo →</a></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BANNER ASESOR -->
    <div class="b-asesor">
        <div class="ba-txt">
            <h3>¿Necesitas ayuda con tu solicitud?</h3>
            <p>Nuestros asesores especializados están disponibles para orientarte y ayudarte a tomar la mejor decisión financiera para ti y tu familia.</p>
            <a href="solicitar.php">Habla con un asesor →</a>
        </div>
        <div class="ba-img">
            <img src="../../public/img/asesor.png" alt="Asesor CREDISOL">
        </div>
    </div>

</main>
<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>

<nav class="nav-bottom">
    <div class="nav-bottom-inner">
        <a href="dashboard.php" class="nav-item activo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Inicio
        </a>
        <a href="solicitar.php" class="nav-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Solicitar
        </a>
        <a href="mis_solicitudes.php" class="nav-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Solicitudes
        </a>
        <a href="mis_ahorros.php" class="nav-item">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Ahorros
        </a>
        <button class="nav-menu-btn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Más
        </button>
    </div>
</nav>

<!-- BOTÓN CERRAR SESIÓN MÓVIL -->
<a href="../../controllers/AuthController.php?accion=logout" class="btn-logout-movil">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
    </svg>
    Cerrar Sesión
</a>
<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
var _notifAbierto=false;
function toggleNotif(){
    _notifAbierto=!_notifAbierto;
    document.getElementById('notifPanel').style.display=_notifAbierto?'block':'none';
    if(_notifAbierto)_cargarNotifs();
}
function _cargarNotifs(){
    fetch('/cooperativa/helpers/notificaciones.php')
    .then(function(r){return r.json();})
    .then(function(data){
        var badge=document.getElementById('notifBadge');
        if(data.total>0){badge.style.display='block';badge.textContent=data.total>9?'9+':data.total;}
        else{badge.style.display='none';}
        var iconos={exito:'',error:'',info:'ℹ',advertencia:''};
        var lista=document.getElementById('notifLista');
        if(!data.items||data.items.length===0){lista.innerHTML='<div style="text-align:center;padding:24px;color:#94a3b8;font-size:.82rem;">Sin notificaciones</div>';return;}
        lista.innerHTML=data.items.map(function(n){
            return '<div onclick="_leerNotif('+n.id+',this)" style="display:flex;gap:10px;padding:11px 14px;border-bottom:1px solid #f8fafc;cursor:pointer;background:'+(n.leida=='0'?'#eff6ff':'#fff')+'">'+
            '<div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.95rem;">'+(iconos[n.tipo]||'🔔')+'</div>'+
            '<div style="flex:1"><div style="font-size:.8rem;font-weight:700;color:#0f172a;margin-bottom:2px;">'+n.titulo+'</div>'+
            '<div style="font-size:.74rem;color:#64748b;">'+n.mensaje+'</div>'+
            '<div style="font-size:.67rem;color:#94a3b8;margin-top:3px;">'+n.creado_en+'</div></div></div>';
        }).join('');
    }).catch(function(){});
}
function _leerNotif(id,el){fetch('/cooperativa/helpers/notificaciones.php?accion=leer&id='+id);el.style.background='#fff';}
function leerTodas(){fetch('/cooperativa/helpers/notificaciones.php?accion=leer_todas').then(function(){_cargarNotifs();});}
(function(){
    fetch('/cooperativa/helpers/notificaciones.php').then(function(r){return r.json();}).then(function(data){
        var b=document.getElementById('notifBadge');
        if(data&&data.total>0){b.style.display='block';b.textContent=data.total>9?'9+':data.total;}
    }).catch(function(){});
})();
setInterval(function(){
    fetch('/cooperativa/helpers/notificaciones.php').then(function(r){return r.json();}).then(function(data){
        var b=document.getElementById('notifBadge');
        if(data&&data.total>0){b.style.display='block';b.textContent=data.total>9?'9+':data.total;}
        else if(b)b.style.display='none';
    }).catch(function(){});
},30000);
document.addEventListener('click',function(e){
    var w=document.getElementById('notifWrap');
    if(w&&!w.contains(e.target)){document.getElementById('notifPanel').style.display='none';_notifAbierto=false;}
});
</script>
</html>