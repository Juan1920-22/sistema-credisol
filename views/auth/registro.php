<?php
session_start();
require_once "../../helpers/funciones.php";
$base = getBase();
if (isset($_SESSION['usuario_id'])) {
    header("Location: " . $base . "/views/auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Crear Cuenta</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#eef2f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.13);display:flex;overflow:hidden;width:100%;max-width:860px;}
        .left{background:linear-gradient(170deg,#0a2463,#1d4ed8);width:300px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 28px;text-align:center;color:#fff;position:relative;overflow:hidden;}
        .left::before{content:'';position:absolute;width:240px;height:240px;background:rgba(255,255,255,0.05);border-radius:50%;top:-70px;right:-70px;}
        .left img{width:110px;margin-bottom:16px;position:relative;z-index:1;background:#fff;border-radius:12px;padding:8px;}
        .left h1{font-size:1.4rem;font-weight:800;letter-spacing:.04em;position:relative;z-index:1;}
        .left p{font-size:.8rem;opacity:.75;margin-top:6px;line-height:1.5;position:relative;z-index:1;}
        .left ul{list-style:none;margin-top:24px;text-align:left;font-size:.8rem;position:relative;z-index:1;width:100%;}
        .left ul li{padding:6px 0;opacity:.88;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:8px;}
        .left ul li:last-child{border-bottom:none;}
        .dot{width:10px;height:10px;background:#22c55e;border-radius:50%;flex-shrink:0;}
        .left .slogan{margin-top:20px;font-size:.7rem;opacity:.5;letter-spacing:.1em;text-transform:uppercase;position:relative;z-index:1;}
        .right{flex:1;padding:40px 44px;overflow-y:auto;}
        .right h2{font-size:1.5rem;font-weight:800;color:#0f172a;margin-bottom:4px;}
        .right .sub{color:#64748b;font-size:.87rem;margin-bottom:24px;}
        .seccion{font-size:.75rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.08em;border-bottom:2px solid #dbeafe;padding-bottom:6px;margin:20px 0 14px;}
        .seccion:first-of-type{margin-top:0;}
        .fila2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
        .campo{margin-bottom:0;}
        .campo label{display:block;font-weight:600;color:#374151;font-size:.83rem;margin-bottom:6px;}
        .campo-input{position:relative;}
        .campo-input input{width:100%;padding:11px 42px 11px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.92rem;background:#f9fafb;outline:none;color:#111827;transition:border .2s,box-shadow .2s;}
        .campo-input input:focus{border-color:#1d4ed8;background:#fff;box-shadow:0 0 0 3px rgba(29,78,216,.08);}
        .btn-ojo{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#9ca3af;display:flex;align-items:center;}
        .btn-ojo svg{width:17px;height:17px;}
        .robot-row{display:flex;align-items:center;gap:10px;background:#f9fafb;border:1.5px solid #d1d5db;border-radius:8px;padding:11px 14px;margin:20px 0;}
        .robot-row input[type="checkbox"]{width:17px;height:17px;accent-color:#1d4ed8;cursor:pointer;}
        .robot-row label{font-size:.88rem;color:#374151;font-weight:500;cursor:pointer;}
        .botones{display:flex;gap:12px;}
        .btn-crear{flex:1;padding:12px;background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .2s;}
        .btn-crear:hover{opacity:.91;}
        .btn-volver{padding:12px 22px;background:#fff;color:#1d4ed8;border:2px solid #1d4ed8;border-radius:8px;font-size:.9rem;font-weight:600;text-decoration:none;display:flex;align-items:center;transition:background .2s;}
        .btn-volver:hover{background:#eff6ff;}
        .seguro{text-align:center;margin-top:14px;font-size:.75rem;color:#9ca3af;}
        @media(max-width:640px){
            body{padding:16px;background:#1d4ed8;}
            .card{flex-direction:column;max-width:100%;}
            .left{display:none;}
            .right{padding:28px 20px;}
            .fila2{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
<div class="card">
    <div class="left">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <h1>CREDISOL</h1>
        <p>Cooperativa de Ahorro y Crédito</p>
        <ul>
            <li><span class="dot"></span>Solicita préstamos en línea</li>
            <li><span class="dot"></span>Seguimiento en tiempo real</li>
            <li><span class="dot"></span>Proceso rápido y seguro</li>
            <li><span class="dot"></span>Sin costos de afiliación</li>
        </ul>
        <div class="slogan">Tu confianza, nuestro compromiso</div>
    </div>
    <div class="right">
        <h2>Crear cuenta</h2>
        <p class="sub">Completa tus datos para unirte a CREDISOL</p>
        <?php mostrarMensaje(); ?>
        <form action="../../controllers/AuthController.php" method="POST" id="formReg">
            <input type="hidden" name="accion" value="registro">

            <div class="seccion">Datos Personales</div>
            <div class="fila2">
                <div class="campo">
                    <label>Nombres *</label>
                    <div class="campo-input">
                        <input type="text" name="nombres" placeholder="Juan Carlos" required>
                    </div>
                </div>
                <div class="campo">
                    <label>Apellidos *</label>
                    <div class="campo-input">
                        <input type="text" name="apellidos" placeholder="Pérez García" required>
                    </div>
                </div>
            </div>

            <div class="fila2">
                <div class="campo">
                    <label>DNI *</label>
                    <div class="campo-input">
                        <input type="text" name="dni" placeholder="12345678"
                               maxlength="8" pattern="[0-9]{8}"
                               title="El DNI debe tener 8 dígitos numéricos" required>
                    </div>
                </div>
                <div class="campo">
                    <label>Correo electrónico *</label>
                    <div class="campo-input">
                        <input type="email" name="correo" placeholder="tucorreo@email.com" required>
                    </div>
                </div>
            </div>

            <div class="seccion">Contraseña</div>
            <div class="fila2">
                <div class="campo">
                    <label>Contraseña *</label>
                    <div class="campo-input">
                        <input type="password" name="contrasena" id="p1"
                               placeholder="Mínimo 8 caracteres" minlength="8" required>
                        <button type="button" class="btn-ojo"
                            onmousedown="show('p1')" onmouseup="hide('p1')"
                            onmouseleave="hide('p1')" ontouchstart="show('p1')" ontouchend="hide('p1')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="campo">
                    <label>Repetir contraseña *</label>
                    <div class="campo-input">
                        <input type="password" name="confirmar_contrasena" id="p2"
                               placeholder="Repite tu contraseña" required>
                        <button type="button" class="btn-ojo"
                            onmousedown="show('p2')" onmouseup="hide('p2')"
                            onmouseleave="hide('p2')" ontouchstart="show('p2')" ontouchend="hide('p2')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="robot-row">
                <input type="checkbox" id="robot" required>
                <label for="robot">No soy un robot</label>
            </div>

            <div class="botones">
                <button type="submit" class="btn-crear">Crear mi cuenta</button>
                <a href="login.php" class="btn-volver">Volver al Login</a>
            </div>
            <p class="seguro">Tu información está protegida y nunca será compartida con terceros.</p>
                    <div style="text-align:center;margin-top:14px;">
                        <a href="../../index.php" style="font-size:.8rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Volver al inicio
                        </a>
                    </div>
        </form>
    </div>
</div>
<script>
function show(id){ document.getElementById(id).type='text'; }
function hide(id){ document.getElementById(id).type='password'; }
document.getElementById('formReg').addEventListener('submit',function(e){
    var p1=document.getElementById('p1').value;
    var p2=document.getElementById('p2').value;
    if(p1!==p2){ e.preventDefault(); alert('Las contraseñas no coinciden.'); }
    var dni=document.querySelector('[name="dni"]').value;
    if(!/^\d{8}$/.test(dni)){ e.preventDefault(); alert('El DNI debe tener exactamente 8 dígitos numéricos.'); }
});
</script>
</body>
</html>