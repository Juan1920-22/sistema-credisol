<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1]);

$id  = $_SESSION['usuario_id'];
$sid = intval($_GET['id'] ?? 0);

$sol = $conn->query(
    "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
     CONCAT(u.nombres,' ',u.apellidos) AS cliente,
     u.dni, u.correo, u.telefono
     FROM solicitudes s
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     JOIN usuarios u ON s.cliente_id=u.id
     WHERE s.id=$sid AND s.cliente_id=$id"
)->fetch_assoc();

if (!$sol) { header("Location: mis_solicitudes.php"); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante <?= $sol['codigo'] ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#fff;color:#0f172a;padding:40px;max-width:700px;margin:0 auto;}
        .header{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #1d4ed8;padding-bottom:16px;margin-bottom:24px;}
        .logo-area h1{font-size:1.6rem;font-weight:800;color:#1d4ed8;}
        .logo-area p{font-size:.78rem;color:#64748b;}
        .titulo{font-size:.85rem;color:#64748b;text-align:right;}
        .titulo strong{display:block;font-size:1.1rem;color:#0f172a;}
        .seccion{margin-bottom:20px;}
        .seccion h3{font-size:.75rem;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #e2e8f0;padding-bottom:6px;margin-bottom:12px;}
        .fila{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f8fafc;font-size:.87rem;}
        .fila span:first-child{color:#64748b;}
        .fila span:last-child{font-weight:600;}
        .monto-grande{text-align:center;background:#eff6ff;border-radius:10px;padding:20px;margin:20px 0;}
        .monto-grande .num{font-size:2.4rem;font-weight:800;color:#1d4ed8;}
        .monto-grande .lbl{font-size:.8rem;color:#64748b;margin-top:4px;}
        .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;}
        .badge.pendiente{background:#fef3c7;color:#92400e;}
        .badge.en_evaluacion{background:#dbeafe;color:#1e40af;}
        .badge.aprobada{background:#d1fae5;color:#065f46;}
        .badge.desembolsada{background:#a7f3d0;color:#064e3b;}
        .badge.rechazada{background:#fee2e2;color:#991b1b;}
        .nota{background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:14px;font-size:.82rem;color:#713f12;margin-top:20px;line-height:1.6;}
        .footer{margin-top:32px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:.75rem;color:#94a3b8;}
        .btn-imprimir{display:block;width:100%;padding:13px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-bottom:16px;}
        @media print{
            .btn-imprimir{display:none;}
            body{padding:20px;}
        }
    </style>
</head>
<body>
    <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir / Guardar como PDF</button>

    <div class="header">
        <div class="logo-area">
            <h1>CREDISOL</h1>
            <p>Cooperativa de Ahorro y Crédito</p>
            <p>TU CONFIANZA, NUESTRO COMPROMISO</p>
        </div>
        <div class="titulo">
            <p>Comprobante de Solicitud</p>
            <strong><?= $sol['codigo'] ?></strong>
            <p style="margin-top:4px;"><?= fechaCorta($sol['fecha_solicitud']) ?></p>
        </div>
    </div>

    <!-- MONTO -->
    <div class="monto-grande">
        <div class="num"><?= soles($sol['monto_solicitado']) ?></div>
        <div class="lbl">Monto Solicitado — <?= $sol['tipo'] ?></div>
        <div style="margin-top:10px;">
            <span class="badge <?= $sol['estado'] ?>"><?= ucfirst(str_replace('_',' ',$sol['estado'])) ?></span>
        </div>
    </div>

    <!-- DATOS DEL SOLICITANTE -->
    <div class="seccion">
        <h3>Datos del Solicitante</h3>
        <div class="fila"><span>Nombre completo</span><span><?= htmlspecialchars($sol['cliente']) ?></span></div>
        <div class="fila"><span>DNI</span><span><?= $sol['dni'] ?></span></div>
        <div class="fila"><span>Correo</span><span><?= htmlspecialchars($sol['correo']) ?></span></div>
        <div class="fila"><span>Teléfono</span><span><?= $sol['telefono']??'—' ?></span></div>
    </div>

    <!-- DATOS DEL PRÉSTAMO -->
    <div class="seccion">
        <h3>Datos del Préstamo</h3>
        <div class="fila"><span>Código de solicitud</span><span><?= $sol['codigo'] ?></span></div>
        <div class="fila"><span>Tipo de préstamo</span><span><?= $sol['tipo'] ?></span></div>
        <div class="fila"><span>Monto solicitado</span><span><?= soles($sol['monto_solicitado']) ?></span></div>
        <div class="fila"><span>Plazo</span><span><?= $sol['plazo_meses'] ?> meses</span></div>
        <div class="fila"><span>Tasa de interés</span><span><?= $sol['tasa_interes'] ?>% anual</span></div>
        <div class="fila"><span>Cuota mensual estimada</span><span style="color:#1d4ed8;font-weight:700;"><?= soles($sol['cuota_estimada']) ?></span></div>
        <div class="fila"><span>Total a pagar</span><span><?= soles($sol['cuota_estimada'] * $sol['plazo_meses']) ?></span></div>
        <div class="fila"><span>Motivo</span><span><?= htmlspecialchars($sol['motivo']) ?></span></div>
        <div class="fila"><span>Fecha de solicitud</span><span><?= fechaCorta($sol['fecha_solicitud']) ?></span></div>
    </div>

    <div class="nota">
        <strong>Importante:</strong> Este documento es un comprobante de solicitud de préstamo. La aprobación está sujeta a evaluación crediticia por parte del asesor y administración de CREDISOL. Se le notificará el resultado por el sistema.
    </div>

    <div class="footer">
        <span>CREDISOL — Cooperativa de Ahorro y Crédito</span>
        <span>Generado el <?= date('d/m/Y H:i') ?></span>
    </div>
</body>
</html>