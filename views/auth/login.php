<?php
session_start();
require_once "../../helpers/funciones.php";
$base = getBase();
if (isset($_SESSION['usuario_id'])) {
    $rol = $_SESSION['rol_id'];
    if ($rol == 1)      header("Location: " . $base . "/views/cliente/dashboard.php");
    elseif ($rol == 2)  header("Location: " . $base . "/views/asesor/dashboard.php");
    elseif ($rol == 3)  header("Location: " . $base . "/views/admin/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CREDISOL — Iniciar Sesión</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#eef2f7;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
        .card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.13);display:flex;overflow:hidden;width:100%;max-width:860px;}
        .left{background:linear-gradient(170deg,#0a2463,#1d4ed8);width:340px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 32px;text-align:center;color:#fff;position:relative;overflow:hidden;}
        .left::before{content:'';position:absolute;width:280px;height:280px;background:rgba(255,255,255,0.05);border-radius:50%;top:-80px;right:-80px;}
        .left::after{content:'';position:absolute;width:180px;height:180px;background:rgba(255,255,255,0.05);border-radius:50%;bottom:-50px;left:-50px;}
        .left img{width:120px;margin-bottom:18px;position:relative;z-index:1;background:#fff;border-radius:12px;padding:8px;}
        .left h1{font-size:1.5rem;font-weight:800;letter-spacing:.04em;position:relative;z-index:1;}
        .left p{font-size:.82rem;opacity:.75;margin-top:6px;line-height:1.5;position:relative;z-index:1;}
        .left .slogan{margin-top:20px;font-size:.72rem;opacity:.55;letter-spacing:.1em;text-transform:uppercase;position:relative;z-index:1;}
        .right{flex:1;display:flex;flex-direction:column;justify-content:center;padding:48px 44px;}
        .mob-header{display:none;text-align:center;margin-bottom:28px;}
        .mob-header img{width:80px;border-radius:12px;padding:6px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.1);margin-bottom:10px;}
        .mob-header h2{font-size:1.3rem;font-weight:800;color:#0a2463;}
        .mob-header span{font-size:.78rem;color:#64748b;}
        .right h2{font-size:1.6rem;font-weight:800;color:#0f172a;margin-bottom:4px;}
        .right .sub{color:#64748b;font-size:.88rem;margin-bottom:28px;}
        .campo{margin-bottom:18px;}
        .campo label{display:block;font-weight:600;color:#374151;font-size:.85rem;margin-bottom:6px;}
        .campo-input{position:relative;}
        .campo-input input{width:100%;padding:12px 44px 12px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:.95rem;background:#f9fafb;outline:none;color:#111827;transition:border .2s,box-shadow .2s;}
        .campo-input input:focus{border-color:#1d4ed8;background:#fff;box-shadow:0 0 0 3px rgba(29,78,216,.08);}
        .btn-ojo{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;color:#9ca3af;display:flex;align-items:center;}
        .btn-ojo svg{width:18px;height:18px;}
        .robot-row{display:flex;align-items:center;gap:10px;background:#f9fafb;border:1.5px solid #d1d5db;border-radius:8px;padding:11px 14px;margin-bottom:22px;}
        .robot-row input[type="checkbox"]{width:18px;height:18px;accent-color:#1d4ed8;cursor:pointer;}
        .robot-row label{font-size:.88rem;color:#374151;font-weight:500;cursor:pointer;}
        .btn-submit{width:100%;padding:13px;background:linear-gradient(135deg,#1e40af,#1d4ed8);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s,transform .15s;}
        .btn-submit:hover{opacity:.92;transform:translateY(-1px);}
        .reg-link{text-align:center;margin-top:20px;font-size:.87rem;color:#6b7280;}
        .reg-link a{color:#1d4ed8;font-weight:700;text-decoration:none;}
        .seguro{text-align:center;margin-top:14px;font-size:.76rem;color:#9ca3af;}
        @media(max-width:640px){
            body{padding:16px;background:#1d4ed8;}
            .card{flex-direction:column;max-width:100%;}
            .left{display:none;}
            .right{padding:32px 24px;}
            .mob-header{display:block;}
        }
    </style>
</head>
<body>
<div class="card">
    <div class="left">
        <img src="../../public/img/logo.png" alt="CREDISOL">
        <h1>CREDISOL</h1>
        <p>Cooperativa de Ahorro y Crédito</p>
        <div class="slogan">Tu confianza, nuestro compromiso</div>
    </div>
    <div class="right">
        <div class="mob-header">
            <img src="../../public/img/logo.png" alt="CREDISOL">
            <h2>CREDISOL</h2>
            <span>Cooperativa de Ahorro y Crédito</span>
        </div>
        <h2>Iniciar Sesión</h2>
        <p class="sub">Ingresa tus credenciales para acceder a tu cuenta</p>
        <?php mostrarMensaje(); ?>
        <form action="../../controllers/AuthController.php" method="POST">
            <input type="hidden" name="accion" value="login">
            <div class="campo">
                <label>Correo electrónico</label>
                <div class="campo-input">
                    <input type="email" name="correo" placeholder="tucorreo@email.com" required>
                </div>
            </div>
            <div class="campo">
                <label>Contraseña</label>
                <div class="campo-input">
                    <input type="password" name="contrasena" id="pass" placeholder="Ingresa tu contraseña" required>
                    <button type="button" class="btn-ojo"
                        onmousedown="mostrarPass()" onmouseup="ocultarPass()"
                        onmouseleave="ocultarPass()" ontouchstart="mostrarPass()" ontouchend="ocultarPass()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="robot-row">
                <input type="checkbox" id="robot" required>
                <label for="robot">No soy un robot</label>
            </div>
            <button type="submit" class="btn-submit">Iniciar Sesión</button>
        </form>
        <p class="reg-link">¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
        <p class="seguro">Tu información está protegida y cifrada</p>
                    <div style="text-align:center;margin-top:16px;">
                        <a href="../../index.php" style="font-size:.8rem;color:#64748b;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:color .2s;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Volver al inicio
                        </a>
                    </div>
    </div>
</div>
<script>
function mostrarPass(){ document.getElementById('pass').type='text'; }
function ocultarPass(){ document.getElementById('pass').type='password'; }
</script>
</body>
</html>