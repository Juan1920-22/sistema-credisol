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

// Registrar movimiento (depósito, retiro, interés)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'movimiento') {
    $cuenta_id   = intval($_POST['cuenta_id']);
    $tipo        = limpiar($_POST['tipo'] ?? '');
    $monto       = floatval($_POST['monto'] ?? 0);
    $descripcion = limpiar($_POST['descripcion'] ?? '');

    if (!$cuenta_id || !$tipo || $monto <= 0) {
        setMensaje("Completa todos los campos correctamente.", "error");
        header("Location: ahorros.php");
        exit;
    }

    // Obtener saldo actual
    $cuenta = $conn->query("SELECT * FROM cuentas_ahorro WHERE id=$cuenta_id AND estado='activa'")->fetch_assoc();
    if (!$cuenta) {
        setMensaje("Cuenta no válida.", "error");
        header("Location: ahorros.php");
        exit;
    }

    $saldo_anterior = $cuenta['saldo'];

    // Calcular nuevo saldo
    if ($tipo == 'deposito' || $tipo == 'interes') {
        $saldo_nuevo = $saldo_anterior + $monto;
    } elseif ($tipo == 'retiro') {
        if ($monto > $saldo_anterior) {
            setMensaje("Saldo insuficiente. Saldo actual: " . soles($saldo_anterior), "error");
            header("Location: ahorros.php?id=$cuenta_id");
            exit;
        }
        $saldo_nuevo = $saldo_anterior - $monto;
    } else {
        setMensaje("Tipo de movimiento no válido.", "error");
        header("Location: ahorros.php");
        exit;
    }

    // Registrar movimiento
    $stmt = $conn->prepare(
        "INSERT INTO movimientos_ahorro (cuenta_id, tipo, monto, saldo_anterior, saldo_despues, descripcion, registrado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("isddss i", $cuenta_id, $tipo, $monto, $saldo_anterior, $saldo_nuevo, $descripcion, $admin_id);
    $stmt->bind_param("isddssi", $cuenta_id, $tipo, $monto, $saldo_anterior, $saldo_nuevo, $descripcion, $admin_id);
    $stmt->execute();

    // Actualizar saldo
    $conn->query("UPDATE cuentas_ahorro SET saldo=$saldo_nuevo WHERE id=$cuenta_id");

    // Notificar al cliente
    $tipos_txt = ['deposito'=>'depósito','retiro'=>'retiro','interes'=>'acreditación de intereses'];
    $msg = "Se registró un {$tipos_txt[$tipo]} de " . soles($monto) . " en tu cuenta de ahorros. Saldo actual: " . soles($saldo_nuevo);
    $notif = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Movimiento en tu cuenta', ?, 'info')");
    $notif->bind_param("is", $cuenta['cliente_id'], $msg);
    $notif->execute();

    $tipos_msg = ['deposito'=>'Depósito','retiro'=>'Retiro','interes'=>'Interés'];
    setMensaje("{$tipos_msg[$tipo]} de " . soles($monto) . " registrado correctamente.", "exito");
    header("Location: ahorros.php?id=$cuenta_id");
    exit;
}

// Obtener todas las cuentas de ahorro
$cuentas = $conn->query(
    "SELECT ca.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente, u.dni
     FROM cuentas_ahorro ca
     JOIN usuarios u ON ca.cliente_id = u.id
     ORDER BY ca.saldo DESC"
)->fetch_all(MYSQLI_ASSOC);

