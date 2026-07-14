<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$admin_id = $_SESSION['usuario_id'];
$base     = getBase();

// Aprobar solicitud
if (isset($_GET['aprobar'])) {
    $sid = intval($_GET['aprobar']);
    $conn->query("UPDATE solicitudes SET estado='aprobada', fecha_aprobacion=NOW() WHERE id=$sid");
    // Notificar al cliente
    $sol = $conn->query("SELECT s.cliente_id, s.codigo, CONCAT(u.nombres,' ',u.apellidos) AS cliente
        FROM solicitudes s JOIN usuarios u ON s.cliente_id=u.id WHERE s.id=$sid")->fetch_assoc();
    if ($sol) {
        $msg = "Tu solicitud {$sol['codigo']} fue aprobada por la administración. Pronto recibirás el desembolso.";
        $stmt = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud Aprobada', ?, 'exito')");
        $stmt->bind_param("is", $sol['cliente_id'], $msg);
        $stmt->execute();
        // Log
        $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id) VALUES ($admin_id, 'aprobar_solicitud', 'solicitudes', $sid)");
    }
    setMensaje("Solicitud aprobada exitosamente.", "exito");
    header("Location: aprobaciones.php");
    exit;
}

// Rechazar solicitud
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'rechazar') {
    $sid    = intval($_POST['solicitud_id']);
    $motivo = limpiar($_POST['motivo_rechazo'] ?? '');
    $conn->prepare("UPDATE solicitudes SET estado='rechazada', motivo_rechazo=?, fecha_aprobacion=NOW() WHERE id=?")
         ->bind_param("si", $motivo, $sid);
    $conn->query("UPDATE solicitudes SET estado='rechazada', motivo_rechazo='".addslashes($motivo)."', fecha_aprobacion=NOW() WHERE id=$sid");
    // Notificar
    $sol = $conn->query("SELECT s.cliente_id, s.codigo FROM solicitudes s WHERE s.id=$sid")->fetch_assoc();
    if ($sol) {
        $msg = "Tu solicitud {$sol['codigo']} no fue aprobada. Motivo: $motivo";
        $stmt = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud Rechazada', ?, 'error')");
        $stmt->bind_param("is", $sol['cliente_id'], $msg);
        $stmt->execute();
        $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id) VALUES ($admin_id, 'rechazar_solicitud', 'solicitudes', $sid)");
    }
    setMensaje("Solicitud rechazada.", "advertencia");
    header("Location: aprobaciones.php");
    exit;
}

// Enviar contrato firmado por admin al asesor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'enviar_contrato_asesor') {
    $sid = intval($_POST['solicitud_id']);
    $sol = $conn->query(
        "SELECT s.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente_nombre
         FROM solicitudes s
         JOIN usuarios u ON s.cliente_id=u.id
         WHERE s.id=$sid AND s.estado='aprobada'"
    )->fetch_assoc();

    if ($sol) {
        // Notificar al asesor con instrucciones claras
        $msg = "El administrador aprobo y firmo el contrato de la solicitud {$sol['codigo']} del cliente {$sol['cliente_nombre']}. Descarga el contrato desde Aprobaciones, presentaselo al cliente para que lo firme y luego subelo al sistema.";
        $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Contrato listo para firma del cliente', ?, 'info')");
        $n->bind_param("is", $sol['asesor_id'], $msg);
        $n->execute();

        // Notificar al cliente tambien
        $msg_c = "Tu solicitud {$sol['codigo']} fue aprobada. El contrato esta siendo preparado. Pronto tu asesor te lo presentara para que lo firmes.";
        $nc = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Contrato en preparacion', ?, 'exito')");
        $nc->bind_param("is", $sol['cliente_id'], $msg_c);
        $nc->execute();

        setMensaje("Contrato enviado al asesor. El asesor procedera a presentarselo al cliente para su firma.", "exito");
    }
    header("Location: aprobaciones.php?id=$sid");
    exit;
}

// Obtener solicitudes aprobadas por asesor (listas para aprobacion final)
$solicitudes = $conn->query(
    "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.dni AS cliente_dni,
     c.ingreso_mensual, c.telefono AS cliente_tel,
     CONCAT(a.nombres,' ',a.apellidos) AS asesor,
     h.score_crediticio, h.puntualidad, h.esta_en_infocorp, h.deudas_actuales
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
     JOIN usuarios c ON s.cliente_id = c.id
     LEFT JOIN usuarios a ON s.asesor_id = a.id
     LEFT JOIN historial_crediticio h ON h.cliente_id = c.id
     WHERE s.estado IN ('aprobada_asesor','aprobada')
     ORDER BY s.fecha_solicitud ASC"
)->fetch_all(MYSQLI_ASSOC);

