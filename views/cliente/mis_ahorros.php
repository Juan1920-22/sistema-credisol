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

// Cargar foto de perfil desde BD si no está en sesión
if (empty($_SESSION['foto'])) {
    $u = $conn->query("SELECT foto FROM usuarios WHERE id=$id")->fetch_assoc();
    if ($u && $u['foto']) $_SESSION['foto'] = $u['foto'];
}

// Abrir cuenta de ahorros
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['accion']??'') == 'abrir_cuenta') {
    $existe = $conn->query("SELECT id FROM cuentas_ahorro WHERE cliente_id=$id AND estado='activa'")->fetch_assoc();
    if ($existe) {
        setMensaje("Ya tienes una cuenta de ahorros activa.", "advertencia");
    } else {
        // Generar número de cuenta único
        $numero = '2026-' . str_pad($id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
        $stmt = $conn->prepare(
            "INSERT INTO cuentas_ahorro (cliente_id, numero_cuenta, saldo, tasa_interes, estado, fecha_apertura)
             VALUES (?, ?, 0.00, 3.50, 'activa', NOW())"
        );
        $stmt->bind_param("is", $id, $numero);
        $stmt->execute();
        $cid = $conn->insert_id;

        // Registrar movimiento de apertura
        $conn->query("INSERT INTO movimientos_ahorro (cuenta_id, tipo, monto, saldo_anterior, saldo_despues, descripcion)
                      VALUES ($cid, 'apertura', 0, 0, 0, 'Apertura de cuenta de ahorros')");

        setMensaje("¡Cuenta de ahorros abierta! Tu número de cuenta es: $numero", "exito");
    }
    header("Location: mis_ahorros.php");
    exit;
}

// Obtener cuenta del cliente
$cuenta = $conn->query(
    "SELECT * FROM cuentas_ahorro WHERE cliente_id=$id AND estado='activa' LIMIT 1"
)->fetch_assoc();

// Obtener movimientos
$movimientos = [];
if ($cuenta) {
    $movimientos = $conn->query(
        "SELECT * FROM movimientos_ahorro WHERE cuenta_id={$cuenta['id']} ORDER BY fecha DESC LIMIT 20"
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Mis Ahorros</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        .sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:linear-gradient(180deg,#0a2463,#1e3a8a);display:flex;flex-direction:column;z-index:100;overflow:hidden;}
        .sb-brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;}
        .sb-brand img{width:38px;height:38px;border-radius:8px;background:#fff;padding:3px;}
        .sb-brand div h2{color:#fff;font-size:.95rem;font-weight:800;}
        .sb-brand div span{color:#93c5fd;font-size:.68rem;}
        .sb-user{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px;}
        .avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.95rem;flex-shrink:0;}
        .sb-user p{color:#fff;font-size:.82rem;font-weight:600;}
        .sb-user span{color:#93c5fd;font-size:.68rem;}
        .sb-menu{padding:10px 0;flex:1;overflow-y:auto;}
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
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:18px;height:18px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        .btn-salir-movil{display:none;background:#ef4444;color:#fff;padding:8px 14px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;}

        /* TARJETA BANCARIA */
        .tarjeta{
            background:linear-gradient(135deg,#0a2463,#1d4ed8,#0ea5e9);
            border-radius:20px;
            padding:28px 32px;
            color:#fff;
            position:relative;
            overflow:hidden;
            margin-bottom:24px;
            box-shadow:0 8px 32px rgba(29,78,216,.35);
        }
        .tarjeta::before{
            content:'';position:absolute;
            width:280px;height:280px;
            background:rgba(255,255,255,.06);
            border-radius:50%;
            top:-80px;right:-80px;
        }
        .tarjeta::after{
            content:'';position:absolute;
            width:200px;height:200px;
            background:rgba(255,255,255,.04);
            border-radius:50%;
            bottom:-60px;left:-40px;
        }
        .tarjeta-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;position:relative;z-index:1;}
        .tarjeta-banco{font-size:.75rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;opacity:.8;}
        .tarjeta-chip{width:44px;height:34px;background:linear-gradient(135deg,#fbbf24,#f59e0b);border-radius:6px;display:flex;align-items:center;justify-content:center;}
        .tarjeta-chip svg{width:28px;height:28px;color:rgba(0,0,0,.3);}
        .tarjeta-numero{font-size:1.25rem;font-weight:700;letter-spacing:.18em;margin-bottom:24px;position:relative;z-index:1;font-family:monospace;}
        .tarjeta-footer{display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:1;}
        .tarjeta-titular .lbl{font-size:.6rem;opacity:.6;text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px;}
        .tarjeta-titular .val{font-size:.9rem;font-weight:700;text-transform:uppercase;}
        .tarjeta-saldo{text-align:right;}
        .tarjeta-saldo .lbl{font-size:.6rem;opacity:.6;text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px;}
        .tarjeta-saldo .val{font-size:1.5rem;font-weight:800;}
        .tarjeta-tasa{font-size:.72rem;opacity:.7;margin-top:2px;}

        /* INFO CARDS */
        .info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px;}
        .info-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.06);text-align:center;}
        .info-card .ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
        .info-card .ico svg{width:22px;height:22px;}
        .info-card .val{font-size:1.2rem;font-weight:800;color:#0f172a;}
        .info-card .lbl{font-size:.72rem;color:#64748b;margin-top:2px;}

        /* MOVIMIENTOS */
        .card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .card h3{font-size:.92rem;font-weight:700;color:#0f172a;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9;}
        table{width:100%;border-collapse:collapse;font-size:.83rem;}
        thead th{padding:8px 10px;text-align:left;background:#f8fafc;color:#64748b;font-weight:700;font-size:.71rem;text-transform:uppercase;border-bottom:1px solid #e2e8f0;}
        tbody td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tbody tr:last-child td{border-bottom:none;}
        .badge{padding:3px 9px;border-radius:20px;font-size:.71rem;font-weight:600;display:inline-block;}
        .b-dep{background:#d1fae5;color:#065f46;}
        .b-ret{background:#fee2e2;color:#991b1b;}
        .b-int{background:#dbeafe;color:#1e40af;}
        .b-ape{background:#f3f4f6;color:#374151;}

        /* SIN CUENTA */
        .sin-cuenta{text-align:center;padding:60px 20px;}
        .sin-cuenta svg{width:80px;height:80px;color:#cbd5e1;margin:0 auto 20px;display:block;}
        .sin-cuenta h2{font-size:1.2rem;font-weight:700;color:#0f172a;margin-bottom:8px;}
        .sin-cuenta p{font-size:.88rem;color:#64748b;line-height:1.6;margin-bottom:28px;}
        .btn-abrir{background:linear-gradient(135deg,#0a2463,#1d4ed8);color:#fff;border:none;border-radius:10px;padding:14px 32px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s;}
        .btn-abrir:hover{opacity:.9;}

        /* NOTA */
        .nota{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:14px 16px;font-size:.83rem;color:#1e40af;line-height:1.6;margin-bottom:20px;}
        .nota strong{font-weight:700;}

        .empty{text-align:center;padding:24px;color:#94a3b8;font-size:.84rem;}

        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}.contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .uchip{display:none;}
            .btn-salir-movil{display:block !important;}
            .info-grid{grid-template-columns:1fr 1fr;}
            .tarjeta-numero{font-size:1rem;}
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
        <a href="mis_ahorros.php" class="activo"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>Mis Ahorros</a>
        <a href="mis_pagos.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Mis Pagos</a>
        <a href="mi_perfil.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>Mi Perfil</a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Cerrar Sesión</a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>Menú</button>
        <h1>Mis Ahorros</h1>
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
        <div class="uchip"><?= avatar($nombre, $_SESSION['foto']??null, 26) ?><span><?= htmlspecialchars($nombre) ?></span></div>
        <a href="../../controllers/AuthController.php?accion=logout" class="btn-salir-movil">Salir</a>
    </div>
</header>

<main class="contenido">
    <?php mostrarMensaje(); ?>

    <?php if (!$cuenta): ?>
    <!-- SIN CUENTA -->
    <div class="card">
        <div class="sin-cuenta">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            <h2>Aún no tienes cuenta de ahorros</h2>
            <p>Abre tu cuenta de ahorros CREDISOL gratis.<br>
               Tasa de interés anual: <strong>3.50%</strong><br>
               Sin costo de mantenimiento.</p>
            <form method="POST">
                <input type="hidden" name="accion" value="abrir_cuenta">
                <button type="submit" class="btn-abrir">
                    Abrir mi cuenta de ahorros
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>

    <!-- TARJETA BANCARIA -->
    <div class="tarjeta">
        <div class="tarjeta-header">
            <div>
                <div class="tarjeta-banco">CREDISOL</div>
                <div style="font-size:.65rem;opacity:.5;margin-top:2px;">Cooperativa de Ahorro y Crédito</div>
            </div>
            <div class="tarjeta-chip">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <rect x="2" y="6" width="20" height="12" rx="2" fill="rgba(0,0,0,.2)"/>
                    <rect x="7" y="6" width="1" height="12" fill="rgba(0,0,0,.15)"/>
                    <rect x="11" y="6" width="1" height="12" fill="rgba(0,0,0,.15)"/>
                    <rect x="15" y="6" width="1" height="12" fill="rgba(0,0,0,.15)"/>
                    <rect x="2" y="10" width="20" height="4" fill="rgba(0,0,0,.1)"/>
                </svg>
            </div>
        </div>

        <div class="tarjeta-numero">
            <?= implode(' ', str_split(str_replace('-', '', $cuenta['numero_cuenta']), 4)) ?>
        </div>

        <div class="tarjeta-footer">
            <div class="tarjeta-titular">
                <div class="lbl">Titular</div>
                <div class="val"><?= htmlspecialchars(strtoupper(substr($nombre,0,12).' '.substr($apellido,0,10))) ?></div>
                <div style="font-size:.65rem;opacity:.5;margin-top:3px;">Desde <?= date('m/Y', strtotime($cuenta['fecha_apertura'])) ?></div>
            </div>
            <div class="tarjeta-saldo">
                <div class="lbl">Saldo disponible</div>
                <div class="val"><?= soles($cuenta['saldo']) ?></div>
                <div class="tarjeta-tasa">Tasa: <?= $cuenta['tasa_interes'] ?>% anual</div>
            </div>
        </div>
    </div>

    <!-- NÚMERO DE CUENTA VISIBLE -->
    <div class="nota">
        <strong>N° de cuenta:</strong> <?= $cuenta['numero_cuenta'] ?><br>
        Presenta este número en oficina para realizar depósitos o retiros. El administrador registrará los movimientos en el sistema.
    </div>

    <!-- INFO GRID -->
    <div class="info-grid">
        <div class="info-card">
            <div class="ico" style="background:#d1fae5;">
                <svg fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div class="val"><?= count(array_filter($movimientos, fn($m) => $m['tipo']=='deposito')) ?></div>
            <div class="lbl">Depósitos</div>
        </div>
        <div class="info-card">
            <div class="ico" style="background:#fee2e2;">
                <svg fill="none" viewBox="0 0 24 24" stroke="#dc2626" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
            </div>
            <div class="val"><?= count(array_filter($movimientos, fn($m) => $m['tipo']=='retiro')) ?></div>
            <div class="lbl">Retiros</div>
        </div>
        <div class="info-card">
            <div class="ico" style="background:#dbeafe;">
                <svg fill="none" viewBox="0 0 24 24" stroke="#1d4ed8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div class="val"><?= count($movimientos) ?></div>
            <div class="lbl">Movimientos</div>
        </div>
    </div>

    <!-- MOVIMIENTOS -->
    <div class="card">
        <h3>Últimos movimientos</h3>
        <?php if (empty($movimientos)): ?>
        <div class="empty">No hay movimientos aún. Acércate a oficina para hacer tu primer depósito.</div>
        <?php else:
            $bc=['deposito'=>'b-dep','retiro'=>'b-ret','interes'=>'b-int','apertura'=>'b-ape'];
            $bt=['deposito'=>'Depósito','retiro'=>'Retiro','interes'=>'Interés','apertura'=>'Apertura'];
        ?>
        <table>
            <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Saldo anterior</th><th>Saldo nuevo</th></tr>
            </thead>
            <tbody>
            <?php foreach ($movimientos as $m):
                $color = $m['tipo']=='retiro' ? '#dc2626' : '#059669';
                $signo = $m['tipo']=='retiro' ? '- ' : '+ ';
                if($m['tipo']=='apertura'){$signo='';$color='#64748b';}
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
        <?php endif; ?>
    </div>

    <?php endif; ?>
</main>

<script>
function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
</script>
</body>
</html>