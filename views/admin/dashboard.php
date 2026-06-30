<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();
actualizarMora($conn); // mora automática

// Estadísticas generales
$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM solicitudes) AS total_sol,
    (SELECT COUNT(*) FROM solicitudes WHERE estado='pendiente') AS pendientes,
    (SELECT COUNT(*) FROM solicitudes WHERE estado='en_evaluacion') AS en_evaluacion,
    (SELECT COUNT(*) FROM solicitudes WHERE estado IN ('aprobada','aprobada_asesor')) AS aprobadas,
    (SELECT COUNT(*) FROM solicitudes WHERE estado='desembolsada') AS desembolsadas,
    (SELECT COUNT(*) FROM solicitudes WHERE estado IN ('rechazada','rechazada_asesor')) AS rechazadas,
    (SELECT COALESCE(SUM(monto),0) FROM desembolsos) AS total_desembolsado,
    (SELECT COALESCE(SUM(saldo_pendiente),0) FROM cartera_prestamos WHERE estado='vigente') AS cartera_vigente,
    (SELECT COUNT(*) FROM usuarios WHERE rol_id=1 AND activo=1) AS total_clientes,
    (SELECT COUNT(*) FROM usuarios WHERE rol_id=2 AND activo=1) AS total_asesores,
    (SELECT COALESCE(SUM(saldo),0) FROM cuentas_ahorro WHERE estado='activa') AS total_ahorros
")->fetch_assoc();

