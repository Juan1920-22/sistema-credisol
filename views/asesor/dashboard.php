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

$stats = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(estado='pendiente') AS pendientes,
    SUM(estado='en_evaluacion') AS en_evaluacion,
    SUM(estado IN ('aprobada_asesor','aprobada','desembolsada')) AS aprobadas,
    SUM(estado IN ('rechazada_asesor','rechazada')) AS rechazadas
    FROM solicitudes WHERE asesor_id=$asesor_id")->fetch_assoc();

$pendientes = $conn->query(
    "SELECT s.*, tp.nombre AS tipo,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente, c.dni AS cliente_dni
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     JOIN usuarios c ON s.cliente_id=c.id
     WHERE s.asesor_id=$asesor_id AND s.estado IN ('pendiente','en_evaluacion')
     ORDER BY s.fecha_solicitud ASC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$evaluadas = $conn->query(
    "SELECT s.*, tp.nombre AS tipo,
     CONCAT(c.nombres,' ',c.apellidos) AS cliente
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     JOIN usuarios c ON s.cliente_id=c.id
     WHERE s.asesor_id=$asesor_id AND s.estado IN ('aprobada_asesor','rechazada_asesor')
     ORDER BY s.fecha_evaluacion DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Panel del Asesor</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}

        /* SIDEBAR */
        .sidebar{
            position:fixed;top:0;left:0;width:250px;height:100vh;
            background:linear-gradient(180deg,#0a2463,#1e3a8a);
            display:flex;flex-direction:column;z-index:200;
            overflow:hidden;
        }
        .sb-menu{padding:10px 0;flex:1;overflow-y:auto;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#6ee7b7;font-size:.68rem;}
        .menu-lbl{padding:10px 20px 4px;font-size:.62rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.1em;}
        .sb-menu a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.65);text-decoration:none;font-size:.88rem;transition:all .15s;border-left:3px solid transparent;}
        .sb-menu a:hover,.sb-menu a.activo{background:rgba(255,255,255,.07);color:#fff;border-left-color:#10b981;}
        .sb-menu a.activo{font-weight:600;}
        .sb-menu a svg{width:17px;height:17px;flex-shrink:0;}
        .bnot{margin-left:auto;background:#ef4444;color:#fff;font-size:.67rem;font-weight:700;padding:2px 6px;border-radius:20px;}
        .sb-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
        .sb-footer a{display:flex;align-items:center;gap:8px;color:#f87171;font-size:.88rem;font-weight:700;text-decoration:none;padding:10px 0;}

        /* TOPBAR */
        .topbar{
            position:fixed;top:0;left:250px;right:0;height:62px;
            background:#fff;border-bottom:1px solid #e2e8f0;
            display:flex;align-items:center;justify-content:space-between;
            padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);
        }
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:5px 12px;}
        .uchip .ava{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#065f46;}

        /* CONTENIDO */
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}

        /* OVERLAY */
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150;}
        .overlay.show{display:block;}

        /* BOTÓN MENÚ - solo en móvil */
        .menu-btn{
            display:none;
            background:#1d4ed8;border:none;cursor:pointer;
            color:#fff;padding:8px 16px;border-radius:8px;
            font-size:.85rem;font-weight:700;
            align-items:center;gap:6px;
        }
        .menu-btn svg{width:18px;height:18px;}

        /* BANNER */
        .banner{background:linear-gradient(135deg,#065f46,#059669,#10b981);border-radius:14px;padding:24px 28px;color:#fff;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;}
        .banner h2{font-size:1.3rem;font-weight:800;margin-bottom:4px;}
        .banner p{font-size:.85rem;opacity:.82;}
        .banner-badge{background:rgba(255,255,255,.2);padding:6px 16px;border-radius:20px;font-size:.8rem;font-weight:700;white-space:nowrap;}

        /* STATS */
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-top:3px solid transparent;}
        .stat.az{border-top-color:#3b82f6;}.stat.na{border-top-color:#f59e0b;}.stat.ve{border-top-color:#10b981;}.stat.ro{border-top-color:#ef4444;}
        .stat .etq{font-size:.7rem;color:#64748b;font-weight:600;margin-bottom:6px;text-transform:uppercase;}
        .stat .num{font-size:1.7rem;font-weight:800;color:#0f172a;}
        .stat .sub{font-size:.7rem;color:#94a3b8;margin-top:3px;}

        /* GRID */
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .ct{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
        .ct a{font-size:.76rem;color:#10b981;font-weight:600;text-decoration:none;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .bp{background:#fef3c7;color:#92400e;}.be{background:#dbeafe;color:#1e40af;}.baa{background:#d1fae5;color:#065f46;}.br{background:#fee2e2;color:#991b1b;}

        .btn-sm{padding:6px 12px;border-radius:6px;font-size:.78rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-eval{background:#d1fae5;color:#065f46;}
        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.84rem;}

        /* RESPONSIVE */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s ease;width:280px;}
            .sidebar.abierto{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .stats{grid-template-columns:1fr 1fr;}
            .grid2{grid-template-columns:1fr;}
            .banner{flex-direction:column;gap:12px;text-align:center;}
            .banner-badge{display:none;}
            .uchip{display:none;}
            .btn-salir-movil{display:block !important;}
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

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="cerrarMenu()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <div><h2>CREDISOL</h2><span>Panel del Asesor</span></div>
    </div>
    <div class="sb-user">
        <div class="av"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <div>
            <p><?= htmlspecialchars($nombre.' '.$apellido) ?></p>
            <span>Asesor de Crédito</span>
        </div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Principal</div>
        <a href="dashboard.php" class="activo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Inicio
        </a>
        <div class="menu-lbl">Solicitudes</div>
        <a href="solicitudes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Mis Solicitudes
            <?php if(($stats['pendientes']??0)+($stats['en_evaluacion']??0)>0): ?>
            <span class="bnot"><?= ($stats['pendientes']??0)+($stats['en_evaluacion']??0) ?></span>
            <?php endif; ?>
        </a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Ver Clientes
        </a>
    </div>
    <!-- CERRAR SESIÓN SIEMPRE VISIBLE -->
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Cerrar Sesión
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" id="menuBtn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menú
        </button>
        <h1>Panel del Asesor</h1>
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
        <div class="uchip">
            <div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div>
            <span><?= htmlspecialchars($nombre) ?></span>
        </div>
        <!-- CERRAR SESIÓN VISIBLE EN MÓVIL -->
        <a href="../../controllers/AuthController.php?accion=logout"
           style="display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;"
           class="btn-salir-movil">
           Salir
        </a>
    </div>
</header>

<!-- CONTENIDO -->
<main class="contenido">

    <div class="banner">
        <div>
            <h2>Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
            <p>Revisa y evalúa las solicitudes de préstamo asignadas a ti.</p>
        </div>
        <span class="banner-badge">Asesor de Crédito</span>
    </div>

    <div class="stats">
        <div class="stat az"><div class="etq">Total Asignadas</div><div class="num"><?= $stats['total']??0 ?></div><div class="sub">Historial</div></div>
        <div class="stat na"><div class="etq">Por Evaluar</div><div class="num"><?= ($stats['pendientes']??0)+($stats['en_evaluacion']??0) ?></div><div class="sub">Urgente</div></div>
        <div class="stat ve"><div class="etq">Aprobadas</div><div class="num"><?= $stats['aprobadas']??0 ?></div><div class="sub">Enviadas al jefe</div></div>
        <div class="stat ro"><div class="etq">Rechazadas</div><div class="num"><?= $stats['rechazadas']??0 ?></div><div class="sub">No aprobadas</div></div>
    </div>

    <div class="grid2">
        <div class="card">
            <div class="ct">
                Solicitudes por Evaluar
                <a href="solicitudes.php">Ver todas</a>
            </div>
            <?php if (empty($pendientes)): ?>
            <div class="empty">No tienes solicitudes pendientes.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($pendientes as $s):
                    $bc=['pendiente'=>'bp','en_evaluacion'=>'be'];
                    $bt=['pendiente'=>'Pendiente','en_evaluacion'=>'En evaluación'];
                ?>
                <tr>
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($s['cliente']) ?></div>
                        <div style="font-size:.72rem;color:#94a3b8;"><?= $s['tipo'] ?></div>
                    </td>
                    <td style="font-weight:600;"><?= soles($s['monto_solicitado']) ?></td>
                    <td><span class="badge <?= $bc[$s['estado']]??'bp' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span></td>
                    <td><a href="solicitudes.php?id=<?= $s['id'] ?>" class="btn-sm btn-eval">Evaluar</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="ct">Últimas Evaluadas</div>
            <?php if (empty($evaluadas)): ?>
            <div class="empty">Aún no has evaluado ninguna solicitud.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Código</th><th>Cliente</th><th>Monto</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach ($evaluadas as $s):
                    $bc=['aprobada_asesor'=>'baa','rechazada_asesor'=>'br'];
                    $bt=['aprobada_asesor'=>'Aprobada','rechazada_asesor'=>'Rechazada'];
                ?>
                <tr>
                    <td style="font-weight:700;color:#1d4ed8;"><?= $s['codigo'] ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($s['cliente']) ?></td>
                    <td><?= soles($s['monto_solicitado']) ?></td>
                    <td><span class="badge <?= $bc[$s['estado']]??'' ?>"><?= $bt[$s['estado']]??$s['estado'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function abrirMenu() {
    document.getElementById('sidebar').classList.add('abierto');
    document.getElementById('overlay').classList.add('show');
}
function cerrarMenu() {
    document.getElementById('sidebar').classList.remove('abierto');
    document.getElementById('overlay').classList.remove('show');
}
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
        var iconos={exito:'✅',error:'❌',info:'ℹ️',advertencia:'⚠️'};
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
</body>
</html>