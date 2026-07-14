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

// Procesar desembolso
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'desembolsar') {
    $sid     = intval($_POST['solicitud_id']);
    $metodo  = limpiar($_POST['metodo'] ?? 'transferencia');
    $cuenta  = limpiar($_POST['numero_cuenta'] ?? '');
    $banco   = limpiar($_POST['banco'] ?? '');

    // Obtener datos completos de la solicitud
    $sol = $conn->query(
        "SELECT s.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente_nombre, u.correo AS cliente_correo
         FROM solicitudes s
         JOIN usuarios u ON s.cliente_id=u.id
         WHERE s.id=$sid AND s.estado='aprobada'"
    )->fetch_assoc();

    if (!$sol) {
        setMensaje("Solicitud no valida para desembolso.", "error");
        header("Location: desembolsos.php");
        exit;
    }

    // REACCION 1: Registrar desembolso
    $stmt = $conn->prepare(
        "INSERT INTO desembolsos (solicitud_id, admin_id, monto, metodo, numero_cuenta, banco)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iidsss", $sid, $admin_id, $sol['monto_solicitado'], $metodo, $cuenta, $banco);
    $stmt->execute();

    // REACCION 2: Actualizar estado de la solicitud
    $conn->query("UPDATE solicitudes SET estado='desembolsada', fecha_desembolso=NOW() WHERE id=$sid");

    // REACCION 3: Crear cartera de prestamos
    $inicio = date('Y-m-d');
    $fin    = date('Y-m-d', strtotime("+{$sol['plazo_meses']} months"));
    $stmt2  = $conn->prepare(
        "INSERT INTO cartera_prestamos (solicitud_id, cliente_id, monto_total, saldo_pendiente, cuotas_total, cuotas_pagadas, estado, fecha_inicio, fecha_fin)
         VALUES (?, ?, ?, ?, ?, 0, 'vigente', ?, ?)"
    );
    $stmt2->bind_param("iiddiss", $sid, $sol['cliente_id'], $sol['monto_solicitado'], $sol['monto_solicitado'], $sol['plazo_meses'], $inicio, $fin);
    $stmt2->execute();
    $cartera_id = $conn->insert_id;

    // REACCION 4: Generar cuotas automaticamente (Programacion Reactiva)
    for ($i = 1; $i <= $sol['plazo_meses']; $i++) {
        $venc  = date('Y-m-d', strtotime("+$i months", strtotime($inicio)));
        $cuota = $sol['cuota_estimada'];
        $ins   = $conn->prepare(
            "INSERT INTO pagos (cartera_id, numero_cuota, monto_cuota, monto_pagado, fecha_vencimiento, estado)
             VALUES (?, ?, ?, 0, ?, 'pendiente')"
        );
        $ins->bind_param("iids", $cartera_id, $i, $cuota, $venc);
        $ins->execute();
    }

    // Descripcion del metodo de desembolso
    $desc_metodo = $metodo == 'efectivo' ? 'Retiro por Caja en nuestras oficinas'
                 : ($metodo == 'cheque'  ? 'Cheque'
                 : "Transferencia bancaria a {$banco} — Cuenta: {$cuenta}");

    // REACCION 5: Notificar al CLIENTE con todos los detalles
    $msg_cliente = "Tu prestamo {$sol['codigo']} por " . soles($sol['monto_solicitado']) . " ha sido desembolsado exitosamente. Metodo: {$desc_metodo}. Se generaron {$sol['plazo_meses']} cuotas mensuales de " . soles($sol['cuota_estimada']) . " cada una. Revisa tu cronograma en 'Mis Pagos'.";
    $notif = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Prestamo Desembolsado', ?, 'exito')");
    $notif->bind_param("is", $sol['cliente_id'], $msg_cliente);
    $notif->execute();

    // REACCION 6: Notificar al ASESOR
    $msg_asesor = "El prestamo {$sol['codigo']} de {$sol['cliente_nombre']} fue desembolsado exitosamente. Metodo: {$desc_metodo}. El cliente puede ver sus cuotas en el sistema.";
    $na = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Desembolso completado', ?, 'exito')");
    $na->bind_param("is", $sol['asesor_id'], $msg_asesor);
    $na->execute();

    // REACCION 7: Mensaje automatico en el chat al cliente
    $msg_chat = "Tu prestamo fue desembolsado exitosamente. {$desc_metodo}. Ya puedes ver tu cronograma completo de {$sol['plazo_meses']} cuotas en 'Mis Pagos'. Si tienes alguna consulta, no dudes en escribirme.";
    $mc = $conn->prepare("INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, 'asesor')");
    $mc->bind_param("iis", $sid, $sol['asesor_id'], $msg_chat);
    $mc->execute();

    // Log de auditoria
    $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id) VALUES ($admin_id, 'desembolso_completado', 'desembolsos', $sid)");

    setMensaje("Desembolso realizado correctamente. Se generaron {$sol['plazo_meses']} cuotas. Cliente y asesor notificados.", "exito");
    header("Location: desembolsos.php");
    exit;
}

