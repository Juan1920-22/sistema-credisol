<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([2]);

$nombre    = $_SESSION['nombres'];
$apellido  = $_SESSION['apellidos'];
$asesor_id = $_SESSION['usuario_id'];
$base      = getBase();

// GUARDAR HISTORIAL CREDITICIO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'guardar_historial') {
    $cliente_id          = intval($_POST['cliente_id']);
    $score               = intval($_POST['score_crediticio'] ?? 500);
    $puntualidad         = limpiar($_POST['puntualidad'] ?? 'buena');
    $prestamos_ant       = intval($_POST['prestamos_anteriores'] ?? 0);
    $prestamos_mora      = intval($_POST['prestamos_en_mora'] ?? 0);
    $deudas              = floatval($_POST['deudas_actuales'] ?? 0);
    $infocorp            = isset($_POST['esta_en_infocorp']) ? 1 : 0;
    $solicitud_id        = intval($_POST['solicitud_id']);

    // Verificar si ya existe historial
    $existe = $conn->query("SELECT id FROM historial_crediticio WHERE cliente_id=$cliente_id")->fetch_assoc();

    if ($existe) {
        $conn->query("UPDATE historial_crediticio SET
            score_crediticio=$score,
            puntualidad='$puntualidad',
            prestamos_anteriores=$prestamos_ant,
            prestamos_en_mora=$prestamos_mora,
            deudas_actuales=$deudas,
            esta_en_infocorp=$infocorp
            WHERE cliente_id=$cliente_id");
    } else {
        $conn->query("INSERT INTO historial_crediticio
            (cliente_id, score_crediticio, puntualidad, prestamos_anteriores, prestamos_en_mora, deudas_actuales, esta_en_infocorp)
            VALUES ($cliente_id, $score, '$puntualidad', $prestamos_ant, $prestamos_mora, $deudas, $infocorp)");
    }

    setMensaje("Historial crediticio guardado correctamente.", "exito");
    header("Location: solicitudes.php?id=$solicitud_id");
    exit;
}
//OBSERVER
// Aprobar solicitud
if (isset($_GET['aprobar'])) {
    $sid = intval($_GET['aprobar']);
    $conn->query("UPDATE solicitudes SET estado='aprobada_asesor', fecha_evaluacion=NOW() WHERE id=$sid AND asesor_id=$asesor_id");
    // Notificar cliente
    $sol = $conn->query("SELECT s.cliente_id, s.codigo FROM solicitudes s WHERE s.id=$sid")->fetch_assoc();
    if ($sol) {
        $msg = "Tu solicitud {$sol['codigo']} fue aprobada por el asesor y está en revisión final.";
        $stmt = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Aprobada por Asesor', ?, 'exito')");
        $stmt->bind_param("is", $sol['cliente_id'], $msg);
        $stmt->execute();
    }
    setMensaje("Solicitud aprobada y enviada al jefe.", "exito");
    header("Location: solicitudes.php");
    exit;
}

// Rechazar solicitud
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'rechazar') {
    $sid    = intval($_POST['solicitud_id']);
    $motivo = limpiar($_POST['motivo_rechazo'] ?? '');
    $conn->query("UPDATE solicitudes SET estado='rechazada_asesor', motivo_rechazo='".addslashes($motivo)."', fecha_evaluacion=NOW() WHERE id=$sid AND asesor_id=$asesor_id");
    $sol = $conn->query("SELECT s.cliente_id, s.codigo FROM solicitudes s WHERE s.id=$sid")->fetch_assoc();
    if ($sol) {
        $msg = "Tu solicitud {$sol['codigo']} fue rechazada. Motivo: $motivo";
        $stmt = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud Rechazada', ?, 'error')");
        $stmt->bind_param("is", $sol['cliente_id'], $msg);
        $stmt->execute();
    }
    setMensaje("Solicitud rechazada.", "advertencia");
    header("Location: solicitudes.php");
    exit;
}

