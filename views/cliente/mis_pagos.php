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
actualizarMora($conn); // mora automática

// Procesar reporte de pago con Yape
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'reportar_yape') {
    $cuota_id = intval($_POST['cuota_id'] ?? 0);
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === 0) {
        $archivo = $_FILES['comprobante'];
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','pdf']) && $archivo['size'] <= 5*1024*1024) {
            $carpeta = __DIR__ . "/../../public/uploads/comprobantes/";
            if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);
            $nombre_archivo = "comprobante_" . $cuota_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($archivo['tmp_name'], $carpeta . $nombre_archivo)) {
                $ruta = "public/uploads/comprobantes/" . $nombre_archivo;
                $stmt = $conn->prepare("UPDATE pagos SET estado='por_verificar', comprobante_yape=?, fecha_reporte=NOW() WHERE id=? AND cartera_id IN (SELECT id FROM cartera_prestamos WHERE cliente_id=?)");
                $stmt->bind_param("sii", $ruta, $cuota_id, $id);
                $stmt->execute();

                // Notificar al administrador
                $cuota_info = $conn->query("SELECT p.numero_cuota, cp.codigo_cuenta, u.nombres, u.apellidos FROM pagos p JOIN cartera_prestamos cp ON p.cartera_id=cp.id JOIN usuarios u ON cp.cliente_id=u.id WHERE p.id=$cuota_id")->fetch_assoc();
                $admins = $conn->query("SELECT id FROM usuarios WHERE rol_id=3 AND activo=1");
                if ($admins) {
                    while ($adm = $admins->fetch_assoc()) {
                        $msg = "Cliente reportó pago de la cuota #" . ($cuota_info['numero_cuota'] ?? '') . " vía Yape. Pendiente de verificación.";
                        $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Pago por verificar', ?, 'advertencia')");
                        $n->bind_param("is", $adm['id'], $msg);
                        $n->execute();
                    }
                }
                setMensaje("Comprobante enviado. El administrador verificará tu pago pronto.", "exito");
            }
        } else {
            setMensaje("Solo JPG, PNG o PDF hasta 5MB.", "error");
        }
    }
    header("Location: mis_pagos.php");
    exit;
}

// Cargar foto de perfil desde BD si no está en sesión
if (empty($_SESSION['foto'])) {
    $u = $conn->query("SELECT foto FROM usuarios WHERE id=$id")->fetch_assoc();
    if ($u && $u['foto']) $_SESSION['foto'] = $u['foto'];
}

// Obtener cartera de préstamos del cliente
$carteras = [];
$r = $conn->query(
    "SELECT cp.*, s.codigo, s.monto_solicitado, tp.nombre AS tipo
     FROM cartera_prestamos cp
     JOIN solicitudes s ON cp.solicitud_id = s.id
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
     WHERE cp.cliente_id = $id
     ORDER BY cp.fecha_inicio DESC"
);
if ($r) $carteras = $r->fetch_all(MYSQLI_ASSOC);

