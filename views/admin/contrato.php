<?php
ini_set('display_errors', 0);
session_start();
require_once "../../helpers/funciones.php";
require_once "../../config/conexion.php";
requiereRol([1, 2, 3]);

$rol = $_SESSION['rol_id'];
$sid = intval($_GET['id'] ?? 0);

// Admin y asesor pueden ver cualquier contrato aprobado
// Cliente solo puede ver SU propio contrato
if ($rol == 1) {
    $sol = $conn->query(
        "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
         CONCAT(u.nombres,' ',u.apellidos) AS cliente,
         u.dni, u.correo, u.telefono, u.direccion,
         CONCAT(a.nombres,' ',a.apellidos) AS asesor
         FROM solicitudes s
         JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
         JOIN usuarios u ON s.cliente_id = u.id
         LEFT JOIN usuarios a ON s.asesor_id = a.id
         WHERE s.id = $sid AND s.cliente_id = {$_SESSION['usuario_id']}"
    )->fetch_assoc();
} else {
    $sol = $conn->query(
        "SELECT s.*, tp.nombre AS tipo, tp.tasa_interes,
         CONCAT(u.nombres,' ',u.apellidos) AS cliente,
         u.dni, u.correo, u.telefono, u.direccion,
         CONCAT(a.nombres,' ',a.apellidos) AS asesor
         FROM solicitudes s
         JOIN tipos_prestamo tp ON s.tipo_prestamo_id = tp.id
         JOIN usuarios u ON s.cliente_id = u.id
         LEFT JOIN usuarios a ON s.asesor_id = a.id
         WHERE s.id = $sid"
    )->fetch_assoc();
}

if (!$sol) { header("Location: ../../views/auth/login.php"); exit; }

