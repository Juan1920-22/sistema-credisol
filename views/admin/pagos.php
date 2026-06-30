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
actualizarMora($conn); // mora automática

// Registrar pago de cuota
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'pagar') {
    $pago_id    = intval($_POST['pago_id']);
    $cartera_id = intval($_POST['cartera_id']);
    $monto      = floatval($_POST['monto_pagado']);

    // Obtener datos del pago
    $pago = $conn->query("SELECT * FROM pagos WHERE id=$pago_id AND estado='pendiente'")->fetch_assoc();
    if (!$pago) {
        setMensaje("Cuota no válida o ya pagada.", "error");
        header("Location: pagos.php");
        exit;
    }

    // Marcar cuota como pagada
    $conn->query("UPDATE pagos SET estado='pagado', monto_pagado=$monto, fecha_pago=NOW() WHERE id=$pago_id");

    // Actualizar cartera
    $cartera = $conn->query("SELECT * FROM cartera_prestamos WHERE id=$cartera_id")->fetch_assoc();
    $nuevas_pagadas = $cartera['cuotas_pagadas'] + 1;
    $nuevo_saldo    = $cartera['saldo_pendiente'] - $monto;
    if ($nuevo_saldo < 0) $nuevo_saldo = 0;

    $estado_cartera = $nuevo_saldo <= 0 ? 'cancelado' : 'vigente';

    $conn->query("UPDATE cartera_prestamos SET
        cuotas_pagadas=$nuevas_pagadas,
        saldo_pendiente=$nuevo_saldo,
        estado='$estado_cartera'
        WHERE id=$cartera_id");

    // Obtener cliente para notificar
    $sol = $conn->query(
        "SELECT s.cliente_id, s.codigo FROM solicitudes s
         JOIN cartera_prestamos cp ON cp.solicitud_id=s.id
         WHERE cp.id=$cartera_id"
    )->fetch_assoc();

    if ($sol) {
        $msg = "Se registró el pago de la cuota #{$pago['numero_cuota']} de tu préstamo {$sol['codigo']}. Saldo restante: " . soles($nuevo_saldo);
        $notif = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Pago Registrado', ?, 'exito')");
        $notif->bind_param("is", $sol['cliente_id'], $msg);
        $notif->execute();
    }

    // Log
    $conn->query("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id) VALUES ($admin_id, 'registrar_pago', 'pagos', $pago_id)");

    setMensaje("Pago de la cuota #{$pago['numero_cuota']} registrado correctamente.", "exito");
    header("Location: pagos.php?cartera=" . $cartera_id);
    exit;
}

// Obtener carteras activas
$carteras = $conn->query(
    "SELECT cp.*, s.codigo,
     CONCAT(u.nombres,' ',u.apellidos) AS cliente, u.dni,
     tp.nombre AS tipo
     FROM cartera_prestamos cp
     JOIN solicitudes s ON cp.solicitud_id = s.id
     JOIN usuarios u ON cp.cliente_id = u.id
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
     WHERE cp.estado IN ('vigente','en_mora','por_vencer')
     ORDER BY cp.saldo_pendiente DESC"
)->fetch_all(MYSQLI_ASSOC);

// Cuotas de cartera seleccionada
$cartera_sel = null;
$cuotas      = [];
if (isset($_GET['cartera'])) {
    $cid = intval($_GET['cartera']);
    $cartera_sel = $conn->query(
        "SELECT cp.*, s.codigo,
         CONCAT(u.nombres,' ',u.apellidos) AS cliente
         FROM cartera_prestamos cp
         JOIN solicitudes s ON cp.solicitud_id=s.id
         JOIN usuarios u ON cp.cliente_id=u.id
         WHERE cp.id=$cid"
    )->fetch_assoc();

    if ($cartera_sel) {
        // Actualizar cuotas vencidas
        $hoy = date('Y-m-d');
        $conn->query("UPDATE pagos SET estado='vencido' WHERE cartera_id=$cid AND fecha_vencimiento < '$hoy' AND estado='pendiente'");

        $cuotas = $conn->query(
            "SELECT * FROM pagos WHERE cartera_id=$cid ORDER BY numero_cuota ASC"
        )->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Registrar Pagos</title>
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

        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.sel{background:#eff6ff;}
        tbody tr.vencida{background:#fff5f5;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .b-pend{background:#fef3c7;color:#92400e;}
        .b-pag{background:#d1fae5;color:#065f46;}
        .b-venc{background:#fee2e2;color:#991b1b;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-ver{background:#eff6ff;color:#1d4ed8;}
        .btn-pagar{background:#d1fae5;color:#065f46;}
        .btn-dis{background:#f1f5f9;color:#94a3b8;cursor:not-allowed;}

        /* RESUMEN CARTERA */
        .resumen{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;}
        .res-item{background:#f8fafc;border-radius:8px;padding:12px;}
        .res-item .n{font-size:1.2rem;font-weight:800;color:#0f172a;}
        .res-item .l{font-size:.7rem;color:#64748b;margin-top:2px;}

        .progreso-bar{margin-bottom:16px;}
        .progreso-bar .info{display:flex;justify-content:space-between;font-size:.75rem;color:#64748b;margin-bottom:5px;}
        .barra{height:8px;background:#e2e8f0;border-radius:20px;overflow:hidden;}
        .barra-fill{height:100%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:20px;}

        .empty{text-align:center;padding:32px;color:#94a3b8;font-size:.86rem;}
        .sin-sel{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-sel svg{width:52px;height:52px;margin:0 auto 14px;display:block;color:#cbd5e1;}

        /* MODAL PAGO */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;}
        .modal-overlay.show{display:flex;}
        .modal{background:#fff;border-radius:14px;padding:28px;width:100%;max-width:400px;margin:20px;}
        .modal h3{font-size:1rem;font-weight:700;margin-bottom:16px;}
        .form-grupo{margin-bottom:14px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input{width:100%;padding:11px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.95rem;background:#f9fafb;outline:none;}
        .form-grupo input:focus{border-color:#059669;background:#fff;}
        .btn-confirmar{width:100%;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;margin-bottom:8px;}
        .btn-cancelar{width:100%;padding:10px;background:#f1f5f9;color:#374151;border:none;border-radius:8px;font-size:.88rem;font-weight:600;cursor:pointer;}

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
        <a href="desembolsos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Desembolsos</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
        <a href="ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Gestionar Ahorros</a>
        <a href="pagos.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Registrar Pagos</a>
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
        <h1>Registrar Pagos</h1>
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

        <!-- CARTERAS ACTIVAS -->
        <div class="card">
            <h3>Préstamos Activos</h3>
            <?php if (empty($carteras)): ?>
            <div class="empty">No hay préstamos activos con cuotas pendientes.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Código</th><th>Cliente</th><th>Saldo</th><th>Cuotas</th><th>Ver</th></tr>
                </thead>
                <tbody>
                <?php foreach ($carteras as $c):
                    $pct = $c['cuotas_total']>0 ? round($c['cuotas_pagadas']/$c['cuotas_total']*100) : 0;
                ?>
                <tr class="<?= ($cartera_sel && $cartera_sel['id']==$c['id']) ? 'sel' : '' ?>">
                    <td style="font-weight:700;color:#1d4ed8;"><?= $c['codigo'] ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($c['cliente']) ?></div>
                        <div style="font-size:.72rem;color:#94a3b8;"><?= $c['tipo'] ?></div>
                    </td>
                    <td style="font-weight:700;color:#dc2626;"><?= soles($c['saldo_pendiente']) ?></td>
                    <td>
                        <div style="font-size:.78rem;"><?= $c['cuotas_pagadas'] ?>/<?= $c['cuotas_total'] ?></div>
                        <div class="barra" style="width:80px;margin-top:3px;">
                            <div class="barra-fill" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </td>
                    <td><a href="pagos.php?cartera=<?= $c['id'] ?>" class="btn-sm btn-ver">Ver cuotas</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- CUOTAS DE LA CARTERA SELECCIONADA -->
        <div class="card">
            <?php if (!$cartera_sel): ?>
            <div class="sin-sel">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p>Selecciona un préstamo para ver y registrar el pago de sus cuotas.</p>
            </div>
            <?php else:
                $pct = $cartera_sel['cuotas_total']>0 ? round($cartera_sel['cuotas_pagadas']/$cartera_sel['cuotas_total']*100) : 0;
            ?>
            <h3>Cuotas — <?= $cartera_sel['codigo'] ?> (<?= htmlspecialchars($cartera_sel['cliente']) ?>)</h3>

            <!-- RESUMEN -->
            <div class="resumen">
                <div class="res-item">
                    <div class="n" style="color:#dc2626;"><?= soles($cartera_sel['saldo_pendiente']) ?></div>
                    <div class="l">Saldo pendiente</div>
                </div>
                <div class="res-item">
                    <div class="n"><?= $cartera_sel['cuotas_pagadas'] ?>/<?= $cartera_sel['cuotas_total'] ?></div>
                    <div class="l">Cuotas pagadas</div>
                </div>
            </div>
            <div class="progreso-bar">
                <div class="info"><span>Progreso</span><span><?= $pct ?>%</span></div>
                <div class="barra"><div class="barra-fill" style="width:<?= $pct ?>%;"></div></div>
            </div>

            <!-- TABLA CUOTAS -->
            <table>
                <thead>
                    <tr><th>#</th><th>Vencimiento</th><th>Monto</th><th>Estado</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($cuotas as $c):
                    $es_vencida = $c['estado'] == 'vencido';
                    $bc = ['pendiente'=>'b-pend','pagado'=>'b-pag','vencido'=>'b-venc'];
                    $bt = ['pendiente'=>'Pendiente','pagado'=>'Pagado','vencido'=>'Vencido'];
                ?>
                <tr class="<?= $es_vencida ? 'vencida' : '' ?>">
                    <td style="font-weight:700;">#<?= $c['numero_cuota'] ?></td>
                    <td style="<?= $es_vencida?'color:#dc2626;font-weight:600;':'' ?>"><?= fechaCorta($c['fecha_vencimiento']) ?></td>
                    <td style="font-weight:600;"><?= soles($c['monto_cuota']) ?></td>
                    <td><span class="badge <?= $bc[$c['estado']] ?>"><?= $bt[$c['estado']] ?></span></td>
                    <td>
                        <?php if ($c['estado'] == 'pagado'): ?>
                        <span class="btn-sm btn-dis">Pagado</span>
                        <?php else: ?>
                        <button class="btn-sm btn-pagar"
                            onclick="abrirPago(<?= $c['id'] ?>, <?= $cartera_sel['id'] ?>, <?= $c['monto_cuota'] ?>, <?= $c['numero_cuota'] ?>)">
                            Registrar Pago
                        </button>
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

<!-- MODAL PAGO -->
<div class="modal-overlay" id="modalPago">
    <div class="modal">
        <h3>Registrar Pago de Cuota</h3>
        <p id="modal-info" style="color:#64748b;font-size:.85rem;margin-bottom:16px;"></p>
        <form method="POST">
            <input type="hidden" name="accion" value="pagar">
            <input type="hidden" name="pago_id" id="modal-pago-id">
            <input type="hidden" name="cartera_id" id="modal-cartera-id">
            <div class="form-grupo">
                <label>Monto recibido (S/) *</label>
                <input type="number" name="monto_pagado" id="modal-monto" step="0.01" min="0.01" required>
            </div>
            <button type="submit" class="btn-confirmar">Confirmar Pago</button>
            <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}

function abrirPago(pagoId, carteraId, monto, numCuota) {
    document.getElementById('modal-pago-id').value    = pagoId;
    document.getElementById('modal-cartera-id').value = carteraId;
    document.getElementById('modal-monto').value      = monto;
    document.getElementById('modal-info').textContent = 'Cuota #' + numCuota + ' — Monto: S/ ' + parseFloat(monto).toFixed(2);
    document.getElementById('modalPago').classList.add('show');
}
function cerrarModal() {
    document.getElementById('modalPago').classList.remove('show');
}
document.getElementById('modalPago').addEventListener('click', function(e){
    if (e.target === this) cerrarModal();
});
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