// Obtener todos los pagos del cliente
$pagos = [];
foreach ($carteras as $c) {
    $cid = $c['id'];
    $r2 = $conn->query(
        "SELECT p.*, c.solicitud_id
         FROM pagos p
         JOIN cartera_prestamos c ON p.cartera_id = c.id
         WHERE p.cartera_id = $cid
         ORDER BY p.numero_cuota ASC"
    );
    if ($r2) {
        $cuotas = $r2->fetch_all(MYSQLI_ASSOC);
        $pagos[$cid] = [
            'cartera' => $c,
            'cuotas'  => $cuotas
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Mis Pagos</title>
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

        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;}
        .card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #f1f5f9;}
        .card-header h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:4px;}
        .card-header p{font-size:.78rem;color:#64748b;}

        .prestamo-info{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;background:#f8fafc;border-radius:8px;padding:14px;}
        .pi-item .lbl{font-size:.68rem;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:3px;}
        .pi-item .val{font-size:.88rem;font-weight:700;color:#0f172a;}

        /* BARRA PROGRESO */
        .progreso-bar{margin-bottom:16px;}
        .progreso-bar .info{display:flex;justify-content:space-between;font-size:.75rem;color:#64748b;margin-bottom:5px;}
        .barra{height:8px;background:#e2e8f0;border-radius:20px;overflow:hidden;}
        .barra-fill{height:100%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:20px;transition:width .3s;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .b-pend{background:#fef3c7;color:#92400e;}
        .b-pag{background:#d1fae5;color:#065f46;}
        .b-venc{background:#fee2e2;color:#991b1b;}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;align-items:center;justify-content:center;}
        .modal-overlay.show{display:flex;}
        .modal-yape{background:#fff;border-radius:16px;padding:28px;max-width:380px;width:90%;text-align:center;}
        .modal-yape h3{font-size:1.1rem;font-weight:800;color:#722ED1;margin-bottom:4px;}
        .modal-yape p{font-size:.82rem;color:#64748b;margin-bottom:16px;}
        .qr-box{background:#f8fafc;border:2px dashed #722ED1;border-radius:12px;padding:20px;margin-bottom:16px;}
        .qr-box .monto{font-size:1.6rem;font-weight:800;color:#0f172a;margin-bottom:10px;}
        .qr-box img{width:160px;height:160px;margin:0 auto 10px;display:block;border-radius:8px;}
        .qr-box .numero{font-size:1.1rem;font-weight:700;color:#722ED1;}
        .qr-box .nombre{font-size:.78rem;color:#64748b;}
        .form-upload label{display:block;text-align:left;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px;}
        .form-upload input[type=file]{width:100%;padding:10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.82rem;margin-bottom:14px;}
        .btn-modal{padding:11px 20px;border-radius:8px;font-size:.88rem;font-weight:700;border:none;cursor:pointer;width:100%;margin-bottom:8px;}
        .btn-modal.enviar{background:#722ED1;color:#fff;}
        .btn-modal.cancelar{background:#f1f5f9;color:#64748b;}

        /* SIN PAGOS */
        .empty{text-align:center;padding:60px 20px;color:#94a3b8;}
        .empty svg{width:64px;height:64px;margin:0 auto 16px;display:block;color:#cbd5e1;}
        .empty h3{font-size:1rem;font-weight:600;color:#64748b;margin-bottom:8px;}
        .empty p{font-size:.85rem;line-height:1.6;}
        .empty a{color:#3b82f6;font-weight:600;text-decoration:none;}

        /* INFO BOX */
        .info-box{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.84rem;color:#1e40af;line-height:1.6;}
        .info-box strong{font-weight:700;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .prestamo-info{grid-template-columns:1fr 1fr;}
            thead th:nth-child(4),tbody td:nth-child(4){display:none;}
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
        <?= avatar($nombre, $_SESSION['foto']??null, 38) ?>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Cliente</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Menu Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <a href="solicitar.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Solicitar Préstamo</a>
        <a href="mis_solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <a href="mis_ahorros.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Mis Ahorros</a>
        <a href="mis_pagos.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mis Pagos</a>
        <a href="mi_perfil.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
        <h1>Mis Pagos</h1>
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
        <?= avatar($nombre, $_SESSION['foto']??null, 26) ?>
        <span><?= htmlspecialchars($nombre) ?></span>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>

    <?php if (empty($pagos)): ?>

    <!-- SIN PRÉSTAMOS DESEMBOLSADOS -->
    <div class="card">
        <div class="info-box">
            <strong>¿Cómo funciona esta sección?</strong> Aquí verás las cuotas de tus préstamos una vez que hayan sido desembolsados. El administrador registrará tus pagos cuando vayas a la oficina.
        </div>
        <div class="empty">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3>No tienes préstamos activos aún</h3>
            <p>Cuando tu préstamo sea aprobado y desembolsado,<br>aquí aparecerán todas tus cuotas de pago.<br><br>
               <a href="solicitar.php">Solicitar un préstamo →</a>
            </p>
        </div>
    </div>

    <?php else: ?>

    <div class="info-box">
        <strong>Recuerda:</strong> Para registrar un pago debes acercarte a nuestras oficinas CREDISOL. El administrador actualizará tu estado de pago en el sistema.
    </div>

    <?php foreach ($pagos as $cid => $data):
        $cartera = $data['cartera'];
        $cuotas  = $data['cuotas'];
        $pagadas = array_filter($cuotas, fn($c) => $c['estado'] == 'pagado');
        $pct     = $cartera['cuotas_total'] > 0 ? round(count($pagadas) / $cartera['cuotas_total'] * 100) : 0;
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h3><?= htmlspecialchars($cartera['codigo']) ?> — <?= htmlspecialchars($cartera['tipo']) ?></h3>
                <p>Inicio: <?= fechaCorta($cartera['fecha_inicio']) ?> &nbsp;|&nbsp; Vencimiento: <?= fechaCorta($cartera['fecha_fin']) ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <a href="cronograma.php?cartera=<?= $cid ?>" target="_blank"
                   style="background:#eff6ff;color:#1d4ed8;padding:6px 12px;border-radius:6px;font-size:.76rem;font-weight:600;text-decoration:none;">
                   🖨️ Imprimir cronograma
                </a>
            </div>
            <span class="badge <?= $cartera['estado']=='vigente'?'b-pag':($cartera['estado']=='en_mora'?'b-venc':'b-pend') ?>"><?= ucfirst($cartera['estado']) ?></span>
        </div>

        <!-- INFO DEL PRÉSTAMO -->
        <div class="prestamo-info">
            <div class="pi-item">
                <div class="lbl">Monto Total</div>
                <div class="val"><?= soles($cartera['monto_total']) ?></div>
            </div>
            <div class="pi-item">
                <div class="lbl">Saldo Pendiente</div>
                <div class="val" style="color:#ef4444;"><?= soles($cartera['saldo_pendiente']) ?></div>
            </div>
            <div class="pi-item">
                <div class="lbl">Cuotas Pagadas</div>
                <div class="val"><?= $cartera['cuotas_pagadas'] ?> / <?= $cartera['cuotas_total'] ?></div>
            </div>
            <div class="pi-item">
                <div class="lbl">Estado</div>
                <div class="val"><?= ucfirst($cartera['estado']) ?></div>
            </div>
        </div>

        <!-- BARRA DE PROGRESO -->
        <div class="progreso-bar">
            <div class="info">
                <span>Progreso de pago</span>
                <span><?= $pct ?>% completado</span>
            </div>
            <div class="barra">
                <div class="barra-fill" style="width:<?= $pct ?>%;"></div>
            </div>
        </div>

        <!-- TABLA DE CUOTAS -->
        <?php if (!empty($cuotas)): ?>
        <table>
            <thead>
                <tr>
                    <th>Cuota</th>
                    <th>Vencimiento</th>
                    <th>Monto</th>
                    <th>Fecha Pago</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cuotas as $cuota):
                $hoy = date('Y-m-d');
                $venc = $cuota['fecha_vencimiento'];
                $estado = $cuota['estado'];
                // Verificar si está vencida
                if ($estado == 'pendiente' && $venc < $hoy) $estado = 'vencido';
                $bc = ['pendiente'=>'b-pend','pagado'=>'b-pag','vencido'=>'b-venc','por_verificar'=>'b-venc'];
                $bt = ['pendiente'=>'Pendiente','pagado'=>'Pagado','vencido'=>'Vencido','por_verificar'=>'Por verificar'];
                $rowStyle = $estado == 'vencido' ? 'background:#fff5f5;' : '';
            ?>
            <tr style="<?= $rowStyle ?>">
                <td style="font-weight:700;color:#1d4ed8;">#<?= $cuota['numero_cuota'] ?></td>
                <td style="<?= $estado=='vencido'?'color:#ef4444;font-weight:600;':'' ?>"><?= fechaCorta($venc) ?></td>
                <td style="font-weight:600;"><?= soles($cuota['monto_cuota']) ?></td>
                <td style="color:#64748b;"><?= $cuota['fecha_pago'] ? fechaCorta($cuota['fecha_pago']) : '—' ?></td>
                <td><span class="badge <?= $bc[$estado]??'b-pend' ?>"><?= $bt[$estado]??'Pendiente' ?></span></td>
                <td>
                    <?php if ($estado=='pendiente' || $estado=='vencido'): ?>
                    <button onclick="abrirYape(<?= $cuota['id'] ?>, '<?= soles($cuota['monto_cuota']) ?>', <?= $cuota['numero_cuota'] ?>)"
                            style="background:#722ED1;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:.74rem;font-weight:700;cursor:pointer;">
                        Pagar con Yape
                    </button>
                    <?php elseif ($estado=='por_verificar'): ?>
                    <span style="font-size:.72rem;color:#92400e;">En revisión</span>
                    <?php else: ?>
                    <span style="font-size:.72rem;color:#94a3b8;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align:center;color:#94a3b8;padding:20px;font-size:.85rem;">No hay cuotas registradas aún.</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function abrirYape(cuotaId, monto, numCuota){
    document.getElementById('yapeCuotaId').value = cuotaId;
    document.getElementById('yapeMonto').textContent = monto + ' — Cuota #' + numCuota;
    document.getElementById('modalYape').classList.add('show');
}
function cerrarYape(){
    document.getElementById('modalYape').classList.remove('show');
}
</script>

<!-- BOTÓN CERRAR SESIÓN MÓVIL -->
<a href="../../controllers/AuthController.php?accion=logout" class="btn-logout-movil">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
    </svg>
    Cerrar Sesión
</a>

<!-- MODAL PAGO YAPE -->
<div class="modal-overlay" id="modalYape">
    <div class="modal-yape">
        <h3>Pagar con Yape</h3>
        <p>Escanea el código o usa el número, luego sube tu comprobante</p>
        <div class="qr-box">
            <div class="monto" id="yapeMonto">S/ 0.00</div>
            <img src="../../public/img/qr_yape.png" alt="QR Yape" onerror="this.style.display='none'">
            <div class="numero">987 654 321</div>
            <div class="nombre">CREDISOL Cooperativa</div>
        </div>
        <form method="POST" enctype="multipart/form-data" class="form-upload">
            <input type="hidden" name="accion" value="reportar_yape">
            <input type="hidden" name="cuota_id" id="yapeCuotaId" value="">
            <label>Sube tu comprobante de pago (foto o captura)</label>
            <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>
            <button type="submit" class="btn-modal enviar">Enviar comprobante</button>
        </form>
        <button type="button" class="btn-modal cancelar" onclick="cerrarYape()">Cancelar</button>
    </div>
</div>

</body>
</html>