// Solicitudes aprobadas listas para desembolsar
$para_desembolsar = $conn->query(
    "SELECT s.*, tp.nombre AS tipo,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.dni AS cliente_dni,
     c.telefono AS cliente_tel
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
     JOIN usuarios c ON s.cliente_id = c.id
     WHERE s.estado = 'aprobada'
     ORDER BY s.fecha_aprobacion ASC"
)->fetch_all(MYSQLI_ASSOC);

// Historial de desembolsos
$historial = $conn->query(
    "SELECT d.*, s.codigo, s.plazo_meses,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente
     FROM desembolsos d
     JOIN solicitudes s ON d.solicitud_id = s.id
     JOIN usuarios c ON s.cliente_id = c.id
     ORDER BY d.fecha DESC LIMIT 20"
)->fetch_all(MYSQLI_ASSOC);

// Detalle seleccionado
$detalle = null;
if (isset($_GET['id'])) {
    $sid = intval($_GET['id']);
    foreach ($para_desembolsar as $s) {
        if ($s['id'] == $sid) { $detalle = $s; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Desembolsos</title>
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
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        .grid2{display:grid;grid-template-columns:1fr 360px;gap:20px;margin-bottom:22px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.sel{background:#f0fdf4;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .ba{background:#d1fae5;color:#065f46;}
        .bd{background:#a7f3d0;color:#064e3b;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-ver{background:#eff6ff;color:#1d4ed8;}

        .det-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:.84rem;}
        .det-row:last-child{border-bottom:none;}
        .det-row span:first-child{color:#64748b;}
        .det-row span:last-child{font-weight:600;color:#0f172a;}

        .monto-grande{font-size:2rem;font-weight:800;color:#059669;text-align:center;padding:16px;background:#f0fdf4;border-radius:10px;margin:14px 0;}

        .form-grupo{margin-bottom:13px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input,.form-grupo select{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#f9fafb;outline:none;transition:border .2s;}
        .form-grupo input:focus,.form-grupo select:focus{border-color:#059669;background:#fff;}

        .btn-desembolsar{width:100%;padding:13px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-top:6px;}
        .btn-desembolsar:hover{opacity:.9;}

        .sin-sel{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-sel svg{width:52px;height:52px;margin:0 auto 14px;display:block;color:#cbd5e1;}
        .sin-sel p{font-size:.85rem;}

        .empty{text-align:center;padding:32px;color:#94a3b8;font-size:.86rem;}

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
        <a href="aprobaciones.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Aprobaciones Finales</a>
        <a href="desembolsos.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Desembolsos</a>
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
        <h1>Desembolsos</h1>
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

        <!-- LISTA PARA DESEMBOLSAR -->
        <div class="card">
            <h3>Préstamos aprobados — Listos para desembolsar</h3>
            <?php if (empty($para_desembolsar)): ?>
            <div class="empty">No hay préstamos listos para desembolsar.<br>Primero aprueba solicitudes en Aprobaciones Finales.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Código</th><th>Cliente</th><th>Tipo</th><th>Monto</th><th>Cuota</th><th>Ver</th></tr>
                </thead>
                <tbody>
                <?php foreach ($para_desembolsar as $s): ?>
                <tr class="<?= ($detalle && $detalle['id']==$s['id']) ? 'sel' : '' ?>">
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td><?= htmlspecialchars($s['cliente']) ?></td>
                    <td><?= $s['tipo'] ?></td>
                    <td style="font-weight:700;color:#059669;"><?= soles($s['monto_solicitado']) ?></td>
                    <td><?= soles($s['cuota_estimada']) ?></td>
                    <td><a href="desembolsos.php?id=<?= $s['id'] ?>" class="btn-sm btn-ver">Desembolsar</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- FORMULARIO DESEMBOLSO -->
        <div class="card">
            <?php if (!$detalle): ?>
            <div class="sin-sel">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <p>Selecciona un préstamo de la lista para registrar el desembolso.</p>
            </div>
            <?php else: ?>
            <h3>Registrar Desembolso</h3>

            <div class="det-row"><span>Código</span><span style="color:#1d4ed8;"><?= $detalle['codigo'] ?></span></div>
            <div class="det-row"><span>Cliente</span><span><?= htmlspecialchars($detalle['cliente']) ?></span></div>
            <div class="det-row"><span>DNI</span><span><?= $detalle['cliente_dni'] ?></span></div>
            <div class="det-row"><span>Tipo</span><span><?= $detalle['tipo'] ?></span></div>
            <div class="det-row"><span>Plazo</span><span><?= $detalle['plazo_meses'] ?> meses</span></div>
            <div class="det-row"><span>Cuota mensual</span><span><?= soles($detalle['cuota_estimada']) ?></span></div>

            <div class="monto-grande"><?= soles($detalle['monto_solicitado']) ?></div>

            <?php if (empty($detalle['contrato_firmado'])): ?>
            <!-- CONTRATO AUN NO FIRMADO -->
            <div style="background:#fef3c7;border:2px solid #fde068;border-radius:10px;padding:18px;margin-bottom:14px;text-align:center;">
                <div style="font-size:1.1rem;margin-bottom:6px;">Contrato pendiente de firma del cliente</div>
                <p style="font-size:.85rem;color:#92400e;margin-bottom:14px;line-height:1.6;">
                    El asesor aun no ha enviado el contrato firmado por el cliente. No se puede desembolsar hasta que el cliente firme.
                </p>
                <a href="contrato.php?id=<?= $detalle['id'] ?>" target="_blank"
                   style="display:inline-block;padding:10px 20px;background:#1d4ed8;color:#fff;border-radius:8px;font-size:.88rem;font-weight:700;text-decoration:none;">
                   Ver Contrato
                </a>
            </div>

            <?php else: ?>
            <!-- CONTRATO FIRMADO POR EL CLIENTE -->
            <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-size:.88rem;font-weight:700;color:#065f46;margin-bottom:8px;">
                    Contrato firmado digitalmente por el cliente
                </div>
                <div style="font-size:.78rem;color:#64748b;margin-bottom:10px;">
                    Firmado el <?= fechaCorta($detalle['fecha_firma']) ?>
                    <?php if ($detalle['metodo_desembolso']): ?>
                    — Metodo: <strong><?= $detalle['metodo_desembolso']=='caja'?'Retiro por Caja':'Transferencia Bancaria' ?></strong>
                    <?php if ($detalle['banco_desembolso']): ?> — <?= htmlspecialchars($detalle['banco_desembolso']) ?><?php endif; ?>
                    <?php endif; ?>
                </div>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px;text-align:center;margin-bottom:10px;">
                    <div style="font-size:.75rem;color:#64748b;margin-bottom:6px;">Firma digital del cliente:</div>
                    <img src="/cooperativa/<?= $detalle['contrato_firmado'] ?>"
                         alt="Firma del cliente"
                         style="max-width:100%;max-height:120px;border-radius:6px;"
                         onerror="this.style.display='none'">
                </div>
                <a href="/cooperativa/<?= $detalle['contrato_firmado'] ?>" target="_blank"
                   style="display:inline-block;padding:6px 14px;background:#dbeafe;color:#1d4ed8;border-radius:6px;font-size:.78rem;font-weight:600;text-decoration:none;">
                   Ver firma completa
                </a>
            </div>

            <!-- FORMULARIO DE DESEMBOLSO -->
            <form method="POST" onsubmit="return confirm('¿Confirmar desembolso de <?= soles($detalle['monto_solicitado']) ?> a <?= htmlspecialchars($detalle['cliente']) ?>?')">
                <input type="hidden" name="accion" value="desembolsar">
                <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">

                <?php if (!empty($detalle['metodo_desembolso'])): ?>
                <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;padding:12px;margin-bottom:14px;">
                    <div style="font-size:.8rem;font-weight:700;color:#065f46;margin-bottom:4px;">Metodo solicitado por el cliente:</div>
                    <div style="font-size:.88rem;color:#1e293b;font-weight:600;">
                        <?= $detalle['metodo_desembolso'] == 'caja' ? 'Retiro por Caja' : 'Transferencia Bancaria' ?>
                        <?php if ($detalle['banco_desembolso']): ?> — <?= htmlspecialchars($detalle['banco_desembolso']) ?><?php endif; ?>
                        <?php if ($detalle['cuenta_desembolso']): ?>
                        <br><span style="font-size:.8rem;color:#64748b;">Cuenta: <?= htmlspecialchars($detalle['cuenta_desembolso']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-grupo">
                    <label>Metodo de pago *</label>
                    <select name="metodo" required>
                        <option value="transferencia" <?= ($detalle['metodo_desembolso']??'')==='transferencia'?'selected':'' ?>>Transferencia bancaria</option>
                        <option value="efectivo" <?= ($detalle['metodo_desembolso']??'')==='caja'?'selected':'' ?>>Efectivo en oficina (Caja)</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-grupo">
                    <label>Numero de cuenta (si aplica)</label>
                    <input type="text" name="numero_cuenta"
                           value="<?= htmlspecialchars($detalle['cuenta_desembolso']??'') ?>"
                           placeholder="Ej: 123-456789-0-12">
                </div>
                <div class="form-grupo">
                    <label>Banco (si aplica)</label>
                    <input type="text" name="banco"
                           value="<?= htmlspecialchars($detalle['banco_desembolso']??'') ?>"
                           placeholder="Ej: BCP, Interbank, BBVA...">
                </div>

                <button type="submit" class="btn-desembolsar">
                    Confirmar Desembolso
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORIAL DE DESEMBOLSOS -->
    <div class="card">
        <h3>Historial de Desembolsos</h3>
        <?php if (empty($historial)): ?>
        <div class="empty">No hay desembolsos registrados aún.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Método</th><th>Plazo</th><th>Fecha</th></tr>
            </thead>
            <tbody>
            <?php foreach ($historial as $d): ?>
            <tr>
                <td style="font-weight:700;color:#1d4ed8;"><?= $d['codigo'] ?></td>
                <td><?= htmlspecialchars($d['cliente']) ?></td>
                <td style="font-weight:700;color:#059669;"><?= soles($d['monto']) ?></td>
                <td><span class="badge ba"><?= ucfirst($d['metodo']) ?></span></td>
                <td><?= $d['plazo_meses'] ?> meses</td>
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