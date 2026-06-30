<?php
session_start();
require_once "../config/conexion.php";
require_once "../helpers/funciones.php";

$base   = getBase();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion == 'nueva_solicitud') nuevaSolicitud();
else {
    header("Location: " . $base . "/views/cliente/solicitar.php");
    exit;
}
// controllers/SolicitudController.php
// DECORATOR: Cada validación es una capa decoradora
// La solicitud base solo guarda datos en BD
// Cada capa "decora" agregando una validación

function nuevaSolicitud() {
    // SUJETO: La solicitud cambia de estado
    global $conn, $base;

    $cliente_id      = $_SESSION['usuario_id'];
    $tipo_id         = intval($_POST['tipo_prestamo_id'] ?? 0);
    $monto           = floatval($_POST['monto_solicitado'] ?? 0);
    $plazo           = intval($_POST['plazo_meses'] ?? 0);
    $motivo          = limpiar($_POST['motivo'] ?? '');
    $tasa            = floatval($_POST['tasa_interes'] ?? 0);
    $monto_min       = floatval($_POST['monto_min'] ?? 0);
    $monto_max       = floatval($_POST['monto_max'] ?? 0);

    // Validaciones
    if (!$tipo_id || !$monto || !$plazo || !$motivo) {
        setMensaje("Completa todos los campos.", "error");
        header("Location: " . $base . "/views/cliente/solicitar.php");
        exit;
    }
    if ($monto < $monto_min || $monto > $monto_max) {
        setMensaje("El monto no está en el rango permitido.", "error");
        header("Location: " . $base . "/views/cliente/solicitar.php");
        exit;
    }
    if (strlen($motivo) < 20) {
        setMensaje("El motivo debe tener al menos 20 caracteres.", "error");
        header("Location: " . $base . "/views/cliente/solicitar.php");
        exit;
    }

    // Calcular cuota mensual
    $tasa_mensual = ($tasa / 100) / 12;
    if ($tasa_mensual > 0) {
        $cuota = $monto * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo))
                 / (pow(1 + $tasa_mensual, $plazo) - 1);
    } else {
        $cuota = $monto / $plazo;
    }
    $cuota = round($cuota, 2);

    // Generar código único SOL-AÑO-NNNN
    $anio   = date('Y');
    $ultimo = $conn->query("SELECT codigo FROM solicitudes ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $nro    = 1;
    if ($ultimo) {
        preg_match('/(\d+)$/', $ultimo['codigo'], $m);
        $nro = intval($m[1] ?? 0) + 1;
    }
    $codigo = "SOL-$anio-" . str_pad($nro, 4, '0', STR_PAD_LEFT);

    // Insertar solicitud
    $stmt = $conn->prepare(
        "INSERT INTO solicitudes
         (codigo, cliente_id, tipo_prestamo_id, monto_solicitado, plazo_meses, cuota_estimada, motivo, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')"
    );
    $stmt->bind_param("siidids",
        $codigo, $cliente_id, $tipo_id,
        $monto, $plazo, $cuota, $motivo
    );

    if (!$stmt->execute()) {
        setMensaje("Error al enviar la solicitud. Intenta de nuevo.", "error");
        header("Location: " . $base . "/views/cliente/solicitar.php");
        exit;
    }

    $solicitud_id = $conn->insert_id;

    // Buscar asesor con menos carga y asignar
    $asesor = $conn->query(
        "SELECT u.id, COUNT(s.id) AS carga
         FROM usuarios u
         LEFT JOIN solicitudes s ON s.asesor_id = u.id
           AND s.estado IN ('pendiente','en_evaluacion')
         WHERE u.rol_id = 2 AND u.activo = 1
         GROUP BY u.id
         ORDER BY carga ASC LIMIT 1"
    )->fetch_assoc();

    if ($asesor) {
        $upd = $conn->prepare("UPDATE solicitudes SET asesor_id = ? WHERE id = ?");
        $upd->bind_param("ii", $asesor['id'], $solicitud_id);
        $upd->execute();

        // Notificar al asesor
        $msg = "Se te asignó la solicitud $codigo del cliente.";
        $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Nueva solicitud asignada', ?, 'info')");
        $n->bind_param("is", $asesor['id'], $msg);
        $n->execute();
    }

    // Notificar al cliente
    $msg2 = "Tu solicitud $codigo fue enviada correctamente y está siendo revisada.";
    $n2 = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud enviada', ?, 'exito')");
    $n2->bind_param("is", $cliente_id, $msg2);
    $n2->execute();

    // Log de auditoría
    $log = $conn->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id) VALUES (?, 'nueva_solicitud', 'solicitudes', ?)");
    $log->bind_param("ii", $cliente_id, $solicitud_id);
    $log->execute();

    setMensaje("¡Solicitud $codigo enviada exitosamente! Un asesor la revisará pronto.", "exito");
    header("Location: " . $base . "/views/cliente/mis_solicitudes.php");
    exit;
}