// Detalle de cuenta seleccionada
$detalle   = null;
$movimientos = [];
if (isset($_GET['id'])) {
    $cid = intval($_GET['id']);
    $detalle = $conn->query(
        "SELECT ca.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente, u.dni, u.telefono, u.correo
         FROM cuentas_ahorro ca JOIN usuarios u ON ca.cliente_id=u.id WHERE ca.id=$cid"
    )->fetch_assoc();
    if ($detalle) {
        $movimientos = $conn->query(
            "SELECT m.*, CONCAT(u.nombres,' ',u.apellidos) AS registrado_nombre
             FROM movimientos_ahorro m
             LEFT JOIN usuarios u ON m.registrado_por=u.id
             WHERE m.cuenta_id=$cid ORDER BY m.fecha DESC LIMIT 30"
        )->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Gestionar Ahorros</title>
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

        .grid2{display:grid;grid-template-columns:1fr 360px;gap:20px;}
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);margin-bottom:20px;}
        .card h3{font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}

        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:9px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        tbody tr:hover{background:#f8fafc;}
        tbody tr.sel{background:#f0fdf4;}

        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .b-dep{background:#d1fae5;color:#065f46;}
        .b-ret{background:#fee2e2;color:#991b1b;}
        .b-int{background:#dbeafe;color:#1e40af;}
        .b-ape{background:#f3f4f6;color:#374151;}

        .btn-sm{padding:5px 11px;border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;border:none;text-decoration:none;display:inline-block;}
        .btn-ver{background:#eff6ff;color:#1d4ed8;}

        .det-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:.84rem;}
        .det-row:last-child{border-bottom:none;}
        .det-row span:first-child{color:#64748b;}
        .det-row span:last-child{font-weight:600;color:#0f172a;}

        .saldo-grande{font-size:2rem;font-weight:800;color:#059669;text-align:center;padding:16px;background:#f0fdf4;border-radius:10px;margin:12px 0;}

        .tabs{display:flex;gap:0;margin-bottom:16px;border-radius:8px;overflow:hidden;border:1.5px solid #e2e8f0;}
        .tab{flex:1;padding:10px;text-align:center;font-size:.82rem;font-weight:600;color:#64748b;background:#f8fafc;cursor:pointer;border:none;transition:all .2s;}
        .tab.activo{background:#059669;color:#fff;}

        .form-grupo{margin-bottom:13px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.82rem;margin-bottom:5px;}
        .form-grupo input,.form-grupo select,.form-grupo textarea{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#f9fafb;outline:none;transition:border .2s;}
        .form-grupo input:focus,.form-grupo select:focus{border-color:#059669;background:#fff;}

        .btn-registrar{width:100%;padding:12px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;margin-top:6px;}
        .btn-registrar:hover{opacity:.9;}

        .sin-sel{text-align:center;padding:48px 20px;color:#94a3b8;}
        .sin-sel svg{width:52px;height:52px;margin:0 auto 14px;display:block;color:#cbd5e1;}
        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.86rem;}

        @media(max-width:900px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
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
        <a href="solicitudes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Todas las Solicitudes</a>
        <a href="aprobaciones.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Aprobaciones Finales</a>
        <a href="desembolsos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Desembolsos</a>
        <div class="menu-lbl">Clientes</div>
        <a href="clientes.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>Ver Clientes</a>
        <a href="ahorros.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Gestionar Ahorros</a>
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
        <h1>Gestionar Ahorros</h1>
    </div>
    <div class="uchip">
        <div class="ava"><?= strtoupper(substr($nombre,0,1)) ?></div>
        <span><?= htmlspecialchars($nombre) ?></span>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="grid2">

        <!-- LISTA CUENTAS -->
        <div>
            <div class="card">
                <h3>Cuentas de Ahorro de Clientes</h3>
                <?php if (empty($cuentas)): ?>
                <div class="empty">No hay cuentas de ahorro abiertas aún.</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Cliente</th><th>N° Cuenta</th><th>Saldo</th><th>Estado</th><th>Ver</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cuentas as $c): ?>
                    <tr class="<?= ($detalle && $detalle['id']==$c['id']) ? 'sel' : '' ?>">
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($c['cliente']) ?></div>
                            <div style="font-size:.72rem;color:#94a3b8;">DNI: <?= $c['dni'] ?></div>
                        </td>
                        <td style="font-size:.8rem;color:#1d4ed8;font-weight:600;"><?= $c['numero_cuenta'] ?></td>
                        <td style="font-weight:700;color:#059669;"><?= soles($c['saldo']) ?></td>
                        <td><span class="badge <?= $c['estado']=='activa'?'b-dep':'b-ret' ?>"><?= ucfirst($c['estado']) ?></span></td>
                        <td><a href="ahorros.php?id=<?= $c['id'] ?>" class="btn-sm btn-ver">Gestionar</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- MOVIMIENTOS -->
            <?php if ($detalle && !empty($movimientos)): ?>
            <div class="card">
                <h3>Movimientos — <?= $detalle['numero_cuenta'] ?></h3>
                <table>
                    <thead>
                        <tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Saldo Anterior</th><th>Saldo Después</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $bc=['deposito'=>'b-dep','retiro'=>'b-ret','interes'=>'b-int','apertura'=>'b-ape'];
                    $bt=['deposito'=>'Depósito','retiro'=>'Retiro','interes'=>'Interés','apertura'=>'Apertura'];
                    foreach ($movimientos as $m):
                        $color = $m['tipo']=='retiro' ? '#dc2626' : '#059669';
                        $signo = $m['tipo']=='retiro' ? '- ' : '+ ';
                        if ($m['tipo']=='apertura') { $signo=''; $color='#374151'; }
                    ?>
                    <tr>
                        <td style="color:#64748b;"><?= fechaCorta($m['fecha']) ?></td>
                        <td><span class="badge <?= $bc[$m['tipo']]??'b-ape' ?>"><?= $bt[$m['tipo']]??$m['tipo'] ?></span></td>
                        <td style="font-weight:700;color:<?= $color ?>"><?= $signo ?><?= soles($m['monto']) ?></td>
                        <td style="color:#64748b;"><?= soles($m['saldo_anterior']) ?></td>
                        <td style="font-weight:600;"><?= soles($m['saldo_despues']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- PANEL DERECHO -->
        <div>
            <?php if (!$detalle): ?>
            <div class="card">
                <div class="sin-sel">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <p>Selecciona una cuenta para registrar depósitos, retiros o intereses.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <h3>Cuenta de <?= htmlspecialchars($detalle['cliente']) ?></h3>
                <div class="det-row"><span>N° Cuenta</span><span style="color:#1d4ed8;"><?= $detalle['numero_cuenta'] ?></span></div>
                <div class="det-row"><span>DNI</span><span><?= $detalle['dni'] ?></span></div>
                <div class="det-row"><span>Correo</span><span style="font-size:.78rem;"><?= $detalle['correo'] ?></span></div>
                <div class="det-row"><span>Tasa interés</span><span><?= $detalle['tasa_interes'] ?>% anual</span></div>
                <div class="det-row"><span>Apertura</span><span><?= fechaCorta($detalle['fecha_apertura']) ?></span></div>

                <div class="saldo-grande"><?= soles($detalle['saldo']) ?></div>

                <!-- TABS -->
                <div class="tabs">
                    <button class="tab activo" onclick="cambiarTab('deposito',this)">Depósito</button>
                    <button class="tab" onclick="cambiarTab('retiro',this)">Retiro</button>
                    <button class="tab" onclick="cambiarTab('interes',this)">Interés</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="accion" value="movimiento">
                    <input type="hidden" name="cuenta_id" value="<?= $detalle['id'] ?>">
                    <input type="hidden" name="tipo" id="tipo_input" value="deposito">

                    <div class="form-grupo">
                        <label>Monto (S/) *</label>
                        <input type="number" name="monto" placeholder="0.00" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-grupo">
                        <label>Descripción</label>
                        <input type="text" name="descripcion" id="desc_input" placeholder="Depósito en efectivo en oficina">
                    </div>

                    <button type="submit" class="btn-registrar" id="btn-registrar">
                        Registrar Depósito
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}

function cambiarTab(tipo, btn) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('activo'));
    btn.classList.add('activo');
    document.getElementById('tipo_input').value = tipo;
    var textos = {
        'deposito': {desc:'Depósito en efectivo en oficina', btn:'Registrar Depósito'},
        'retiro':   {desc:'Retiro solicitado por el cliente', btn:'Registrar Retiro'},
        'interes':  {desc:'Acreditación de intereses mensuales', btn:'Acreditar Intereses'}
    };
    document.getElementById('desc_input').placeholder = textos[tipo].desc;
    document.getElementById('btn-registrar').textContent = textos[tipo].btn;
}
</script>
</body>
</html>