// Calcular cuotas para el cronograma resumido
$tasa_mensual = $sol['tasa_interes'] / 12 / 100;
$plazo        = $sol['plazo_meses'];
$monto        = $sol['monto_solicitado'];
$cuota        = $monto * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo)) / (pow(1 + $tasa_mensual, $plazo) - 1);
$total_pagar  = $cuota * $plazo;
$total_int    = $total_pagar - $monto;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Prestamo — <?= $sol['codigo'] ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Times New Roman',serif;background:#fff;color:#000;padding:30px 40px;max-width:800px;margin:0 auto;font-size:11pt;line-height:1.6;}
        .btn-imprimir{display:block;width:100%;padding:12px;background:#1d4ed8;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;margin-bottom:20px;font-family:'Segoe UI',Arial,sans-serif;}
        .encabezado{text-align:center;margin-bottom:24px;border-bottom:2px solid #000;padding-bottom:16px;}
        .encabezado h1{font-size:14pt;font-weight:700;text-transform:uppercase;letter-spacing:1px;}
        .encabezado h2{font-size:12pt;font-weight:700;margin-top:4px;}
        .encabezado p{font-size:10pt;color:#555;}
        .codigo{text-align:center;margin:12px 0;font-size:10pt;}
        .seccion{margin-bottom:18px;}
        .seccion h3{font-size:11pt;font-weight:700;text-transform:uppercase;border-bottom:1px solid #000;padding-bottom:3px;margin-bottom:10px;}
        .clausula{margin-bottom:12px;text-align:justify;}
        .clausula strong{font-weight:700;}
        .tabla-datos{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:10pt;}
        .tabla-datos td{padding:5px 8px;border:1px solid #ccc;vertical-align:top;}
        .tabla-datos td:first-child{font-weight:700;background:#f5f5f5;width:40%;}
        .tabla-cuotas{width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:12px;}
        .tabla-cuotas th{background:#000;color:#fff;padding:5px 8px;text-align:left;}
        .tabla-cuotas td{padding:4px 8px;border-bottom:1px solid #ddd;}
        .tabla-cuotas tr:nth-child(even){background:#f9f9f9;}
        .firmas{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:40px;}
        .firma-box{text-align:center;}
        .firma-linea{border-top:1px solid #000;margin-top:60px;padding-top:6px;font-size:9pt;}
        .monto-destacado{font-size:14pt;font-weight:700;text-align:center;border:2px solid #000;padding:10px;margin:12px 0;}
        .advertencia{background:#fffde7;border:1px solid #f0c30f;padding:10px;font-size:9pt;margin-top:12px;}
        @media print{
            .btn-imprimir{display:none;}
            body{padding:15px 20px;}
        }
    </style>
</head>
<body>
    <button class="btn-imprimir" onclick="window.print()">Imprimir / Guardar como PDF</button>

    <!-- ENCABEZADO -->
    <div class="encabezado">
        <h1>Cooperativa de Ahorro y Credito CREDISOL</h1>
        <h2>Contrato de Prestamo</h2>
        <p>Supervisada por la Superintendencia de Banca, Seguros y AFP (SBS) — Ley N.° 30822</p>
    </div>

    <div class="codigo">
        <strong>Codigo de Solicitud:</strong> <?= $sol['codigo'] ?> &nbsp;&nbsp;
        <strong>Fecha:</strong> <?= date('d/m/Y') ?>
    </div>

    <!-- CLAUSULA 1: PARTES -->
    <div class="seccion">
        <h3>Clausula Primera: Identificacion de las Partes</h3>
        <div class="clausula">
            <strong>LA COOPERATIVA:</strong> Cooperativa de Ahorro y Credito CREDISOL, debidamente constituida y supervisada por la SBS, con domicilio en Av. Principal 123, Huaraz, Peru; en adelante denominada "LA COOPERATIVA".
        </div>
        <div class="clausula">
            <strong>EL SOCIO PRESTATARIO:</strong>
        </div>
        <table class="tabla-datos">
            <tr><td>Nombre completo</td><td><?= htmlspecialchars($sol['cliente']) ?></td></tr>
            <tr><td>DNI</td><td><?= $sol['dni'] ?></td></tr>
            <tr><td>Correo electronico</td><td><?= htmlspecialchars($sol['correo']) ?></td></tr>
            <tr><td>Telefono</td><td><?= $sol['telefono'] ?? '—' ?></td></tr>
            <tr><td>Direccion</td><td><?= htmlspecialchars($sol['direccion'] ?? '—') ?></td></tr>
        </table>
    </div>

    <!-- CLAUSULA 2: OBJETO -->
    <div class="seccion">
        <h3>Clausula Segunda: Objeto del Contrato</h3>
        <div class="clausula">
            Por el presente contrato, LA COOPERATIVA se compromete a otorgar al SOCIO PRESTATARIO un prestamo bajo las condiciones que se detallan a continuacion, y el SOCIO PRESTATARIO se compromete a devolver el capital prestado mas los intereses generados en el plazo acordado.
        </div>
        <div class="monto-destacado">
            MONTO PRESTADO: <?= soles($sol['monto_solicitado']) ?>
        </div>
        <table class="tabla-datos">
            <tr><td>Tipo de prestamo</td><td><?= htmlspecialchars($sol['tipo']) ?></td></tr>
            <tr><td>Monto solicitado</td><td><?= soles($sol['monto_solicitado']) ?></td></tr>
            <tr><td>Plazo</td><td><?= $sol['plazo_meses'] ?> meses</td></tr>
            <tr><td>Tasa efectiva anual (TEA)</td><td><?= $sol['tasa_interes'] ?>%</td></tr>
            <tr><td>Cuota mensual fija</td><td><?= soles($cuota) ?></td></tr>
            <tr><td>Total intereses a pagar</td><td><?= soles($total_int) ?></td></tr>
            <tr><td>Total a pagar</td><td><strong><?= soles($total_pagar) ?></strong></td></tr>
            <tr><td>Motivo del prestamo</td><td><?= htmlspecialchars($sol['motivo']) ?></td></tr>
            <tr><td>Asesor asignado</td><td><?= htmlspecialchars($sol['asesor'] ?? '—') ?></td></tr>
        </table>
    </div>

    <!-- CLAUSULA 3: PAGOS -->
    <div class="seccion">
        <h3>Clausula Tercera: Condiciones de Pago</h3>
        <div class="clausula">
            El SOCIO PRESTATARIO se obliga a pagar <?= $plazo ?> cuotas mensuales consecutivas de <strong><?= soles($cuota) ?></strong> cada una, a partir del mes siguiente a la fecha de desembolso. Las fechas exactas de cada cuota se detallan en el cronograma de pagos anexo al presente contrato, accesible desde la plataforma web de CREDISOL.
        </div>
        <div class="clausula">
            Los pagos deberan realizarse en las oficinas de la Cooperativa CREDISOL, mediante deposito bancario o transferencia electronica a las cuentas autorizadas por la institucion.
        </div>
    </div>

    <!-- CLAUSULA 4: MORA -->
    <div class="seccion">
        <h3>Clausula Cuarta: Mora e Incumplimiento</h3>
        <div class="clausula">
            En caso de atraso en el pago de cualquier cuota, se generaran intereses moratorios a una tasa equivalente al 1.5 veces la tasa efectiva mensual pactada, calculados sobre el monto vencido desde la fecha de vencimiento hasta la fecha de pago efectivo.
        </div>
        <div class="clausula">
            Si el SOCIO PRESTATARIO acumulara tres (3) cuotas consecutivas impagas, LA COOPERATIVA se reserva el derecho de declarar el vencimiento anticipado del prestamo y exigir el pago inmediato del saldo total pendiente, sin necesidad de requerimiento previo.
        </div>
        <div class="clausula">
            El incumplimiento del presente contrato sera reportado a la Central de Riesgos de la SBS (Infocorp), lo que podra afectar el historial crediticio del SOCIO PRESTATARIO.
        </div>
    </div>

    <!-- CLAUSULA 5: DECLARACIONES -->
    <div class="seccion">
        <h3>Clausula Quinta: Declaraciones del Prestatario</h3>
        <div class="clausula">
            El SOCIO PRESTATARIO declara bajo juramento que:
        </div>
        <div class="clausula">
            a) La informacion proporcionada en la solicitud de prestamo es veridica y completa.<br>
            b) Los fondos recibidos seran destinados exclusivamente al motivo declarado en la solicitud.<br>
            c) Ha leido, comprendido y acepta voluntariamente todas las condiciones establecidas en el presente contrato.<br>
            d) Se compromete a informar a LA COOPERATIVA de cualquier cambio en su situacion economica que pudiera afectar su capacidad de pago.
        </div>
    </div>

    <!-- CLAUSULA 6: JURISDICCION -->
    <div class="seccion">
        <h3>Clausula Sexta: Jurisdiccion y Ley Aplicable</h3>
        <div class="clausula">
            Las partes se someten expresamente a la jurisdiccion de los Juzgados y Tribunales del domicilio de LA COOPERATIVA para la resolucion de cualquier controversia derivada del presente contrato, renunciando a cualquier otro fuero que pudiera corresponderles.
        </div>
        <div class="clausula">
            El presente contrato se rige por las disposiciones de la Ley General del Sistema Financiero (Ley N.° 26702), la Ley N.° 30822, y demas normas emitidas por la SBS aplicables a las Cooperativas de Ahorro y Credito del Peru.
        </div>
    </div>

    <div class="advertencia">
        <strong>Importante:</strong> Antes de firmar este contrato, asegurese de haber leido y comprendido todas las clausulas. Si tiene alguna duda, consulte con el asesor de credito asignado. La firma del presente documento implica la aceptacion plena de todas las condiciones establecidas.
    </div>

    <!-- FIRMAS -->
    <div class="firmas">
        <div class="firma-box">
            <div class="firma-linea">
                <strong>EL SOCIO PRESTATARIO</strong><br>
                <?= htmlspecialchars($sol['cliente']) ?><br>
                DNI: <?= $sol['dni'] ?>
            </div>
        </div>
        <div class="firma-box">
            <div class="firma-linea">
                <strong>POR LA COOPERATIVA CREDISOL</strong><br>
                Representante Legal<br>
                Cargo: Administrador General
            </div>
        </div>
    </div>

    <div style="text-align:center;margin-top:30px;font-size:9pt;color:#666;border-top:1px solid #ccc;padding-top:10px;">
        Huaraz, <?= date('d') ?> de <?= strftime('%B', mktime(0,0,0,date('m'),1)) ?> del <?= date('Y') ?> — 
        Generado por el Sistema Web CREDISOL — <?= date('d/m/Y H:i') ?>
    </div>
</body>
</html>