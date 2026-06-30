<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$q        = limpiar($_GET['q'] ?? '');
$resultados = [];

if ($q && strlen($q) >= 2) {
    // Buscar clientes
    $r = $conn->query("SELECT 'cliente' AS tipo, id, CONCAT(nombres,' ',apellidos) AS titulo,
        CONCAT('DNI: ',dni,' | ',correo) AS subtitulo,
        'clientes.php' AS url
        FROM usuarios WHERE rol_id=1
        AND (nombres LIKE '%$q%' OR apellidos LIKE '%$q%' OR dni LIKE '%$q%' OR correo LIKE '%$q%')
        LIMIT 5");
    if ($r) $resultados = array_merge($resultados, $r->fetch_all(MYSQLI_ASSOC));

    // Buscar solicitudes
    $r2 = $conn->query("SELECT 'solicitud' AS tipo, s.id,
        CONCAT(s.codigo,' — ',CONCAT(u.nombres,' ',u.apellidos)) AS titulo,
        CONCAT(tp.nombre,' | S/ ',s.monto_solicitado,' | ',s.estado) AS subtitulo,
        CONCAT('solicitudes.php?buscar=',s.codigo) AS url
        FROM solicitudes s
        JOIN usuarios u ON s.cliente_id=u.id
        JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
        WHERE s.codigo LIKE '%$q%' OR u.nombres LIKE '%$q%' OR u.apellidos LIKE '%$q%' OR u.dni LIKE '%$q%'
        LIMIT 5");
    if ($r2) $resultados = array_merge($resultados, $r2->fetch_all(MYSQLI_ASSOC));

    // Buscar asesores
    $r3 = $conn->query("SELECT 'asesor' AS tipo, id,
        CONCAT(nombres,' ',apellidos) AS titulo,
        CONCAT('DNI: ',dni,' | Asesor de Crédito') AS subtitulo,
        'usuarios.php?rol=2' AS url
        FROM usuarios WHERE rol_id=2
        AND (nombres LIKE '%$q%' OR apellidos LIKE '%$q%' OR dni LIKE '%$q%')
        LIMIT 3");
    if ($r3) $resultados = array_merge($resultados, $r3->fetch_all(MYSQLI_ASSOC));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Búsqueda</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:260px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow:hidden;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#fcd34d;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;overflow-y:auto;}
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
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;align-items:center;gap:6px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        .search-box{background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:22px;}
        .search-input-wrap{display:flex;gap:10px;}
        .search-input{flex:1;padding:13px 18px;border:2px solid #e2e8f0;border-radius:10px;font-size:1rem;outline:none;transition:border .2s;}
        .search-input:focus{border-color:#1d4ed8;}
        .btn-search{padding:13px 24px;background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:700;cursor:pointer;}
        .search-hint{font-size:.8rem;color:#94a3b8;margin-top:10px;}

        .resultado{display:flex;align-items:center;gap:14px;padding:14px 18px;background:#fff;border-radius:10px;margin-bottom:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);text-decoration:none;transition:transform .15s,box-shadow .15s;}
        .resultado:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1);}
        .res-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
        .res-ico.cliente{background:#dbeafe;}.res-ico.solicitud{background:#d1fae5;}.res-ico.asesor{background:#fef3c7;}
        .res-titulo{font-size:.9rem;font-weight:700;color:#0f172a;margin-bottom:3px;}
        .res-sub{font-size:.78rem;color:#64748b;}
        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;margin-left:8px;}
        .b-cli{background:#dbeafe;color:#1e40af;}
        .b-sol{background:#d1fae5;color:#065f46;}
        .b-ase{background:#fef3c7;color:#92400e;}

        .empty{text-align:center;padding:40px;color:#94a3b8;}
        .empty svg{width:56px;height:56px;margin:0 auto 14px;display:block;color:#cbd5e1;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
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
    <div class="sb-brand"><img src="../../public/img/logo.png" alt="CREDISOL"><div><h2>CREDISOL</h2><span>Panel de Administración</span></div></div>
    <div class="sb-user"><div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div><div><p><?= htmlspecialchars($nombre.' '.$apellido) ?></p><span>&#9733; Administrador General</span></div></div>
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
        <a href="pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Registrar Pagos</a>
        <div class="menu-lbl">Usuarios</div>
        <a href="usuarios.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Gestionar Usuarios</a>
        <div class="menu-lbl">Sistema</div>
        <a href="buscar.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>Búsqueda Global</a>
        <a href="reportes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
    </div>
    <div class="sb-footer"><a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a></div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Búsqueda Global</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <div class="search-box">
        <form method="GET">
            <div class="search-input-wrap">
                <input type="text" name="q" class="search-input"
                       placeholder="Buscar por nombre, DNI, código de solicitud..."
                       value="<?= htmlspecialchars($q) ?>" autofocus>
                <button type="submit" class="btn-search">Buscar</button>
            </div>
            <p class="search-hint">Busca clientes por nombre o DNI, solicitudes por código, o asesores por nombre.</p>
        </form>
    </div>

    <?php if ($q): ?>
    <div style="font-size:.88rem;color:#64748b;margin-bottom:14px;">
        <?= count($resultados) ?> resultado(s) para "<strong><?= htmlspecialchars($q) ?></strong>"
    </div>

    <?php if (empty($resultados)): ?>
    <div class="empty">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <p>No se encontraron resultados para "<?= htmlspecialchars($q) ?>"</p>
    </div>
    <?php else: ?>
    <?php
    $iconos = ['cliente'=>'👤', 'solicitud'=>'📋', 'asesor'=>'🧑‍💼'];
    $badges = ['cliente'=>'b-cli', 'solicitud'=>'b-sol', 'asesor'=>'b-ase'];
    $nombres_tipo = ['cliente'=>'Cliente', 'solicitud'=>'Solicitud', 'asesor'=>'Asesor'];
    foreach ($resultados as $r): ?>
    <a href="<?= $r['url'] ?>" class="resultado">
        <div class="res-ico <?= $r['tipo'] ?>"><?= $iconos[$r['tipo']] ?></div>
        <div style="flex:1">
            <div class="res-titulo">
                <?= htmlspecialchars($r['titulo']) ?>
                <span class="badge <?= $badges[$r['tipo']] ?>"><?= $nombres_tipo[$r['tipo']] ?></span>
            </div>
            <div class="res-sub"><?= htmlspecialchars($r['subtitulo']) ?></div>
        </div>
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:#94a3b8;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>
</body>
</html>