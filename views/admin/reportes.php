<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([3]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];

// Estadísticas generales
$stats = $conn->query("SELECT
    (SELECT COUNT(*) FROM solicitudes) AS total_sol,
    (SELECT COUNT(*) FROM solicitudes WHERE estado='desembolsada') AS desembolsadas,
    (SELECT COUNT(*) FROM solicitudes WHERE estado IN ('rechazada','rechazada_asesor')) AS rechazadas,
    (SELECT COALESCE(SUM(monto),0) FROM desembolsos) AS total_desembolsado,
    (SELECT COALESCE(SUM(saldo_pendiente),0) FROM cartera_prestamos WHERE estado='vigente') AS cartera_vigente,
    (SELECT COALESCE(SUM(saldo),0) FROM cuentas_ahorro WHERE estado='activa') AS total_ahorros,
    (SELECT COUNT(*) FROM usuarios WHERE rol_id=1 AND activo=1) AS total_clientes,
    (SELECT COUNT(*) FROM cuentas_ahorro WHERE estado='activa') AS cuentas_ahorro,
    (SELECT COUNT(*) FROM cartera_prestamos WHERE estado='en_mora') AS en_mora
")->fetch_assoc();

// Desembolsos por mes (últimos 6 meses)
$por_mes = $conn->query(
    "SELECT DATE_FORMAT(fecha,'%b %Y') AS mes,
     DATE_FORMAT(fecha,'%Y-%m') AS mes_ord,
     COUNT(*) AS cantidad,
     COALESCE(SUM(monto),0) AS monto
     FROM desembolsos
     WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY mes_ord, mes ORDER BY mes_ord ASC"
)->fetch_all(MYSQLI_ASSOC);

// Solicitudes por tipo
$por_tipo = $conn->query(
    "SELECT tp.nombre, COUNT(*) AS total, COALESCE(SUM(s.monto_solicitado),0) AS monto_total
     FROM solicitudes s JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     WHERE s.estado='desembolsada'
     GROUP BY tp.nombre ORDER BY total DESC"
)->fetch_all(MYSQLI_ASSOC);

// Solicitudes por estado
$por_estado = $conn->query(
    "SELECT estado, COUNT(*) AS total FROM solicitudes GROUP BY estado"
)->fetch_all(MYSQLI_ASSOC);
$estados = [];
foreach($por_estado as $e) $estados[$e['estado']] = $e['total'];

// Top clientes por deuda
$top_deuda = $conn->query(
    "SELECT CONCAT(u.nombres,' ',u.apellidos) AS cliente, u.dni,
     SUM(cp.saldo_pendiente) AS deuda_total
     FROM cartera_prestamos cp JOIN usuarios u ON cp.cliente_id=u.id
     WHERE cp.estado='vigente' GROUP BY u.id ORDER BY deuda_total DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// Asesores
$asesores = $conn->query(
    "SELECT CONCAT(u.nombres,' ',u.apellidos) AS asesor,
     COUNT(s.id) AS total,
     SUM(s.estado IN ('aprobada','desembolsada','aprobada_asesor')) AS aprobadas,
     SUM(s.estado IN ('rechazada','rechazada_asesor')) AS rechazadas
     FROM usuarios u LEFT JOIN solicitudes s ON s.asesor_id=u.id
     WHERE u.rol_id=2 AND u.activo=1 GROUP BY u.id"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Reportes</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
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
        .menu-btn svg{width:18px;height:18px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;}
        .stat{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.06);border-top:3px solid transparent;}
        .stat.az{border-top-color:#3b82f6;}.stat.ve{border-top-color:#10b981;}.stat.na{border-top-color:#f59e0b;}.stat.ro{border-top-color:#ef4444;}.stat.mo{border-top-color:#8b5cf6;}
        .stat .etq{font-size:.7rem;color:#64748b;font-weight:600;margin-bottom:6px;text-transform:uppercase;}
        .stat .num{font-size:1.6rem;font-weight:800;color:#0f172a;}
        .stat .sub{font-size:.7rem;color:#94a3b8;margin-top:3px;}

        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        .chart-container{position:relative;height:260px;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}

        .barra-wrap{margin-top:4px;}
        .barra{height:7px;background:#e2e8f0;border-radius:20px;overflow:hidden;}
        .barra-fill{height:100%;border-radius:20px;}
        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.85rem;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}.contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .stats{grid-template-columns:1fr 1fr;}
            .grid2{grid-template-columns:1fr;}
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
        <a href="reportes.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Reportes</a>
    </div>
    <div class="sb-footer"><a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a></div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Reportes del Sistema</h1>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <div class="uchip"><div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">

    <!-- ESTADÍSTICAS -->
    <div class="stats">
        <div class="stat az"><div class="etq">Total Solicitudes</div><div class="num"><?= $stats['total_sol']??0 ?></div><div class="sub">Desde el inicio</div></div>
        <div class="stat ve"><div class="etq">Desembolsadas</div><div class="num"><?= $stats['desembolsadas']??0 ?></div><div class="sub">Préstamos entregados</div></div>
        <div class="stat ro"><div class="etq">Rechazadas</div><div class="num"><?= $stats['rechazadas']??0 ?></div><div class="sub">No aprobadas</div></div>
        <div class="stat ve"><div class="etq">Total Desembolsado</div><div class="num" style="font-size:1.1rem;"><?= soles($stats['total_desembolsado']??0) ?></div><div class="sub">Dinero entregado</div></div>
        <div class="stat az"><div class="etq">Cartera Vigente</div><div class="num" style="font-size:1.1rem;"><?= soles($stats['cartera_vigente']??0) ?></div><div class="sub">Por cobrar</div></div>
        <div class="stat mo"><div class="etq">Total Ahorros</div><div class="num" style="font-size:1.1rem;"><?= soles($stats['total_ahorros']??0) ?></div><div class="sub"><?= $stats['cuentas_ahorro']??0 ?> cuentas</div></div>
    </div>

    <!-- GRÁFICOS -->
    <div class="grid2">

        <!-- GRÁFICO DESEMBOLSOS POR MES -->
        <div class="card">
            <h3>Desembolsos por Mes (últimos 6 meses)</h3>
            <?php if (empty($por_mes)): ?>
            <div class="empty">No hay desembolsos registrados aún.</div>
            <?php else: ?>
            <div class="chart-container">
                <canvas id="chartMes"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <!-- GRÁFICO ESTADOS DE SOLICITUDES -->
        <div class="card">
            <h3>Estado de Solicitudes</h3>
            <div class="chart-container">
                <canvas id="chartEstados"></canvas>
            </div>
        </div>
    </div>

    <div class="grid2">

        <!-- PRÉSTAMOS POR TIPO -->
        <div class="card">
            <h3>Préstamos por Tipo</h3>
            <?php if (empty($por_tipo)): ?>
            <div class="empty">No hay datos aún.</div>
            <?php else:
                $max = max(array_column($por_tipo, 'total'));
                $colores = ['Consumo'=>'#3b82f6','Microempresa'=>'#10b981','Vivienda'=>'#f59e0b','Educación'=>'#8b5cf6'];
            ?>
            <div class="chart-container">
                <canvas id="chartTipo"></canvas>
            </div>
            <?php endif; ?>
        </div>

        <!-- ASESORES -->
        <div class="card">
            <h3>Rendimiento de Asesores</h3>
            <?php if (empty($asesores)): ?>
            <div class="empty">No hay asesores registrados.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Asesor</th><th>Total</th><th>Aprobadas</th><th>Rechazadas</th></tr></thead>
                <tbody>
                <?php foreach ($asesores as $a): ?>
                <tr>
                    <td style="font-weight:600;"><?= htmlspecialchars($a['asesor']) ?></td>
                    <td style="font-weight:700;"><?= $a['total'] ?></td>
                    <td style="color:#059669;font-weight:600;"><?= $a['aprobadas'] ?></td>
                    <td style="color:#dc2626;font-weight:600;"><?= $a['rechazadas'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOP DEUDORES -->
    <div class="card">
        <h3>Top 5 Clientes con Mayor Deuda</h3>
        <?php if (empty($top_deuda)): ?>
        <div class="empty">No hay carteras activas.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Cliente</th><th>DNI</th><th>Deuda Total</th></tr></thead>
            <tbody>
            <?php foreach ($top_deuda as $t): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($t['cliente']) ?></td>
                <td style="color:#64748b;"><?= $t['dni'] ?></td>
                <td style="font-weight:700;color:#dc2626;"><?= soles($t['deuda_total']) ?></td>
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

// DATOS PHP → JS
var datosMes    = <?= json_encode($por_mes) ?>;
var datosEstado = <?= json_encode($estados) ?>;
var datosTipo   = <?= json_encode($por_tipo) ?>;

// GRÁFICO 1: Desembolsos por mes
<?php if (!empty($por_mes)): ?>
new Chart(document.getElementById('chartMes'), {
    type: 'bar',
    data: {
        labels: datosMes.map(function(d){return d.mes;}),
        datasets: [{
            label: 'Monto desembolsado (S/)',
            data: datosMes.map(function(d){return parseFloat(d.monto);}),
            backgroundColor: 'rgba(29,78,216,0.8)',
            borderRadius: 6
        },{
            label: 'Cantidad',
            data: datosMes.map(function(d){return parseInt(d.cantidad);}),
            backgroundColor: 'rgba(16,185,129,0.8)',
            borderRadius: 6,
            yAxisID: 'y2'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {legend:{position:'top'}},
        scales: {
            y: {beginAtZero:true, title:{display:true,text:'Monto (S/)'}},
            y2: {beginAtZero:true, position:'right', title:{display:true,text:'Cantidad'}, grid:{drawOnChartArea:false}}
        }
    }
});
<?php endif; ?>

// GRÁFICO 2: Estados de solicitudes
var labelsEstado = Object.keys(datosEstado);
var valoresEstado = Object.values(datosEstado);
var coloresEstado = ['#f59e0b','#3b82f6','#8b5cf6','#10b981','#ef4444','#ef4444','#a7f3d0'];
if(labelsEstado.length > 0){
    new Chart(document.getElementById('chartEstados'), {
        type: 'doughnut',
        data: {
            labels: labelsEstado.map(function(l){
                var n={pendiente:'Pendiente',en_evaluacion:'En evaluación',aprobada_asesor:'Aprobada asesor',aprobada:'Aprobada',rechazada:'Rechazada',rechazada_asesor:'Rechazada asesor',desembolsada:'Desembolsada'};
                return n[l]||l;
            }),
            datasets:[{data:valoresEstado, backgroundColor:coloresEstado, borderWidth:2}]
        },
        options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'right'}}}
    });
}

// GRÁFICO 3: Por tipo
<?php if (!empty($por_tipo)): ?>
new Chart(document.getElementById('chartTipo'), {
    type: 'bar',
    data: {
        labels: datosTipo.map(function(d){return d.nombre;}),
        datasets:[{
            label: 'Cantidad',
            data: datosTipo.map(function(d){return parseInt(d.total);}),
            backgroundColor: ['#3b82f6','#10b981','#f59e0b','#8b5cf6'],
            borderRadius: 6
        }]
    },
    options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}}}
});
<?php endif; ?>
</script>
</body>
</html>