<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1]);

$id  = $_SESSION['usuario_id'];
$cid = intval($_GET['cartera'] ?? 0);

$cartera = $conn->query(
    "SELECT cp.*, s.codigo, s.monto_solicitado, s.plazo_meses, s.cuota_estimada,
     tp.nombre AS tipo, tp.tasa_interes,
     CONCAT(u.nombres,' ',u.apellidos) AS cliente, u.dni
     FROM cartera_prestamos cp
     JOIN solicitudes s ON cp.solicitud_id=s.id
     JOIN tipos_prestamo tp ON s.tipo_prestamo_id=tp.id
     JOIN usuarios u ON cp.cliente_id=u.id
     WHERE cp.id=$cid AND cp.cliente_id=$id"
)->fetch_assoc();

if (!$cartera) { header("Location: mis_pagos.php"); exit; }

$cuotas = $conn->query(
    "SELECT * FROM pagos WHERE cartera_id=$cid ORDER BY numero_cuota ASC"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cronograma <?= $cartera['codigo'] ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#fff;color:#0f172a;padding:40px;max-width:750px;margin:0 auto;}
        .header{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #1d4ed8;padding-bottom:16px;margin-bottom:24px;}
        .logo-area h1{font-size:1.6rem;font-weight:800;color:#1d4ed8;}
        .logo-area p{font-size:.78rem;color:#64748b;}
        .titulo{font-size:.85rem;color:#64748b;text-align:right;}
        .titulo strong{display:block;font-size:1.1rem;color:#0f172a;}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:24px;background:#f8fafc;border-radius:8px;padding:16px;}
        .info-item .l{font-size:.7rem;color:#64748b;font-weight:600;text-transform:uppercase;}
        .info-item .v{font-size:.9rem;font-weight:700;color:#0f172a;margin-top:2px;}
        table{width:100%;border-collapse:collapse;font-size:.84rem;}
        thead th{padding:10px 12px;text-align:left;background:#1d4ed8;color:#fff;font-weight:700;font-size:.75rem;text-transform:uppercase;}
        tbody td{padding:9px 12px;border-bottom:1px solid #f1f5f9;}
        tbody tr:nth-child(even){background:#f8fafc;}
        tbody tr.pagada{background:#f0fdf4;}
        tbody tr.vencida{background:#fff5f5;}
        .badge{padding:3px 8px;border-radius:20px;font-size:.7rem;font-weight:600;}
        .b-pend{background:#fef3c7;color:#92400e;}
        .b-pag{background:#d1fae5;color:#065f46;}
        .b-venc{background:#fee2e2;color:#991b1b;}
        .resumen{display:flex;gap:16px;margin-bottom:24px;}
        .res-item{flex:1;background:#f8fafc;border-radius:8px;padding:14px;text-align:center;}
        .res-item .n{font-size:1.3rem;font-weight:800;color:#0f172a;}
        .res-item .l{font-size:.72rem;color:#64748b;margin-top:2px;}
        .nota{background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:14px;font-size:.82rem;color:#713f12;margin-top:20px;line-height:1.6;}
        .footer{margin-top:24px;padding-top:14px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;font-size:.75rem;color:#94a3b8;}
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
        </div>
        <div class="titulo">
            <p>Cronograma de Pagos</p>
            <strong><?= $cartera['codigo'] ?></strong>
            <p style="margin-top:4px;"><?= fechaCorta($cartera['fecha_inicio']) ?></p>
        </div>
    </div>

    <!-- INFO PRÉSTAMO -->
    <div class="info-grid">
        <div class="info-item"><div class="l">Titular</div><div class="v"><?= htmlspecialchars($cartera['cliente']) ?></div></div>
        <div class="info-item"><div class="l">DNI</div><div class="v"><?= $cartera['dni'] ?></div></div>
        <div class="info-item"><div class="l">Tipo de Préstamo</div><div class="v"><?= $cartera['tipo'] ?></div></div>
        <div class="info-item"><div class="l">Tasa de Interés</div><div class="v"><?= $cartera['tasa_interes'] ?>% anual</div></div>
        <div class="info-item"><div class="l">Monto Total</div><div class="v"><?= soles($cartera['monto_total']) ?></div></div>
        <div class="info-item"><div class="l">Plazo</div><div class="v"><?= $cartera['plazo_meses'] ?> meses</div></div>
        <div class="info-item"><div class="l">Fecha inicio</div><div class="v"><?= fechaCorta($cartera['fecha_inicio']) ?></div></div>
        <div class="info-item"><div class="l">Fecha fin</div><div class="v"><?= fechaCorta($cartera['fecha_fin']) ?></div></div>
    </div>

    <!-- RESUMEN -->
    <?php
    $pagadas  = count(array_filter($cuotas, fn($c) => $c['estado']=='pagado'));
    $pct      = $cartera['cuotas_total']>0 ? round($pagadas/$cartera['cuotas_total']*100) : 0;
    ?>
    <div class="resumen">
        <div class="res-item"><div class="n"><?= soles($cartera['monto_total']) ?></div><div class="l">Monto total</div></div>
        <div class="res-item"><div class="n" style="color:#dc2626;"><?= soles($cartera['saldo_pendiente']) ?></div><div class="l">Saldo pendiente</div></div>
        <div class="res-item"><div class="n"><?= $pagadas ?>/<?= $cartera['cuotas_total'] ?></div><div class="l">Cuotas pagadas</div></div>
        <div class="res-item"><div class="n"><?= $pct ?>%</div><div class="l">Completado</div></div>
    </div>

    <!-- TABLA DE CUOTAS -->
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha Vencimiento</th>
                <th>Monto Cuota</th>
                <th>Fecha Pago</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $hoy = date('Y-m-d');
        $bc  = ['pendiente'=>'b-pend','pagado'=>'b-pag','vencido'=>'b-venc'];
        $bt  = ['pendiente'=>'Pendiente','pagado'=>'Pagado','vencido'=>'Vencido'];
        foreach ($cuotas as $c):
            $estado = $c['estado'];
            if ($estado=='pendiente' && $c['fecha_vencimiento']<$hoy) $estado='vencido';
            $rowClass = $estado=='pagado'?'pagada':($estado=='vencido'?'vencida':'');
        ?>
        <tr class="<?= $rowClass ?>">
            <td style="font-weight:700;">#<?= $c['numero_cuota'] ?></td>
            <td><?= fechaCorta($c['fecha_vencimiento']) ?></td>
            <td style="font-weight:600;"><?= soles($c['monto_cuota']) ?></td>
            <td><?= $c['fecha_pago'] ? fechaCorta($c['fecha_pago']) : '—' ?></td>
            <td><span class="badge <?= $bc[$estado] ?>"><?= $bt[$estado] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="nota">
        <strong>Recuerda:</strong> Acércate a nuestras oficinas CREDISOL para realizar tus pagos. También puedes consultar tu estado de cuenta en la plataforma web.
    </div>

    <div class="footer">
        <span>CREDISOL — Cooperativa de Ahorro y Crédito</span>
        <span>Generado el <?= date('d/m/Y H:i') ?></span>
    </div>
</body>
</html>