// Ver detalle específico
$detalle = null;
if (isset($_GET['id'])) {
    $sid = intval($_GET['id']);
    foreach ($solicitudes as $s) {
        if ($s['id'] == $sid) { $detalle = $s; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Aprobaciones Finales</title>
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
        .uchip{display:flex;align-items:center;gap:8px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#92400e;}
        .contenido{margin-left:260px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        .grid2{display:grid;grid-template-columns:1fr 380px;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.seleccionada{background:#eff6ff;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .baa{background:#ede9fe;color:#5b21b6;}
        .ba{background:#d1fae5;color:#065f46;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-ver{background:#eff6ff;color:#1d4ed8;}
        .btn-ap{background:#d1fae5;color:#065f46;}
        .btn-re{background:#fee2e2;color:#991b1b;}

        /* DETALLE */
        .det-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.84rem;}
        .det-row:last-child{border-bottom:none;}
        .det-row span:first-child{color:#64748b;}
        .det-row span:last-child{font-weight:600;color:#0f172a;text-align:right;}

        .score-box{background:#f8fafc;border-radius:8px;padding:12px;margin:12px 0;}
        .score-num{font-size:1.6rem;font-weight:800;text-align:center;margin-bottom:4px;}
        .score-lbl{font-size:.72rem;color:#64748b;text-align:center;}

        .btn-aprobar{width:100%;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;margin-bottom:10px;}
        .btn-aprobar:hover{opacity:.9;}
        .btn-rechazar{width:100%;padding:10px;background:#fee2e2;color:#991b1b;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;}
        .btn-rechazar:hover{background:#fecaca;}

        .form-grupo{margin-bottom:12px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.8rem;margin-bottom:5px;}
        .form-grupo textarea{width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;background:#f9fafb;outline:none;resize:vertical;min-height:80px;}
        .form-grupo textarea:focus{border-color:#ef4444;background:#fff;}

        .sin-seleccion{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-seleccion svg{width:56px;height:56px;margin:0 auto 14px;display:block;color:#cbd5e1;}
        .sin-seleccion p{font-size:.86rem;}

        .empty{text-align:center;padding:40px;color:#94a3b8;font-size:.86rem;}

        @media(max-width:900px){
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
        <a href="aprobaciones.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Aprobaciones Finales<span class="bnot"><?= count($solicitudes) ?></span></a>
        <a href="desembolsos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Desembolsos</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
        <a href="ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Gestionar Ahorros</a>
        <a href="pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Registrar Pagos</a>
        <div class="menu-lbl">Usuarios</div>
        <a href="usuarios.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Gestionar Usuarios</a>
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
        <h1>Aprobaciones Finales</h1>
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
        <div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <span><?= htmlspecialchars($nombre) ?></span>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="grid2">

        <!-- LISTA DE SOLICITUDES -->
        <div class="card">
            <h3>Solicitudes aprobadas por Asesor — Pendientes de tu aprobación</h3>
            <?php if (empty($solicitudes)): ?>
            <div class="empty">No hay solicitudes pendientes de aprobación final.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Código</th><th>Cliente</th><th>Tipo</th><th>Monto</th><th>Asesor</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                <tr class="<?= ($detalle && $detalle['id']==$s['id']) ? 'seleccionada' : '' ?>">
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td><?= htmlspecialchars($s['cliente']) ?></td>
                    <td><?= $s['tipo'] ?></td>
                    <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                    <td style="color:#64748b;font-size:.78rem;"><?= htmlspecialchars($s['asesor']??'Sin asignar') ?></td>
                    <td>
                        <a href="aprobaciones.php?id=<?= $s['id'] ?>" class="btn-sm btn-ver">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- PANEL DETALLE -->
        <div class="card">
            <?php if (!$detalle): ?>
            <div class="sin-seleccion">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p>Selecciona una solicitud de la lista para ver el detalle y tomar una decisión.</p>
            </div>
            <?php else: ?>
            <?php
            // Traer documentos del cliente
            $docs_admin = $conn->query(
                "SELECT * FROM documentos WHERE solicitud_id={$detalle['id']} ORDER BY subido_en DESC"
            )->fetch_all(MYSQLI_ASSOC);
            $tipos_doc = ['dni'=>'DNI','recibo_ingreso'=>'Boleta/Ingreso','recibo_servicio'=>'Recibo Servicios','otro'=>'Otro'];
            ?>
            <h3><?= $detalle['codigo'] ?> <span class="badge baa">Aprobada por Asesor</span></h3>

            <!-- DATOS CLIENTE -->
            <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">Cliente</div>
            <div class="det-row"><span>Nombre</span><span><?= htmlspecialchars($detalle['cliente']) ?></span></div>
            <div class="det-row"><span>DNI</span><span><?= $detalle['cliente_dni'] ?></span></div>
            <div class="det-row"><span>Telefono</span><span><?= $detalle['cliente_tel']??'—' ?></span></div>
            <div class="det-row"><span>Ingreso mensual</span><span><?= soles($detalle['ingreso_mensual']??0) ?></span></div>

            <!-- DATOS PRESTAMO -->
            <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;margin:14px 0 8px;">Prestamo</div>
            <div class="det-row"><span>Tipo</span><span><?= $detalle['tipo'] ?></span></div>
            <div class="det-row"><span>Monto</span><span style="color:#1d4ed8;font-size:1rem;"><?= soles($detalle['monto_solicitado']) ?></span></div>
            <div class="det-row"><span>Plazo</span><span><?= $detalle['plazo_meses'] ?> meses</span></div>
            <div class="det-row"><span>Cuota estimada</span><span><?= soles($detalle['cuota_estimada']) ?></span></div>
            <div class="det-row"><span>Tasa</span><span><?= $detalle['tasa_interes'] ?>% anual</span></div>
            <div class="det-row"><span>Motivo</span><span style="max-width:180px;text-align:right;"><?= htmlspecialchars($detalle['motivo']) ?></span></div>

            <!-- HISTORIAL CREDITICIO -->
            <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;margin:14px 0 8px;">Historial Crediticio</div>
            <?php
            $score = $detalle['score_crediticio'] ?? 0;
            $scoreColor = $score >= 700 ? '#059669' : ($score >= 500 ? '#d97706' : '#dc2626');
            ?>
            <div class="score-box">
                <div class="score-num" style="color:<?= $scoreColor ?>"><?= $score ?></div>
                <div class="score-lbl">Score crediticio (0-1000)</div>
            </div>
            <div class="det-row"><span>Puntualidad</span><span><?= ucfirst($detalle['puntualidad']??'—') ?></span></div>
            <div class="det-row"><span>Deudas actuales</span><span style="color:<?= ($detalle['deudas_actuales']??0)>0?'#dc2626':'#059669' ?>"><?= soles($detalle['deudas_actuales']??0) ?></span></div>
            <div class="det-row"><span>En Infocorp</span><span style="color:<?= $detalle['esta_en_infocorp']?'#dc2626':'#059669' ?>"><?= $detalle['esta_en_infocorp'] ? 'SI — RIESGO' : 'No' ?></span></div>

            <!-- DOCUMENTOS DEL CLIENTE -->
            <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;margin:14px 0 8px;">
                Documentos del Cliente (<?= count($docs_admin) ?>)
            </div>
            <?php if (empty($docs_admin)): ?>
            <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px;font-size:.8rem;color:#92400e;text-align:center;margin-bottom:12px;">
                El cliente no ha subido documentos aun.
            </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
                <?php foreach ($docs_admin as $doc):
                    $ext_d = strtolower(pathinfo($doc['nombre_archivo'], PATHINFO_EXTENSION));
                    $es_img_d = in_array($ext_d, ['jpg','jpeg','png','webp']);
                ?>
                <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;">
                    <?php if ($es_img_d): ?>
                    <a href="../../<?= $doc['ruta'] ?>" target="_blank">
                        <img src="../../<?= $doc['ruta'] ?>" style="width:100%;height:70px;object-fit:cover;display:block;">
                    </a>
                    <?php else: ?>
                    <a href="../../<?= $doc['ruta'] ?>" target="_blank"
                       style="display:flex;align-items:center;justify-content:center;height:70px;background:#fff5f5;text-decoration:none;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="1.5" style="width:28px;height:28px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </a>
                    <?php endif; ?>
                    <div style="padding:4px 6px;font-size:.66rem;color:#64748b;font-weight:600;"><?= $tipos_doc[$doc['tipo']]??$doc['tipo'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ACCIONES SEGUN ESTADO -->
            <div style="margin-top:10px;">

                <?php if ($detalle['estado'] == 'aprobada_asesor'): ?>
                <!-- PASO 1: Admin verifica documentos y aprueba -->
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:12px;font-size:.82rem;color:#1e40af;">
                    Verifica los documentos del cliente arriba antes de aprobar.
                </div>
                <a href="aprobaciones.php?aprobar=<?= $detalle['id'] ?>"
                   class="btn-aprobar"
                   style="display:block;text-align:center;text-decoration:none;margin-bottom:10px;"
                   onclick="return confirm('¿Confirmas que los documentos estan en orden y apruebas la solicitud <?= $detalle['codigo'] ?>?')">
                   Documentos verificados — Aprobar Solicitud
                </a>

                <?php elseif ($detalle['estado'] == 'aprobada'): ?>

                <!-- PASO 2: Generar contrato con firma del admin -->
                <?php if (empty($detalle['contrato_firmado'])): ?>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:10px;font-size:.82rem;color:#1e40af;line-height:1.6;">
                    Solicitud aprobada. Genera el contrato, firmalo y envialo al asesor para que el cliente lo firme.
                </div>
                <a href="contrato.php?id=<?= $detalle['id'] ?>" target="_blank"
                   style="display:block;text-align:center;text-decoration:none;padding:12px;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;border-radius:8px;font-size:.9rem;font-weight:700;margin-bottom:10px;">
                   Generar e Imprimir Contrato
                </a>
                <!-- Enviar contrato al asesor por notificacion -->
                <form method="POST">
                    <input type="hidden" name="accion" value="enviar_contrato_asesor">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">
                    <button type="submit"
                            onclick="return confirm('¿Confirmas que ya firmaste el contrato y lo envias al asesor?')"
                            style="width:100%;padding:11px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;margin-bottom:8px;">
                        Contrato firmado — Enviar al Asesor
                    </button>
                </form>

                <?php else: ?>
                <!-- PASO 3: Contrato firmado por cliente + metodo registrado -->
                <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;padding:12px;margin-bottom:10px;">
                    <div style="font-size:.84rem;font-weight:700;color:#065f46;">Contrato firmado y metodo de desembolso confirmado</div>
                    <div style="font-size:.75rem;color:#64748b;margin-top:3px;">
                        Metodo: <?= $detalle['metodo_desembolso']=='caja'?'Retiro por Caja':'Transferencia Bancaria' ?>
                        <?php if ($detalle['banco_desembolso']): ?> — <?= htmlspecialchars($detalle['banco_desembolso']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($detalle['listo_para_desembolso']): ?>
                <a href="desembolsos.php?id=<?= $detalle['id'] ?>"
                   style="display:block;text-align:center;text-decoration:none;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border-radius:8px;font-size:.95rem;font-weight:700;margin-bottom:10px;">
                   Proceder al Desembolso
                </a>
                <?php else: ?>
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px;text-align:center;font-size:.82rem;color:#92400e;margin-bottom:10px;">
                    Esperando que el asesor registre el metodo de desembolso del cliente.
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php endif; ?>

                <button class="btn-rechazar" onclick="mostrarRechazar(<?= $detalle['id'] ?>, '<?= $detalle['codigo'] ?>')">
                    Rechazar Solicitud
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL RECHAZAR -->
<div id="modalRechazar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:440px;margin:20px;">
        <h3 style="font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px;">Rechazar Solicitud</h3>
        <p id="modal-codigo-txt" style="color:#64748b;font-size:.85rem;margin-bottom:20px;"></p>
        <form method="POST">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" name="solicitud_id" id="modal-sid">
            <div class="form-grupo">
                <label>Motivo del rechazo *</label>
                <textarea name="motivo_rechazo" placeholder="Explica el motivo del rechazo..." required></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="flex:1;padding:11px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Confirmar Rechazo</button>
                <button type="button" onclick="cerrarRechazar()" style="padding:11px 20px;background:#f1f5f9;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function mostrarRechazar(id, codigo) {
    document.getElementById('modal-sid').value = id;
    document.getElementById('modal-codigo-txt').textContent = 'Solicitud: ' + codigo;
    document.getElementById('modalRechazar').style.display = 'flex';
}
function cerrarRechazar() {
    document.getElementById('modalRechazar').style.display = 'none';
}
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