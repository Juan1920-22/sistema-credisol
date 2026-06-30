<?php
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1]);

$nombre   = $_SESSION['nombres'];
$apellido = $_SESSION['apellidos'];
$base     = getBase();

// Cargar foto de perfil desde BD si no está en sesión
if (empty($_SESSION['foto'])) {
    $u = $conn->query("SELECT foto FROM usuarios WHERE id=$id")->fetch_assoc();
    if ($u && $u['foto']) $_SESSION['foto'] = $u['foto'];
}

$tipos = $conn->query("SELECT * FROM tipos_prestamo WHERE activo = 1")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Solicitar Préstamo</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f1f5f9;color:#1e293b;}
        /* SIDEBAR */
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
        /* TOPBAR */
        .topbar{position:fixed;top:0;left:250px;right:0;height:62px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .topbar h1{font-size:1.1rem;font-weight:700;color:#0f172a;}
        .uchip{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:5px 10px;}
        .uchip .av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;}
        .uchip span{font-size:.83rem;font-weight:600;color:#0f172a;}
        /* CONTENIDO */
        .contenido{margin-left:250px;margin-top:62px;padding:24px;}
        /* PASOS */
        .pasos-nav{display:flex;margin-bottom:28px;border-radius:10px;overflow:hidden;border:1.5px solid #e2e8f0;}
        .paso-tab{flex:1;padding:13px 8px;text-align:center;font-size:.78rem;font-weight:600;color:#94a3b8;background:#f8fafc;border-right:1.5px solid #e2e8f0;cursor:default;}
        .paso-tab:last-child{border-right:none;}
        .paso-tab.activo{background:#1d4ed8;color:#fff;}
        .paso-tab.completado{background:#eff6ff;color:#1d4ed8;}
        .paso-num{display:block;font-size:1rem;font-weight:800;margin-bottom:1px;}
        .seccion-form{display:none;}
        .seccion-form.visible{display:block;}
        /* TIPO CARD */
        .tipo-card{border:2px solid #e2e8f0;border-radius:10px;padding:16px;cursor:pointer;transition:border .2s,background .2s;margin-bottom:10px;}
        .tipo-card:hover{border-color:#1d4ed8;background:#eff6ff;}
        .tipo-card.seleccionado{border-color:#1d4ed8;background:#eff6ff;}
        .tipo-card h4{font-size:.95rem;color:#0f172a;margin-bottom:3px;font-weight:700;}
        .tipo-card p{font-size:.8rem;color:#64748b;margin-bottom:8px;}
        .tipo-meta{display:flex;gap:8px;flex-wrap:wrap;}
        .tipo-meta span{font-size:.74rem;font-weight:600;background:#dbeafe;color:#1e40af;padding:2px 9px;border-radius:20px;}
        /* SIMULADOR */
        .sim-box{background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;padding:18px;margin-top:18px;}
        .sim-box h4{color:#0369a1;font-size:.88rem;margin-bottom:12px;font-weight:700;}
        .sim-res{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;}
        .sim-item{text-align:center;}
        .sim-item .val{font-size:1.2rem;font-weight:800;color:#0f172a;}
        .sim-item .lbl{font-size:.72rem;color:#64748b;margin-top:2px;}
        /* FORMULARIO */
        .card{background:#fff;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .form-grupo{margin-bottom:16px;}
        .form-grupo label{display:block;font-weight:600;color:#374151;font-size:.85rem;margin-bottom:6px;}
        .form-grupo input,.form-grupo select,.form-grupo textarea{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.92rem;background:#f9fafb;outline:none;color:#111827;transition:border .2s;}
        .form-grupo input:focus,.form-grupo select:focus,.form-grupo textarea:focus{border-color:#1d4ed8;background:#fff;}
        .form-grupo textarea{resize:vertical;min-height:100px;}
        .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        /* RESUMEN */
        .res-item{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid #f1f5f9;font-size:.9rem;}
        .res-item:last-child{border-bottom:none;}
        .res-item span:first-child{color:#64748b;}
        .res-item span:last-child{font-weight:600;color:#0f172a;}
        /* BOTONES */
        .botones-nav{display:flex;justify-content:space-between;margin-top:24px;gap:12px;}
        .btn{display:inline-block;padding:10px 22px;border-radius:8px;border:none;cursor:pointer;font-size:.92rem;font-weight:700;transition:opacity .2s;}
        .btn-az{background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;}
        .btn-gr{background:#6b7280;color:#fff;}
        .btn-ve{background:#059669;color:#fff;}
        .btn:hover{opacity:.9;}
        .alerta{background:#fefce8;border:1.5px solid #fde047;border-radius:8px;padding:13px;margin-top:14px;font-size:.84rem;color:#713f12;}
        /* MENU BTN */
        .menu-btn{display:none;background:#1d4ed8;border:none;cursor:pointer;color:#fff;padding:8px 14px;border-radius:8px;font-size:.8rem;font-weight:700;align-items:center;gap:6px;}
        .menu-btn svg{width:20px;height:20px;}
        .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99;}
        .overlay.show{display:block;}
        /* RESPONSIVE */
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .3s;}
            .sidebar.open{transform:translateX(0);}
            .topbar{left:0;}
            .contenido{margin-left:0;padding:16px;}
            .menu-btn{display:flex !important;}
            .grid2{grid-template-columns:1fr;}
            .sim-res{grid-template-columns:1fr 1fr;}
            .pasos-nav{font-size:.72rem;}
            .paso-num{font-size:.9rem;}
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

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <div><h2>CREDISOL</h2><span>Cooperativa de Ahorro y Crédito</span></div>
    </div>
    <div class="sb-user">
        <?= avatar($nombre, $_SESSION['foto']??null, 38) ?>
        <div>
            <p><?= htmlspecialchars($nombre.' '.$apellido) ?></p>
            <span>Cliente</span>
        </div>
    </div>
    <div class="sb-menu">
        <div class="menu-lbl">Menu Principal</div>
        <a href="dashboard.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Inicio
        </a>
        <a href="solicitar.php" class="activo">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Solicitar Préstamo
        </a>
        <a href="mis_solicitudes.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Mis Solicitudes
        </a>
        <a href="mis_pagos.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Mis Pagos
        </a>
        <a href="mi_perfil.php">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Mi Perfil
        </a>
    </div>
    <div class="sb-footer">
        <a href="../../controllers/AuthController.php?accion=logout">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Cerrar Sesión
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="menu-btn" onclick="abrirMenu()">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Menú
        </button>
        <h1>Solicitar Préstamo</h1>
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

<!-- CONTENIDO -->
<main class="contenido">
    <?php mostrarMensaje(); ?>
    <div class="card">
        <!-- PASOS -->
        <div class="pasos-nav">
            <div class="paso-tab activo" id="tab1"><span class="paso-num">1</span>Tipo de Préstamo</div>
            <div class="paso-tab" id="tab2"><span class="paso-num">2</span>Monto y Plazo</div>
            <div class="paso-tab" id="tab3"><span class="paso-num">3</span>Motivo</div>
            <div class="paso-tab" id="tab4"><span class="paso-num">4</span>Confirmación</div>
        </div>

        <form action="../../controllers/SolicitudController.php" method="POST" id="formSol">
            <input type="hidden" name="accion" value="nueva_solicitud">
            <input type="hidden" name="tipo_prestamo_id" id="tipo_id">
            <input type="hidden" name="tasa_interes"     id="tasa_h">
            <input type="hidden" name="monto_min"        id="mmin_h">
            <input type="hidden" name="monto_max"        id="mmax_h">

            <!-- PASO 1 -->
            <div class="seccion-form visible" id="paso1">
                <h3 style="margin-bottom:16px;color:#0f172a;font-size:1rem;">Selecciona el tipo de préstamo</h3>
                <?php foreach($tipos as $t): ?>
                <div class="tipo-card" id="tipo_<?= $t['id'] ?>"
                     onclick="selTipo(<?= $t['id'] ?>,'<?= addslashes($t['nombre']) ?>',<?= $t['tasa_interes'] ?>,<?= $t['monto_min'] ?>,<?= $t['monto_max'] ?>,<?= $t['plazo_min'] ?>,<?= $t['plazo_max'] ?>)">
                    <h4><?= htmlspecialchars($t['nombre']) ?></h4>
                    <p><?= htmlspecialchars($t['descripcion']) ?></p>
                    <div class="tipo-meta">
                        <span>Tasa: <?= $t['tasa_interes'] ?>% anual</span>
                        <span>S/ <?= number_format($t['monto_min'],0) ?> — S/ <?= number_format($t['monto_max'],0) ?></span>
                        <span><?= $t['plazo_min'] ?> — <?= $t['plazo_max'] ?> meses</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="botones-nav"><span></span><button type="button" class="btn btn-az" onclick="irPaso(2)">Siguiente</button></div>
            </div>

            <!-- PASO 2 -->
            <div class="seccion-form" id="paso2">
                <h3 style="margin-bottom:18px;color:#0f172a;font-size:1rem;">Monto y plazo del préstamo</h3>
                <div class="grid2">
                    <div class="form-grupo">
                        <label>Monto solicitado (S/) *</label>
                        <input type="number" name="monto_solicitado" id="monto_in" placeholder="Ej: 5000" min="0" step="0.01" required oninput="calcSim()">
                    </div>
                    <div class="form-grupo">
                        <label>Plazo en meses *</label>
                        <select name="plazo_meses" id="plazo_sel" required onchange="calcSim()">
                            <option value="">Selecciona el plazo</option>
                        </select>
                    </div>
                </div>
                <div class="sim-box">
                    <h4>Simulador de cuota mensual</h4>
                    <div class="sim-res">
                        <div class="sim-item"><div class="val" id="sim-cuota">S/ 0.00</div><div class="lbl">Cuota mensual</div></div>
                        <div class="sim-item"><div class="val" id="sim-total">S/ 0.00</div><div class="lbl">Total a pagar</div></div>
                        <div class="sim-item"><div class="val" id="sim-tasa">0%</div><div class="lbl">Tasa anual</div></div>
                    </div>
                </div>
                <div class="botones-nav">
                    <button type="button" class="btn btn-gr" onclick="irPaso(1)">Anterior</button>
                    <button type="button" class="btn btn-az" onclick="irPaso(3)">Siguiente</button>
                </div>
            </div>

            <!-- PASO 3 -->
            <div class="seccion-form" id="paso3">
                <h3 style="margin-bottom:18px;color:#0f172a;font-size:1rem;">Motivo del préstamo</h3>
                <div class="form-grupo">
                    <label>¿Para qué utilizarás el préstamo? *</label>
                    <textarea name="motivo" id="motivo_in" placeholder="Describe el motivo..." required minlength="20"></textarea>
                    <small style="color:#94a3b8;font-size:.76rem;">Mínimo 20 caracteres. Esto ayuda al asesor a evaluar tu solicitud.</small>
                </div>
                <div class="botones-nav">
                    <button type="button" class="btn btn-gr" onclick="irPaso(2)">Anterior</button>
                    <button type="button" class="btn btn-az" onclick="irPaso(4)">Siguiente</button>
                </div>
            </div>

            <!-- PASO 4 -->
            <div class="seccion-form" id="paso4">
                <h3 style="margin-bottom:18px;color:#0f172a;font-size:1rem;">Confirma tu solicitud</h3>
                <div style="background:#f8fafc;border-radius:10px;padding:16px;border:1.5px solid #e2e8f0;">
                    <div class="res-item"><span>Tipo de préstamo</span><span id="r-tipo">—</span></div>
                    <div class="res-item"><span>Monto solicitado</span><span id="r-monto">—</span></div>
                    <div class="res-item"><span>Plazo</span><span id="r-plazo">—</span></div>
                    <div class="res-item"><span>Cuota mensual estimada</span><span id="r-cuota" style="color:#1d4ed8;font-size:1.05rem;">—</span></div>
                    <div class="res-item"><span>Total a pagar</span><span id="r-total">—</span></div>
                    <div class="res-item"><span>Motivo</span><span id="r-motivo" style="max-width:200px;text-align:right;">—</span></div>
                </div>
                <div class="alerta">Al enviar esta solicitud confirmas que la información es verídica. Un asesor revisará tu solicitud y te notificará el resultado.</div>
                <div class="botones-nav">
                    <button type="button" class="btn btn-gr" onclick="irPaso(3)">Anterior</button>
                    <button type="submit" class="btn btn-ve" style="padding:12px 28px;">Enviar Solicitud</button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
var sel={id:0,nombre:'',tasa:0,mmin:0,mmax:0,pmin:0,pmax:0};

function abrirMenu(){document.getElementById('sidebar').classList.add('open');document.getElementById('overlay').classList.add('show');}
function cerrarMenu(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}

function selTipo(id,nombre,tasa,mmin,mmax,pmin,pmax){
    document.querySelectorAll('.tipo-card').forEach(c=>c.classList.remove('seleccionado'));
    document.getElementById('tipo_'+id).classList.add('seleccionado');
    sel={id,nombre,tasa,mmin,mmax,pmin,pmax};
    document.getElementById('tipo_id').value=id;
    document.getElementById('tasa_h').value=tasa;
    document.getElementById('mmin_h').value=mmin;
    document.getElementById('mmax_h').value=mmax;
    var mi=document.getElementById('monto_in');
    mi.min=mmin; mi.max=mmax;
    mi.placeholder='Entre S/ '+mmin.toLocaleString()+' y S/ '+mmax.toLocaleString();
    var ps=document.getElementById('plazo_sel');
    ps.innerHTML='<option value="">Selecciona el plazo</option>';
    var step=pmax<=24?3:6;
    for(var m=pmin;m<=pmax;m+=step){
        var o=document.createElement('option');
        o.value=m; o.textContent=m+' meses'; ps.appendChild(o);
    }
    document.getElementById('sim-tasa').textContent=tasa+'%';
    calcSim();
}

function calcSim(){
    var m=parseFloat(document.getElementById('monto_in').value)||0;
    var p=parseInt(document.getElementById('plazo_sel').value)||0;
    var t=sel.tasa;
    if(m>0&&p>0&&t>0){
        var tm=(t/100)/12;
        var c=m*(tm*Math.pow(1+tm,p))/(Math.pow(1+tm,p)-1);
        var tot=c*p;
        document.getElementById('sim-cuota').textContent='S/ '+c.toFixed(2);
        document.getElementById('sim-total').textContent='S/ '+tot.toFixed(2);
    }else{
        document.getElementById('sim-cuota').textContent='S/ 0.00';
        document.getElementById('sim-total').textContent='S/ 0.00';
    }
}

function irPaso(n){
    if(n===2&&!sel.id){alert('Por favor selecciona un tipo de préstamo.');return;}
    if(n===3){
        var m=parseFloat(document.getElementById('monto_in').value);
        var p=document.getElementById('plazo_sel').value;
        if(!m||m<sel.mmin||m>sel.mmax){alert('El monto debe estar entre S/ '+sel.mmin+' y S/ '+sel.mmax);return;}
        if(!p){alert('Por favor selecciona el plazo.');return;}
    }
    if(n===4){
        var motivo=document.getElementById('motivo_in').value.trim();
        if(motivo.length<20){alert('El motivo debe tener al menos 20 caracteres.');return;}
        var m=parseFloat(document.getElementById('monto_in').value);
        var p=parseInt(document.getElementById('plazo_sel').value);
        var t=sel.tasa; var tm=(t/100)/12;
        var c=m*(tm*Math.pow(1+tm,p))/(Math.pow(1+tm,p)-1);
        var tot=c*p;
        document.getElementById('r-tipo').textContent=sel.nombre;
        document.getElementById('r-monto').textContent='S/ '+m.toLocaleString('es-PE',{minimumFractionDigits:2});
        document.getElementById('r-plazo').textContent=p+' meses';
        document.getElementById('r-cuota').textContent='S/ '+c.toFixed(2);
        document.getElementById('r-total').textContent='S/ '+tot.toFixed(2);
        document.getElementById('r-motivo').textContent=motivo;
    }
    for(var i=1;i<=4;i++){
        document.getElementById('paso'+i).classList.remove('visible');
        var tab=document.getElementById('tab'+i);
        tab.classList.remove('activo','completado');
        if(i<n) tab.classList.add('completado');
        else if(i==n) tab.classList.add('activo');
    }
    document.getElementById('paso'+n).classList.add('visible');
    window.scrollTo(0,0);
}
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