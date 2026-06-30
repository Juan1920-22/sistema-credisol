<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([2]);

$nombre    = $_SESSION['nombres'];
$apellido  = $_SESSION['apellidos'];
$asesor_id = $_SESSION['usuario_id'];

$buscar = limpiar($_GET['buscar'] ?? '');

// Solo clientes que tienen solicitudes asignadas a este asesor
$sql = "SELECT DISTINCT u.*,
        (SELECT COUNT(*) FROM solicitudes WHERE cliente_id=u.id AND asesor_id=$asesor_id) AS mis_solicitudes,
        (SELECT s.estado FROM solicitudes s WHERE s.cliente_id=u.id AND s.asesor_id=$asesor_id ORDER BY s.fecha_solicitud DESC LIMIT 1) AS ultimo_estado
        FROM usuarios u
        JOIN solicitudes s ON s.cliente_id=u.id
        WHERE s.asesor_id=$asesor_id AND u.rol_id=1";

if ($buscar) $sql .= " AND (u.nombres LIKE '%$buscar%' OR u.apellidos LIKE '%$buscar%' OR u.dni LIKE '%$buscar%')";
$sql .= " ORDER BY u.nombres ASC";
$clientes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Ver Clientes</title>
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
        .menu-btn svg{width:18px;height:18px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .buscar-bar{display:flex;gap:10px;margin-bottom:18px;}
        .buscar{flex:1;padding:9px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;background:#fff;outline:none;}
        .buscar:focus{border-color:#059669;}
        .btn-buscar{padding:9px 18px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;}
        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:11px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.baa{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}
        .empty{text-align:center;padding:40px;color:#94a3b8;font-size:.86rem;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.abierto{transform:translateX(0);}
            .topbar{left:0;}.contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .uchip{display:none;}
            .btn-salir-movil{display:block !important;}
        }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="cerrarMenu()"></div>
<aside class="sidebar" id="sidebar">
    <div class="sb-brand"><img src="../../public/img/logo.png" alt="CREDISOL"><div><h2>CREDISOL</h2><span>Panel del Asesor</span></div></div>
    <div class="sb-user"><div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div><div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>Asesor de Crédito</span></div></div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>Inicio</a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Mis Solicitudes</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:17px;height:17px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Ver Clientes</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <div class="card">
        <form method="GET">
            <div class="buscar-bar">
                <input type="text" name="buscar" class="buscar" placeholder="Buscar por nombre o DNI..." value="<?= $buscar ?>">
                <button type="submit" class="btn-buscar">Buscar</button>
            </div>
        </form>
        <?php if (empty($clientes)): ?>
        <div class="empty">No tienes clientes asignados aún.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Cliente</th><th>Correo</th><th>Mis Solicitudes</th><th>Último Estado</th></tr></thead>
            <tbody>
            <?php
            $bc=['pendiente'=>'bp','en_evaluacion'=>'be','aprobada_asesor'=>'baa','rechazada_asesor'=>'br','aprobada'=>'baa','desembolsada'=>'baa'];
            $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación','aprobada_asesor'=>'Aprobada','rechazada_asesor'=>'Rechazada','aprobada'=>'Aprobada','desembolsada'=>'Desembolsada'];
            foreach ($clientes as $c): ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($c['nombres'].' '.$c['apellidos']) ?></div>
                    <div style="font-size:.72rem;color:#94a3b8;">DNI: <?= $c['dni'] ?></div>
                </td>
                <td style="font-size:.78rem;color:#64748b;"><?= htmlspecialchars($c['correo']) ?></td>
                <td style="text-align:center;font-weight:700;"><?= $c['mis_solicitudes'] ?></td>
                <td><span class="badge <?= $bc[$c['ultimo_estado']]??'bp' ?>"><?= $bt[$c['ultimo_estado']]??'—' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:12px;font-size:.78rem;color:#94a3b8;"><?= count($clientes) ?> clientes asignados</div>
        <?php endif; ?>
    </div>
</main>
<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('abierto');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('abierto');document.getElementById('overlay').classList.remove('show');}
</script>
</body>
</html>