<?php
// controllers/DocumentoController.php
session_start();
require_once "../config/conexion.php";
require_once "../helpers/funciones.php";

$base   = getBase();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion == 'subir_documento')      subirDocumento();
elseif ($accion == 'enviar_mensaje')   enviarMensaje();
elseif ($accion == 'pedir_documentos') pedirDocumentos();
else header("Location: " . $base . "/views/auth/login.php");

// ============================================================
// SUBIR DOCUMENTO (cliente)
// ============================================================
// DECORATOR aplicado a la subida de archivos

function subirDocumento() {
    global $conn, $base;
    requiereRol([1]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $tipo         = limpiar($_POST['tipo'] ?? '');

    if (!$solicitud_id || !$tipo) {
        setMensaje("Datos incompletos.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php");
        exit;
    }

    // Verificar que la solicitud sea del cliente
    $sol = $conn->query("SELECT id FROM solicitudes WHERE id=$solicitud_id AND cliente_id={$_SESSION['usuario_id']}")->fetch_assoc();
    if (!$sol) {
        setMensaje("Solicitud no válida.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php");
        exit;
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
        setMensaje("Error al subir el archivo.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    $archivo  = $_FILES['archivo'];
    $ext      = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','pdf'];

    if (!in_array($ext, $permitidos)) {
        setMensaje("Solo se permiten JPG, PNG o PDF.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    if ($archivo['size'] > 5 * 1024 * 1024) {
        setMensaje("El archivo no puede pesar más de 5MB.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    // Crear carpeta si no existe
    $carpeta = __DIR__ . "/../public/uploads/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    // Verificar que se pueda escribir
    if (!is_writable($carpeta)) {
        setMensaje("Error: La carpeta de uploads no tiene permisos de escritura.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    $nombre_archivo = $tipo . '_' . $solicitud_id . '_' . time() . '.' . $ext;
    $ruta           = $carpeta . $nombre_archivo;

    if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
        setMensaje("No se pudo guardar el archivo. Verifica que la carpeta public/uploads/ exista.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    // Guardar en BD
    $stmt = $conn->prepare(
        "INSERT INTO documentos (solicitud_id, tipo, nombre_archivo, ruta) VALUES (?, ?, ?, ?)"
    );
    $ruta_db = "public/uploads/" . $nombre_archivo;
    $stmt->bind_param("isss", $solicitud_id, $tipo, $archivo['name'], $ruta_db);
    $stmt->execute();

    // Notificar al asesor
    $sol2 = $conn->query("SELECT asesor_id, codigo FROM solicitudes WHERE id=$solicitud_id")->fetch_assoc();
    if ($sol2 && $sol2['asesor_id']) {
        $msg = "El cliente subió un documento ({$tipo}) para la solicitud {$sol2['codigo']}.";
        $stmt2 = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Nuevo documento', ?, 'info')");
        $stmt2->bind_param("is", $sol2['asesor_id'], $msg);
        $stmt2->execute();
    }

    setMensaje("Documento subido correctamente.", "exito");
    header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
    exit;
}

// ============================================================
// ENVIAR MENSAJE (cliente o asesor)
// ============================================================
function enviarMensaje() {
    global $conn, $base;
    requiereLogin();

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $mensaje      = limpiar($_POST['mensaje'] ?? '');
    $rol_id       = $_SESSION['rol_id'];

    if (!$solicitud_id || !$mensaje) {
        setMensaje("El mensaje no puede estar vacío.", "error");
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $tipo = $rol_id == 1 ? 'cliente' : 'asesor';
    $uid  = $_SESSION['usuario_id'];

    $stmt = $conn->prepare(
        "INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("iiss", $solicitud_id, $uid, $mensaje, $tipo);
    $stmt->execute();

    // Notificar al otro
    $sol = $conn->query("SELECT cliente_id, asesor_id, codigo FROM solicitudes WHERE id=$solicitud_id")->fetch_assoc();
    if ($sol) {
        $dest = $rol_id == 1 ? $sol['asesor_id'] : $sol['cliente_id'];
        if ($dest) {
            $quien = $rol_id == 1 ? 'El cliente' : 'El asesor';
            $msg   = "$quien envió un mensaje sobre la solicitud {$sol['codigo']}.";
            $stmt2 = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Nuevo mensaje', ?, 'info')");
            $stmt2->bind_param("is", $dest, $msg);
            $stmt2->execute();
        }
    }

    setMensaje("Mensaje enviado.", "exito");
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// ============================================================
// PEDIR DOCUMENTOS (asesor)
// ============================================================
function pedirDocumentos() {
    global $conn, $base;
    requiereRol([2]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $docs         = $_POST['docs'] ?? [];
    $mensaje_extra = limpiar($_POST['mensaje_extra'] ?? '');

    if (!$solicitud_id || empty($docs)) {
        setMensaje("Selecciona al menos un documento.", "error");
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $tipos = [
        'dni'             => 'DNI (ambas caras)',
        'recibo_ingreso'  => 'Boleta de pago / Constancia de ingresos',
        'recibo_servicio' => 'Recibo de luz o agua',
        'otro'            => 'Otro documento',
    ];

    $lista = implode(', ', array_map(fn($d) => $tipos[$d] ?? $d, $docs));
    $mensaje = "Por favor sube los siguientes documentos para continuar con tu evaluación: $lista.";
    if ($mensaje_extra) $mensaje .= " Nota: $mensaje_extra";

    $uid = $_SESSION['usuario_id'];
    $stmt = $conn->prepare(
        "INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, 'asesor')"
    );
    $stmt->bind_param("iis", $solicitud_id, $uid, $mensaje);
    $stmt->execute();

    // Notificar al cliente
    $sol = $conn->query("SELECT cliente_id, codigo FROM solicitudes WHERE id=$solicitud_id")->fetch_assoc();
    if ($sol) {
        $stmt2 = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Documentos requeridos', ?, 'advertencia')");
        $stmt2->bind_param("is", $sol['cliente_id'], $mensaje);
        $stmt2->execute();
    }

    setMensaje("Solicitud de documentos enviada al cliente.", "exito");
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}