// Iniciar evaluación
if (isset($_GET['evaluar'])) {
    $sid = intval($_GET['evaluar']);
    $conn->query("UPDATE solicitudes SET estado='en_evaluacion' WHERE id=$sid AND asesor_id=$asesor_id AND estado='pendiente'");
    header("Location: solicitudes.php?id=$sid");
    exit;
}

// Obtener solicitudes asignadas
$filtro = limpiar($_GET['filtro'] ?? 'pendientes');
$sql = "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
        CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.dni AS cliente_dni,
        c.ingreso_mensual, c.telefono AS cliente_tel, c.correo AS cliente_correo
        FROM solicitudes s
        JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
        JOIN usuarios c ON s.cliente_id=c.id
        WHERE s.asesor_id=$asesor_id";

if ($filtro == 'pendientes') $sql .= " AND s.estado IN ('pendiente','en_evaluacion')";
elseif ($filtro == 'evaluadas') $sql .= " AND s.estado IN ('aprobada_asesor','rechazada_asesor')";

$sql .= " ORDER BY s.fecha_solicitud ASC";
$solicitudes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Detalle seleccionado
$detalle = null;
$historial = null;
if (isset($_GET['id'])) {
    $sid = intval($_GET['id']);
    $detalle = $conn->query(
        "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
         CONCAT(c.nombres,' ',c.apellidos) AS cliente,
         c.dni AS cliente_dni, c.ingreso_mensual,
         c.telefono AS cliente_tel, c.correo AS cliente_correo,
         c.ocupacion, c.direccion
         FROM solicitudes s
         JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
         JOIN usuarios c ON s.cliente_id=c.id
         WHERE s.id=$sid AND s.asesor_id=$asesor_id"
    )->fetch_assoc();

    if ($detalle) {
        $historial  = $conn->query("SELECT * FROM historial_crediticio WHERE cliente_id={$detalle['cliente_id']}")->fetch_assoc();
        $documentos = $conn->query("SELECT * FROM documentos WHERE solicitud_id=$sid ORDER BY subido_en DESC")->fetch_all(MYSQLI_ASSOC);
        $mensajes   = $conn->query("SELECT m.*, CONCAT(u.nombres,' ',u.apellidos) AS autor FROM mensajes_solicitud m JOIN usuarios u ON m.usuario_id=u.id WHERE m.solicitud_id=$sid ORDER BY m.creado_en ASC")->fetch_all(MYSQLI_ASSOC);
        // Marcar mensajes del cliente como leídos
        $conn->query("UPDATE mensajes_solicitud SET leido=1 WHERE solicitud_id=$sid AND tipo='cliente'");
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
        .bnot{margin-left:auto;background:#ef4444;color:#fff;font-size:.67rem;font-weight:700;padding:2px 6px;border-radius:20px;}
        .sb-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.88rem;font-weight:700;text-decoration:none;padding:10px 0;}
        .topbar{position:fixed;top:0;left:250px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#065f46;}
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:18px;height:18px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        .grid2{display:grid;grid-template-columns:1fr 380px;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        .filtros{display:flex;gap:8px;margin-bottom:16px;}
        .filtro-btn{padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid #e2e8f0;color:#64748b;background:#fff;}
        .filtro-btn.activo{background:#059669;color:#fff;border-color:#059669;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.sel{background:#f0fdf4;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.baa{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-eval{background:#dbeafe;color:#1e40af;}
        .btn-ver{background:#f0fdf4;color:#065f46;}

        /* DETALLE */
        .det-seccion{font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin:14px 0 8px;}
        .det-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:.84rem;}
        .det-row:last-child{border-bottom:none;}
        .det-row span:first-child{color:#64748b;}
        .det-row span:last-child{font-weight:600;color:#0f172a;text-align:right;max-width:55%;}

        .score-box{background:#f8fafc;border-radius:8px;padding:14px;text-align:center;margin:12px 0;}
        .score-num{font-size:2rem;font-weight:800;}
        .score-lbl{font-size:.72rem;color:#64748b;margin-top:2px;}

        .btn-aprobar{width:100%;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;margin-bottom:10px;text-decoration:none;display:block;text-align:center;}
        .btn-rechazar{width:100%;padding:10px;background:#fee2e2;color:#991b1b;border:none;border-radius:8px;font-size:.88rem;font-weight:700;cursor:pointer;}

        .sin-sel{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-sel svg{width:52px;height:52px;margin:0 auto 14px;display:block;color:#cbd5e1;}
        .empty{text-align:center;padding:32px;color:#94a3b8;font-size:.86rem;}

        .form-grupo{margin-bottom:12px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.8rem;margin-bottom:5px;}
        .form-grupo textarea{width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;background:#f9fafb;outline:none;resize:vertical;min-height:80px;}

        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.abierto{transform:translateX(0);}
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
        .notif-panel{position:absolute;right:0;top:calc(100% + 8px);width:300px;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.15);z-index:500;border:1px solid #e2e8f0;overflow:hidden;}
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
        <div><h2>CREDISOL</h2><span>Panel del Asesor</span></div>
    </div>
    <div class="sb-user">
        <div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Asesor de Crédito</span></div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menú
        </button>
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
        <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="grid2">

        <!-- LISTA -->
        <div class="card">
            <h3>Solicitudes Asignadas a Mí</h3>
            <div class="filtros">
                <a href="solicitudes.php?filtro=pendientes" class="filtro-btn <?= $filtro=='pendientes'?'activo':'' ?>">Por evaluar</a>
                <a href="solicitudes.php?filtro=evaluadas" class="filtro-btn <?= $filtro=='evaluadas'?'activo':'' ?>">Evaluadas</a>
                <a href="solicitudes.php?filtro=todas" class="filtro-btn <?= $filtro=='todas'?'activo':'' ?>">Todas</a>
            </div>
            <?php if (empty($solicitudes)): ?>
            <div class="empty">No tienes solicitudes en esta categoría.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Estado</th><th>Ver</th></tr></thead>
                <tbody>
                <?php
                $bc=['pendiente'=>'bp','en_evaluacion'=>'be','aprobada_asesor'=>'baa','rechazada_asesor'=>'br'];
                $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación','aprobada_asesor'=>'Aprobada','rechazada_asesor'=>'Rechazada'];
                foreach ($solicitudes as $s): ?>
                <tr class="<?= ($detalle && $detalle['id']==$s['id'])?'sel':'' ?>">
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($s['cliente']) ?></div>
                        <div style="font-size:.72rem;color:#94a3b8;"><?= $s['tipo'] ?></div>
                    </td>
                    <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                    <td><span class="badge <?= $bc[$s['estado']]??'bp' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span></td>
                    <td>
                        <?php if ($s['estado'] == 'pendiente'): ?>
                        <a href="solicitudes.php?evaluar=<?= $s['id'] ?>" class="btn-sm btn-eval">Iniciar</a>
                        <?php else: ?>
                        <a href="solicitudes.php?id=<?= $s['id'] ?>" class="btn-sm btn-ver">Ver</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- DETALLE Y EVALUACIÓN -->
        <div class="card">
            <?php if (!$detalle): ?>
            <div class="sin-sel">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p>Selecciona una solicitud para ver el detalle y evaluarla.</p>
            </div>
            <?php else: ?>
            <h3><?= $detalle['codigo'] ?> — <?= htmlspecialchars($detalle['cliente']) ?></h3>

            <div class="det-seccion">Datos del Cliente</div>
            <div class="det-row"><span>DNI</span><span><?= $detalle['cliente_dni'] ?></span></div>
            <div class="det-row"><span>Teléfono</span><span><?= $detalle['cliente_tel']??'—' ?></span></div>
            <div class="det-row"><span>Correo</span><span style="font-size:.78rem;"><?= $detalle['cliente_correo'] ?></span></div>
            <div class="det-row"><span>Ocupación</span><span><?= $detalle['ocupacion']??'—' ?></span></div>
            <div class="det-row"><span>Ingreso mensual</span><span><?= soles($detalle['ingreso_mensual']??0) ?></span></div>

            <div class="det-seccion">Datos del Préstamo</div>
            <div class="det-row"><span>Tipo</span><span><?= $detalle['tipo'] ?></span></div>
            <div class="det-row"><span>Monto</span><span style="color:#1d4ed8;font-size:1rem;"><?= soles($detalle['monto_solicitado']) ?></span></div>
            <div class="det-row"><span>Plazo</span><span><?= $detalle['plazo_meses'] ?> meses</span></div>
            <div class="det-row"><span>Cuota estimada</span><span><?= soles($detalle['cuota_estimada']) ?></span></div>
            <div class="det-row"><span>Tasa</span><span><?= $detalle['tasa_interes'] ?>% anual</span></div>
            <div class="det-row"><span>Motivo</span><span style="max-width:55%;text-align:right;"><?= htmlspecialchars($detalle['motivo']) ?></span></div>

            <?php if ($historial): ?>
            <div class="det-seccion">Historial Crediticio</div>
            <?php
            // STRATEGY: 3 algoritmos de evaluación según el score
            $score = $historial['score_crediticio'] ?? 0;
            $sc = $score >= 700 ? '#059669' : ($score >= 500 ? '#d97706' : '#dc2626');
            $sl = $score >= 700 ? 'Bueno' : ($score >= 500 ? 'Regular' : 'Bajo');
            ?>
            <div class="score-box">
                <div class="score-num" style="color:<?= $sc ?>"><?= $score ?></div>
                <div class="score-lbl">Score crediticio — <?= $sl ?></div>
            </div>
            <div class="det-row"><span>Puntualidad</span><span><?= ucfirst($historial['puntualidad']??'—') ?></span></div>
            <div class="det-row"><span>Préstamos anteriores</span><span><?= $historial['prestamos_anteriores']??0 ?></span></div>
            <div class="det-row"><span>En mora</span><span style="color:<?= ($historial['prestamos_en_mora']??0)>0?'#dc2626':'#059669' ?>"><?= $historial['prestamos_en_mora']??0 ?></span></div>
            <div class="det-row"><span>Deudas actuales</span><span style="color:<?= ($historial['deudas_actuales']??0)>0?'#dc2626':'#059669' ?>"><?= soles($historial['deudas_actuales']??0) ?></span></div>
            <div class="det-row"><span>En Infocorp</span><span style="color:<?= $historial['esta_en_infocorp']?'#dc2626':'#059669' ?>"><?= $historial['esta_en_infocorp']?'SÍ — RIESGO':'No' ?></span></div>
            <?php endif; ?>

            <!-- FORMULARIO HISTORIAL CREDITICIO -->
            <?php if (in_array($detalle['estado'], ['pendiente','en_evaluacion'])): ?>
            <div style="margin-top:14px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;padding:14px;margin-bottom:14px;">
                <div style="font-size:.78rem;font-weight:700;color:#0369a1;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                    ✏️ Llenar / Actualizar Historial Crediticio
                </div>
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_historial">
                    <input type="hidden" name="cliente_id" value="<?= $detalle['cliente_id'] ?>">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                        <div>
                            <label style="font-size:.74rem;font-weight:600;color:#374151;display:block;margin-bottom:3px;">Score crediticio (0-1000)</label>
                            <input type="number" name="score_crediticio" min="0" max="1000"
                                   value="<?= $historial['score_crediticio']??500 ?>"
                                   style="width:100%;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;outline:none;">
                        </div>
                        <div>
                            <label style="font-size:.74rem;font-weight:600;color:#374151;display:block;margin-bottom:3px;">Puntualidad</label>
                            <select name="puntualidad" style="width:100%;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;outline:none;">
                                <option value="excelente" <?= ($historial['puntualidad']??'')=='excelente'?'selected':'' ?>>Excelente</option>
                                <option value="buena" <?= ($historial['puntualidad']??'buena')=='buena'?'selected':'' ?>>Buena</option>
                                <option value="regular" <?= ($historial['puntualidad']??'')=='regular'?'selected':'' ?>>Regular</option>
                                <option value="mala" <?= ($historial['puntualidad']??'')=='mala'?'selected':'' ?>>Mala</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.74rem;font-weight:600;color:#374151;display:block;margin-bottom:3px;">Préstamos anteriores</label>
                            <input type="number" name="prestamos_anteriores" min="0"
                                   value="<?= $historial['prestamos_anteriores']??0 ?>"
                                   style="width:100%;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;outline:none;">
                        </div>
                        <div>
                            <label style="font-size:.74rem;font-weight:600;color:#374151;display:block;margin-bottom:3px;">Préstamos en mora</label>
                            <input type="number" name="prestamos_en_mora" min="0"
                                   value="<?= $historial['prestamos_en_mora']??0 ?>"
                                   style="width:100%;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;outline:none;">
                        </div>
                    </div>
                    <div style="margin-bottom:8px;">
                        <label style="font-size:.74rem;font-weight:600;color:#374151;display:block;margin-bottom:3px;">Deudas actuales (S/)</label>
                        <input type="number" name="deudas_actuales" min="0" step="0.01"
                               value="<?= $historial['deudas_actuales']??0 ?>"
                               style="width:100%;padding:7px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:.85rem;outline:none;">
                    </div>
                    <label style="display:flex;align-items:center;gap:8px;font-size:.82rem;font-weight:600;color:#dc2626;margin-bottom:10px;cursor:pointer;">
                        <input type="checkbox" name="esta_en_infocorp" value="1"
                               <?= ($historial['esta_en_infocorp']??0)?'checked':'' ?>
                               style="width:16px;height:16px;accent-color:#dc2626;">
                        Registrado en Infocorp / SBS
                    </label>
                    <button type="submit" style="width:100%;padding:9px;background:#0369a1;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;">
                        Guardar Historial Crediticio
                    </button>
                </form>
            </div>

            <?php endif; ?>

            <?php if (in_array($detalle['estado'], ['pendiente','en_evaluacion'])): ?>
            <div style="margin-top:18px;">
                <a href="solicitudes.php?aprobar=<?= $detalle['id'] ?>"
                   class="btn-aprobar"
                   onclick="return confirm('¿Aprobar la solicitud <?= $detalle['codigo'] ?> y enviar al jefe?')">
                   Aprobar y Enviar al Jefe
                </a>
                <button class="btn-rechazar" onclick="mostrarRechazar(<?= $detalle['id'] ?>, '<?= $detalle['codigo'] ?>')">
                    Rechazar Solicitud
                </button>
            </div>

            <!-- PEDIR DOCUMENTOS -->
            <div style="margin-top:14px;background:#fefce8;border:1.5px solid #fde047;border-radius:8px;padding:14px;">
                <div style="font-size:.8rem;font-weight:700;color:#713f12;margin-bottom:10px;">Solicitar documentos al cliente</div>
                <form action="../../controllers/DocumentoController.php" method="POST">
                    <input type="hidden" name="accion" value="pedir_documentos">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">
                    <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:10px;">
                        <label style="font-size:.82rem;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="docs[]" value="dni"> DNI (ambas caras)</label>
                        <label style="font-size:.82rem;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="docs[]" value="recibo_ingreso"> Boleta de pago</label>
                        <label style="font-size:.82rem;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="docs[]" value="recibo_servicio"> Recibo de luz o agua</label>
                        <label style="font-size:.82rem;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="docs[]" value="otro"> Otro</label>
                    </div>
                    <input type="text" name="mensaje_extra" placeholder="Mensaje adicional (opcional)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;margin-bottom:8px;">
                    <button type="submit" style="width:100%;padding:9px;background:#d97706;color:#fff;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;">Enviar solicitud de documentos</button>
                </form>
            </div>
            <?php elseif ($detalle['estado'] == 'aprobada_asesor'): ?>
            <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:8px;padding:14px;margin-top:16px;font-size:.85rem;color:#065f46;font-weight:600;text-align:center;">
                Aprobada y enviada al jefe para revisión final.
            </div>
            <?php elseif ($detalle['estado'] == 'rechazada_asesor'): ?>
            <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:8px;padding:14px;margin-top:16px;font-size:.85rem;color:#991b1b;">
                <strong>Rechazada.</strong> Motivo: <?= htmlspecialchars($detalle['motivo_rechazo']??'—') ?>
            </div>
            <?php endif; ?>

            <!-- DOCUMENTOS SUBIDOS POR EL CLIENTE -->
            <?php if (!empty($documentos)): ?>
            <div style="margin-top:14px;">
                <div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">Documentos del cliente</div>
                <?php
                $tipos_doc=['dni'=>'DNI','recibo_ingreso'=>'Boleta/Ingreso','recibo_servicio'=>'Recibo Servicios','otro'=>'Otro'];
                foreach ($documentos as $doc): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:9px;background:#f8fafc;border-radius:8px;margin-bottom:6px;border:1px solid #e2e8f0;">
                    <div style="width:32px;height:32px;background:#dbeafe;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg fill="none" viewBox="0 0 24 24" stroke="#1d4ed8" stroke-width="2" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:.82rem;font-weight:600;"><?= htmlspecialchars($doc['nombre_archivo']) ?></div>
                        <div style="font-size:.72rem;color:#64748b;"><?= $tipos_doc[$doc['tipo']]??$doc['tipo'] ?></div>
                    </div>
                    <a href="../../<?= $doc['ruta'] ?>" target="_blank" style="background:#dbeafe;color:#1e40af;padding:4px 10px;border-radius:6px;font-size:.75rem;font-weight:600;text-decoration:none;">Ver</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- CHAT CON EL CLIENTE -->
            <div style="margin-top:14px;">
                <div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px;">Mensajes con el cliente</div>
                <?php if (!empty($mensajes)): ?>
                <div style="background:#f8fafc;border-radius:10px;padding:12px;max-height:200px;overflow-y:auto;margin-bottom:10px;display:flex;flex-direction:column;gap:8px;" id="chatAsesor">
                    <?php foreach ($mensajes as $m): ?>
                    <div style="max-width:85%;padding:9px 12px;border-radius:10px;font-size:.82rem;line-height:1.5;<?= $m['tipo']=='asesor'?'background:#d1fae5;color:#065f46;align-self:flex-end;':'background:#dbeafe;color:#1e40af;align-self:flex-start;' ?>">
                        <div style="font-size:.68rem;font-weight:700;margin-bottom:3px;opacity:.8;"><?= $m['tipo']=='asesor'?'Tú':'👤 '.$m['autor'] ?></div>
                        <?= nl2br(htmlspecialchars($m['mensaje'])) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form action="../../controllers/DocumentoController.php" method="POST" style="display:flex;gap:8px;">
                    <input type="hidden" name="accion" value="enviar_mensaje">
                    <input type="hidden" name="solicitud_id" value="<?= $detalle['id'] ?>">
                    <input type="text" name="mensaje" placeholder="Escribe al cliente..." required style="flex:1;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;outline:none;">
                    <button type="submit" style="padding:9px 16px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;white-space:nowrap;">Enviar</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- MODAL RECHAZAR -->
<div id="modalRechazar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:420px;">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:6px;">Rechazar Solicitud</h3>
        <p id="modal-txt" style="color:#64748b;font-size:.85rem;margin-bottom:18px;"></p>
        <form method="POST">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" name="solicitud_id" id="modal-sid">
            <div class="form-grupo">
                <label>Motivo del rechazo *</label>
                <textarea name="motivo_rechazo" placeholder="Explica el motivo..." required></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="flex:1;padding:11px;background:#ef4444;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Confirmar</button>
                <button type="button" onclick="cerrarModal()" style="padding:11px 20px;background:#f1f5f9;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('abierto');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('abierto');document.getElementById('overlay').classList.remove('show');}
function mostrarRechazar(id, codigo){
    document.getElementById('modal-sid').value=id;
    document.getElementById('modal-txt').textContent='Solicitud: '+codigo;
    document.getElementById('modalRechazar').style.display='flex';
}
function cerrarModal(){document.getElementById('modalRechazar').style.display='none';}
</script>
</body>
</html>