// Solicitudes pendientes de aprobación final (aprobadas por asesor)
$sol_pendientes = $conn->query(
    "SELECT s.*, tp.nombre AS tipo,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente,
     CONCAT(a.nombres,' ',a.apellidos) AS asesor
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
     JOIN usuarios c ON s.cliente_id = c.id
     LEFT JOIN usuarios a ON s.asesor_id = a.id
     WHERE s.estado = 'aprobada_asesor'
     ORDER BY s.fecha_solicitud DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// Últimos desembolsos
$desembolsos = $conn->query(
    "SELECT d.*, s.codigo,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente
     FROM desembolsos d
     JOIN solicitudes s ON d.solicitud_id = s.id
     JOIN usuarios c ON s.cliente_id = c.id
     ORDER BY d.fecha DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// Clientes recientes
$clientes_recientes = $conn->query(
    "SELECT * FROM usuarios WHERE rol_id=1 AND activo=1
     ORDER BY creado_en DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Panel Admin</title>
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
        .bnot{margin-left:auto;background:#ef4444;color:#fff;font-size:.67rem;font-weight:700;padding:2px 6px;border-radius:20px;}
        .sb-footer{padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.83rem;font-weight:600;text-decoration:none;}
        .topbar{position:fixed;top:0;left:260px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .tb-right{display:flex;align-items:center;gap:12px;}
        .uchip{display:flex;align-items:center;gap:8px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#92400e;}
        .badge-admin{background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
        .contenido{margin-left:260px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        /* BANNER */
        .banner{background:linear-gradient(135deg,#0a2463,#1d4ed8);border-radius:14px;padding:24px 28px;color:#fff;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;}
        .banner h2{font-size:1.3rem;font-weight:800;margin-bottom:4px;}
        .banner p{font-size:.85rem;opacity:.8;}
        .banner-badge{background:rgba(255,255,255,.15);padding:6px 16px;border-radius:20px;font-size:.8rem;font-weight:700;}

        /* STATS */
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-top:3px solid transparent;}
        .stat.az{border-top-color:#3b82f6;}.stat.ve{border-top-color:#10b981;}.stat.na{border-top-color:#f59e0b;}.stat.ro{border-top-color:#ef4444;}.stat.mo{border-top-color:#8b5cf6;}.stat.ci{border-top-color:#06b6d4;}
        .stat .etq{font-size:.7rem;color:#64748b;font-weight:600;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
        .stat .num{font-size:1.7rem;font-weight:800;color:#0f172a;}
        .stat .sub{font-size:.7rem;color:#94a3b8;margin-top:3px;}

        /* ACCIONES RÁPIDAS */
        .acciones{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .accion{background:#fff;border-radius:12px;padding:18px;text-align:center;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:transform .2s,box-shadow .2s;border-top:3px solid transparent;}
        .accion:hover{transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,.1);}
        .accion.a1{border-top-color:#3b82f6;}.accion.a2{border-top-color:#10b981;}.accion.a3{border-top-color:#f59e0b;}.accion.a4{border-top-color:#8b5cf6;}
        .accion .ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
        .accion .ico svg{width:24px;height:24px;}
        .accion.a1 .ico{background:#dbeafe;color:#1d4ed8;}.accion.a2 .ico{background:#d1fae5;color:#059669;}.accion.a3 .ico{background:#fef3c7;color:#d97706;}.accion.a4 .ico{background:#ede9fe;color:#7c3aed;}
        .accion h4{font-size:.85rem;font-weight:700;color:#0f172a;margin-bottom:3px;}
        .accion p{font-size:.73rem;color:#64748b;}

        /* GRID */
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
        .grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:20px;}
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .ct{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
        .ct a{font-size:.76rem;color:#3b82f6;font-weight:600;text-decoration:none;}

        table{width:100%;border-collapse:collapse;font-size:.82rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.7rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.ba{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}.bd{background:#a7f3d0;color:#064e3b;}.baa{background:#ede9fe;color:#5b21b6;}

        .btn-sm{padding:5px 12px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-verde{background:#d1fae5;color:#065f46;}.btn-rojo{background:#fee2e2;color:#991b1b;}.btn-azul{background:#dbeafe;color:#1e40af;}

        /* MINI STAT */
        .mini-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
        .mini-stat{background:#f8fafc;border-radius:8px;padding:12px;text-align:center;}
        .mini-stat .n{font-size:1.3rem;font-weight:800;color:#0f172a;}
        .mini-stat .l{font-size:.7rem;color:#64748b;margin-top:2px;}

        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.84rem;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .stats{grid-template-columns:1fr 1fr;}
            .acciones{grid-template-columns:1fr 1fr;}
            .grid2,.grid3{grid-template-columns:1fr;}
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

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <div><h2>CREDISOL</h2><span>Panel de Administración</span></div>
    </div>
    <div class="sb-user">
        <div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div>
            <p><?= htmlspecialchars($nombre.' '.$apellido) ?></p>
            <span>&#9733; Administrador General</span>
        </div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php" class="activo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard General
        </a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Todas las Solicitudes
            <?php if(($stats['pendientes']??0)>0): ?><span class="bnot"><?= $stats['pendientes'] ?></span><?php endif; ?>
        </a>
        <a href="aprobaciones.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Aprobaciones Finales
            <?php if(($stats['aprobadas']??0)>0): ?><span class="bnot"><?= $stats['aprobadas'] ?></span><?php endif; ?>
        </a>
        <a href="desembolsos.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Desembolsos
        </a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Ver Clientes
        </a>
        <a href="ahorros.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Gestionar Ahorros
        </a>
        <a href="pagos.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Registrar Pagos
        </a>
        <div class="menu-lbl">Usuarios</div>
        <a href="usuarios.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Gestionar Usuarios
        </a>
        <a href="asesores.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Gestionar Asesores
        </a>
        <div class="menu-lbl">Sistema</div>
        <a href="reportes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Reportes
        </a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Cerrar Sesión
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menú
        </button>
        <h1>Dashboard General</h1>
    </div>
    <div class="tb-right">
        <span class="badge-admin">Administrador General</span>
        
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
            <div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div>
            <span><?= htmlspecialchars($nombre) ?></span>
        </div>
    </div>
</header>

<!-- CONTENIDO -->
<main class="contenido">

    <!-- BANNER -->
    <div class="banner">
        <div>
            <h2>Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
            <p>Panel de control general de CREDISOL — Tienes acceso completo al sistema.</p>
        </div>
        <span class="banner-badge">Administrador General</span>
    </div>

    <!-- ESTADÍSTICAS PRINCIPALES -->
    <div class="stats">
        <div class="stat az">
            <div class="etq">Total Solicitudes</div>
            <div class="num"><?= $stats['total_sol']??0 ?></div>
            <div class="sub">Desde el inicio</div>
        </div>
        <div class="stat na">
            <div class="etq">Pendientes</div>
            <div class="num"><?= $stats['pendientes']??0 ?></div>
            <div class="sub">Sin asesor asignado</div>
        </div>
        <div class="stat mo">
            <div class="etq">Para Aprobar</div>
            <div class="num"><?= $stats['aprobadas']??0 ?></div>
            <div class="sub">Aprobadas por asesor</div>
        </div>
        <div class="stat ve">
            <div class="etq">Desembolsadas</div>
            <div class="num"><?= $stats['desembolsadas']??0 ?></div>
            <div class="sub">Préstamos entregados</div>
        </div>
    </div>

    <!-- SEGUNDA FILA STATS -->
    <div class="stats" style="margin-bottom:22px;">
        <div class="stat ve">
            <div class="etq">Total Desembolsado</div>
            <div class="num" style="font-size:1.1rem;"><?= soles($stats['total_desembolsado']??0) ?></div>
            <div class="sub">Dinero entregado</div>
        </div>
        <div class="stat az">
            <div class="etq">Cartera Vigente</div>
            <div class="num" style="font-size:1.1rem;"><?= soles($stats['cartera_vigente']??0) ?></div>
            <div class="sub">Saldo por cobrar</div>
        </div>
        <div class="stat ci">
            <div class="etq">Total Ahorros</div>
            <div class="num" style="font-size:1.1rem;"><?= soles($stats['total_ahorros']??0) ?></div>
            <div class="sub">Depósitos activos</div>
        </div>
        <div class="stat na">
            <div class="etq">Usuarios</div>
            <div class="num"><?= ($stats['total_clientes']??0) + ($stats['total_asesores']??0) ?></div>
            <div class="sub"><?= $stats['total_clientes']??0 ?> clientes / <?= $stats['total_asesores']??0 ?> asesores</div>
        </div>
    </div>

    <!-- ACCIONES RÁPIDAS -->
    <div style="font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:12px;">Acciones Rápidas</div>
    <div class="acciones">
        <a href="usuarios.php" class="accion a1">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg></div>
            <h4>Crear Usuario</h4>
            <p>Agregar clientes o asesores</p>
        </a>
        <a href="aprobaciones.php" class="accion a2">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <h4>Aprobar Solicitudes</h4>
            <p>Revisión y aprobación final</p>
        </a>
        <a href="desembolsos.php" class="accion a3">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
            <h4>Desembolsar</h4>
            <p>Entregar dinero al cliente</p>
        </a>
        <a href="ahorros.php" class="accion a4">
            <div class="ico"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
            <h4>Gestionar Ahorros</h4>
            <p>Depósitos y retiros</p>
        </a>
    </div>

    <!-- SOLICITUDES PARA APROBAR Y CLIENTES RECIENTES -->
    <div class="grid2">

        <!-- SOLICITUDES APROBADAS POR ASESOR -->
        <div class="card">
            <div class="ct">
                Solicitudes para Aprobación Final
                <a href="aprobaciones.php">Ver todas</a>
            </div>
            <?php if (empty($sol_pendientes)): ?>
            <div class="empty">No hay solicitudes pendientes de aprobación.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sol_pendientes as $s): ?>
                <tr>
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td><?= htmlspecialchars($s['cliente']) ?></td>
                    <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                    <td>
                        <a href="aprobaciones.php?id=<?= $s['id'] ?>" class="btn-sm btn-verde">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- CLIENTES RECIENTES -->
        <div class="card">
            <div class="ct">
                Clientes Recientes
                <a href="clientes.php">Ver todos</a>
            </div>
            <?php if (empty($clientes_recientes)): ?>
            <div class="empty">No hay clientes registrados aún.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Correo</th><th>Registro</th></tr>
                </thead>
                <tbody>
                <?php foreach ($clientes_recientes as $c): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($c['nombres'].' '.$c['apellidos']) ?></td>
                    <td style="color:#64748b;font-size:.78rem;"><?= htmlspecialchars($c['correo']) ?></td>
                    <td style="color:#64748b;"><?= fechaCorta($c['creado_en']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ÚLTIMOS DESEMBOLSOS -->
    <div class="card">
        <div class="ct">
            Últimos Desembolsos
            <a href="desembolsos.php">Ver todos</a>
        </div>
        <?php if (empty($desembolsos)): ?>
        <div class="empty">No hay desembolsos registrados aún.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Método</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php foreach ($desembolsos as $d): ?>
            <tr>
                <td style="font-weight:700;color:#1d4ed8;"><?= $d['codigo'] ?></td>
                <td><?= htmlspecialchars($d['cliente']) ?></td>
                <td style="font-weight:700;color:#059669;"><?= soles($d['monto']) ?></td>
                <td><span class="badge ba"><?= ucfirst($d['metodo']) ?></span></td>
                <td style="color:#64748b;"><?= fechaCorta($d['fecha']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
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