<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

// Filtros
$filtro_estado = limpiar($_GET['estado'] ?? '');
$filtro_buscar = limpiar($_GET['buscar'] ?? '');

$sql = "SELECT s.*, tp.nombre AS tipo,
        CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.dni AS cliente_dni,
        CONCAT(a.nombres,' ',a.apellidos) AS asesor
        FROM solicitudes s
        JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
        JOIN usuarios c ON s.cliente_id = c.id
        LEFT JOIN usuarios a ON s.asesor_id = a.id
        WHERE 1=1";

if ($filtro_estado) $sql .= " AND s.estado='$filtro_estado'";
if ($filtro_buscar) $sql .= " AND (s.codigo LIKE '%$filtro_buscar%' OR c.nombres LIKE '%$filtro_buscar%' OR c.apellidos LIKE '%$filtro_buscar%' OR c.dni LIKE '%$filtro_buscar%')";

$sql .= " ORDER BY s.fecha_solicitud DESC";
$solicitudes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Conteo por estado
$conteos = $conn->query("SELECT estado, COUNT(*) AS total FROM solicitudes GROUP BY estado")->fetch_all(MYSQLI_ASSOC);
$por_estado = [];
foreach ($conteos as $c) $por_estado[$c['estado']] = $c['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Todas las Solicitudes</title>
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
        .menu-btn{display:none;background:none;border:none;cursor:pointer;color:#64748b;padding:4px;}
        .menu-btn svg{width:22px;height:22px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}

        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-left:4px solid transparent;cursor:pointer;text-decoration:none;display:block;transition:transform .2s;}
        .stat:hover{transform:translateY(-2px);}
        .stat.az{border-left-color:#3b82f6;}.stat.na{border-left-color:#f59e0b;}.stat.ve{border-left-color:#10b981;}.stat.ro{border-left-color:#ef4444;}
        .stat .n{font-size:1.6rem;font-weight:800;color:#0f172a;}
        .stat .l{font-size:.72rem;color:#64748b;margin-top:2px;}

        .filtros-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
        .buscar{flex:1;min-width:200px;padding:9px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.88rem;background:#fff;outline:none;}
        .buscar:focus{border-color:#1d4ed8;}
        .btn-buscar{padding:9px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;}
        .filtro-btn{padding:7px 14px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid #e2e8f0;color:#64748b;background:#fff;}
        .filtro-btn.activo{background:#1d4ed8;color:#fff;border-color:#1d4ed8;}

        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.ba{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}.bd{background:#a7f3d0;color:#064e3b;}.baa{background:#ede9fe;color:#5b21b6;}

        .empty{text-align:center;padding:40px;color:#94a3b8;font-size:.86rem;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .stats{grid-template-columns:1fr 1fr;}
        }
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
        <a href="solicitudes.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Todas las Solicitudes</a>
        <a href="aprobaciones.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Aprobaciones Finales</a>
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
        <h1>Todas las Solicitudes</h1>
    </div>
    <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>

    <!-- STATS -->
    <div class="stats">
        <a href="solicitudes.php?estado=pendiente" class="stat na">
            <div class="n"><?= $por_estado['pendiente']??0 ?></div>
            <div class="l">Pendientes</div>
        </a>
        <a href="solicitudes.php?estado=en_evaluacion" class="stat az">
            <div class="n"><?= $por_estado['en_evaluacion']??0 ?></div>
            <div class="l">En Evaluación</div>
        </a>
        <a href="solicitudes.php?estado=aprobada" class="stat ve">
            <div class="n"><?= ($por_estado['aprobada']??0)+($por_estado['desembolsada']??0) ?></div>
            <div class="l">Aprobadas</div>
        </a>
        <a href="solicitudes.php?estado=rechazada" class="stat ro">
            <div class="n"><?= ($por_estado['rechazada']??0)+($por_estado['rechazada_asesor']??0) ?></div>
            <div class="l">Rechazadas</div>
        </a>
    </div>

    <div class="card">
        <!-- BARRA DE BÚSQUEDA Y FILTROS -->
        <form method="GET" style="margin-bottom:16px;">
            <div class="filtros-bar">
                <input type="text" name="buscar" class="buscar" placeholder="Buscar por código, nombre o DNI..." value="<?= $filtro_buscar ?>">
                <button type="submit" class="btn-buscar">Buscar</button>
                <a href="solicitudes.php" class="filtro-btn <?= !$filtro_estado&&!$filtro_buscar?'activo':'' ?>">Todas</a>
                <a href="solicitudes.php?estado=pendiente" class="filtro-btn <?= $filtro_estado=='pendiente'?'activo':'' ?>">Pendientes</a>
                <a href="solicitudes.php?estado=en_evaluacion" class="filtro-btn <?= $filtro_estado=='en_evaluacion'?'activo':'' ?>">En evaluación</a>
                <a href="solicitudes.php?estado=aprobada_asesor" class="filtro-btn <?= $filtro_estado=='aprobada_asesor'?'activo':'' ?>">Para aprobar</a>
                <a href="solicitudes.php?estado=desembolsada" class="filtro-btn <?= $filtro_estado=='desembolsada'?'activo':'' ?>">Desembolsadas</a>
            </div>
        </form>

        <?php if (empty($solicitudes)): ?>
        <div class="empty">No se encontraron solicitudes.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Código</th><th>Cliente</th><th>Tipo</th><th>Monto</th><th>Asesor</th><th>Fecha</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php
            $bc=['pendiente'=>'bp','en_evaluacion'=>'be','aprobada_asesor'=>'baa','aprobada'=>'ba','rechazada'=>'br','rechazada_asesor'=>'br','desembolsada'=>'bd'];
            $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación','aprobada_asesor'=>'Para aprobar','aprobada'=>'Aprobada','rechazada'=>'Rechazada','rechazada_asesor'=>'Rechazada','desembolsada'=>'Desembolsada'];
            foreach ($solicitudes as $s): ?>
            <tr>
                <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($s['cliente']) ?></div>
                    <div style="font-size:.72rem;color:#94a3b8;">DNI: <?= $s['cliente_dni'] ?></div>
                </td>
                <td><?= $s['tipo'] ?></td>
                <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                <td style="color:#64748b;font-size:.78rem;"><?= htmlspecialchars($s['asesor']??'Sin asignar') ?></td>
                <td style="color:#64748b;"><?= fechaCorta($s['fecha_solicitud']) ?></td>
                <td><span class="badge <?= $bc[$s['estado']]??'' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:12px;font-size:.78rem;color:#94a3b8;">
            Mostrando <?= count($solicitudes) ?> solicitudes
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>
</body>
</html>