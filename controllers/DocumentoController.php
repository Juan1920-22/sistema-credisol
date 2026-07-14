<?php
// controllers/DocumentoController.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../config/conexion.php";
require_once "../helpers/funciones.php";

$base   = getBase();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion == 'subir_documento')             subirDocumento();
elseif ($accion == 'enviar_mensaje')          enviarMensaje();
elseif ($accion == 'pedir_documentos')        pedirDocumentos();
elseif ($accion == 'aprobar_y_enviar_admin')  aprobarYEnviarAdmin();
elseif ($accion == 'enviar_contrato_cliente') enviarContratoCliente();
elseif ($accion == 'firmar_contrato_cliente') firmarContratoCliente();
elseif ($accion == 'registrar_metodo_desembolso') registrarMetodoDesembolso();
else header("Location: " . $base . "/views/auth/login.php");

// ============================================================
// SUBIR DOCUMENTO (cliente)
// ============================================================
function subirDocumento() {
    global $conn, $base;
    requiereRol([1]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $tipo         = limpiar($_POST['tipo'] ?? 'otro');
    $cliente_id   = $_SESSION['usuario_id'];

    if (!$solicitud_id) {
        setMensaje("Solicitud no identificada.", "error");
        header("Location: /cooperativa/views/cliente/mis_solicitudes.php");
        exit;
    }

    // Verificar que la solicitud pertenece al cliente
    $sol = $conn->query("SELECT id, asesor_id, codigo FROM solicitudes WHERE id=$solicitud_id AND cliente_id=$cliente_id")->fetch_assoc();
    if (!$sol) {
        setMensaje("Solicitud no valida.", "error");
        header("Location: /cooperativa/views/cliente/mis_solicitudes.php");
        exit;
    }

    // Crear carpeta
    $carpeta = __DIR__ . "/../public/uploads/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

    // Recoger archivos — soporta tanto 'archivos[]' como 'archivo'
    $lista = [];
    if (!empty($_FILES['archivos']['name'][0])) {
        $f = $_FILES['archivos'];
        for ($i = 0; $i < count($f['name']); $i++) {
            $lista[] = [
                'name'     => $f['name'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ];
        }
    } elseif (!empty($_FILES['archivo']['name'])) {
        $lista[] = [
            'name'     => $_FILES['archivo']['name'],
            'tmp_name' => $_FILES['archivo']['tmp_name'],
            'error'    => $_FILES['archivo']['error'],
            'size'     => $_FILES['archivo']['size'],
        ];
    }

    if (empty($lista)) {
        $debug_files = json_encode(array_map(function($k){ return $k; }, array_keys($_FILES)));
        $debug_post  = json_encode(array_keys($_POST));
        setMensaje("DEBUG — FILES: $debug_files | POST: $debug_post", "error");
        header("Location: /cooperativa/views/cliente/mis_solicitudes.php?id=$solicitud_id");
        exit;
    }

    $permitidos = ['jpg','jpeg','png','gif','webp','pdf','heic','heif','bmp','tiff','JPG','PNG','JPEG','PDF'];
    $subidos    = 0;
    $errores    = 0;

    foreach ($lista as $i => $archivo) {
        // Mostrar info del archivo para debug
        $err_code = $archivo['error'];
        $tam      = $archivo['size'];
        $ext      = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if ($err_code !== 0) {
            $errores++;
            setMensaje("Error PHP en archivo {$archivo['name']}: codigo $err_code", "error");
            continue;
        }
        if ($tam > 10 * 1024 * 1024) { $errores++; continue; }
        if (!in_array($ext, $permitidos) && !in_array(strtolower($ext), $permitidos)) { 
            setMensaje("Extension no permitida: '$ext' en archivo {$archivo['name']}", "error");
            $errores++; 
            continue; 
        }

        $nombre_archivo = $tipo . '_' . $solicitud_id . '_' . time() . '_' . $i . '.' . $ext;
        $ruta_fisica    = $carpeta . $nombre_archivo;

        if (move_uploaded_file($archivo['tmp_name'], $ruta_fisica)) {
            $ruta_db = "public/uploads/" . $nombre_archivo;
            $stmt = $conn->prepare("INSERT INTO documentos (solicitud_id, tipo, nombre_archivo, ruta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $solicitud_id, $tipo, $archivo['name'], $ruta_db);
            $stmt->execute();
            $subidos++;
        } else {
            $errores++;
        }
    }

    // Notificar al asesor
    $sol2 = $conn->query("SELECT asesor_id, codigo FROM solicitudes WHERE id=$solicitud_id")->fetch_assoc();
    if ($sol2 && $sol2['asesor_id'] && $subidos > 0) {
        $msg = "El cliente subio $subidos documento(s) de tipo '{$tipo}' para la solicitud {$sol2['codigo']}.";
        $stmt2 = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Nuevos documentos', ?, 'info')");
        $stmt2->bind_param("is", $sol2['asesor_id'], $msg);
        $stmt2->execute();
    }

    if ($subidos > 0 && $errores == 0) {
        setMensaje("$subidos documento(s) subido(s) correctamente.", "exito");
    } elseif ($subidos > 0) {
        setMensaje("$subidos documento(s) subido(s). $errores no pudieron subirse.", "advertencia");
    } else {
        setMensaje("No se pudo subir ningun documento. Verifica el formato y tamano.", "error");
    }
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

function aprobarYEnviarAdmin() {
    global $conn, $base;
    requiereRol([2]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $asesor_id    = $_SESSION['usuario_id'];

    // Verificar que la solicitud sea del asesor
    $sol = $conn->query(
        "SELECT s.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente_nombre
         FROM solicitudes s
         JOIN usuarios u ON s.cliente_id = u.id
         WHERE s.id=$solicitud_id AND s.asesor_id=$asesor_id
         AND s.estado IN ('pendiente','en_evaluacion')"
    )->fetch_assoc();

    if (!$sol) {
        setMensaje("Solicitud no valida.", "error");
        header("Location: " . $base . "/views/asesor/solicitudes.php");
        exit;
    }

    // Cambiar estado a aprobada_asesor (Pipes & Filters)
    $conn->query(
        "UPDATE solicitudes SET estado='aprobada_asesor', fecha_evaluacion=NOW()
         WHERE id=$solicitud_id AND asesor_id=$asesor_id"
    );

    // REACCION 1: Notificar al ADMINISTRADOR (Observer)
    $admins = $conn->query("SELECT id FROM usuarios WHERE rol_id=3 AND activo=1");
    while ($adm = $admins->fetch_assoc()) {
        $msg_admin = "El asesor verifico los documentos de la solicitud {$sol['codigo']} del cliente {$sol['cliente_nombre']}. Esta lista para su aprobacion final.";
        $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud lista para aprobar', ?, 'info')");
        $n->bind_param("is", $adm['id'], $msg_admin);
        $n->execute();
    }

    // REACCION 2: Notificar al CLIENTE (Observer)
    $msg_cliente = "Tus documentos han sido verificados por el asesor. Tu solicitud {$sol['codigo']} pasa ahora a manos del administrador para la validacion final y desembolso. Te notificaremos el resultado.";
    $nc = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Documentos verificados', ?, 'exito')");
    $nc->bind_param("is", $sol['cliente_id'], $msg_cliente);
    $nc->execute();

    // REACCION 3: Enviar mensaje automatico al chat del cliente
    $msg_chat = "Tus documentos han sido verificados. Tu solicitud pasa ahora al administrador para la aprobacion final y desembolso. Te notificaremos pronto.";
    $mc = $conn->prepare("INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, 'asesor')");
    $mc->bind_param("iis", $solicitud_id, $asesor_id, $msg_chat);
    $mc->execute();

    setMensaje("Documentos verificados. Solicitud enviada al administrador. El cliente fue notificado.", "exito");
    header("Location: " . $base . "/views/asesor/solicitudes.php?id=$solicitud_id");
    exit;
}

function enviarContratoCliente() {
    global $conn, $base;
    requiereRol([2]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $asesor_id    = $_SESSION['usuario_id'];

    $sol = $conn->query(
        "SELECT s.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente_nombre
         FROM solicitudes s
         JOIN usuarios u ON s.cliente_id = u.id
         WHERE s.id=$solicitud_id AND s.asesor_id=$asesor_id AND s.estado='aprobada'"
    )->fetch_assoc();

    if (!$sol) {
        setMensaje("Solicitud no valida.", "error");
        header("Location: " . $base . "/views/asesor/solicitudes.php");
        exit;
    }

    // Marcar que el contrato fue enviado al cliente
    $conn->query("UPDATE solicitudes SET contrato_enviado_cliente=1 WHERE id=$solicitud_id");

    // Notificar al CLIENTE con instrucciones de firma digital
    $msg = "Tu contrato de prestamo para la solicitud {$sol['codigo']} esta listo. Por favor ingresa a 'Mis Solicitudes', descarga el contrato, coloca tu firma digital y envialo de vuelta. Necesitamos tu firma para proceder con el desembolso.";
    $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Contrato listo para tu firma', ?, 'info')");
    $n->bind_param("is", $sol['cliente_id'], $msg);
    $n->execute();

    // Mensaje en el chat
    $msg_chat = "Tu contrato de prestamo esta listo para firmar. Ingresa a 'Mis Solicitudes' y busca la opcion 'Firmar Contrato'. Una vez que lo firmes y envies, procederemos con el desembolso de tu dinero.";
    $mc = $conn->prepare("INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, 'asesor')");
    $mc->bind_param("iis", $solicitud_id, $asesor_id, $msg_chat);
    $mc->execute();

    setMensaje("Contrato enviado al cliente. Espera su firma digital.", "exito");
    header("Location: " . $base . "/views/asesor/solicitudes.php?id=$solicitud_id");
    exit;
}

function firmarContratoCliente() {
    global $conn, $base;
    requiereRol([1]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $cliente_id   = $_SESSION['usuario_id'];
    $firma_data   = $_POST['firma_data'] ?? '';

    if (!$solicitud_id || !$firma_data) {
        setMensaje("Datos incompletos.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php");
        exit;
    }

    $sol = $conn->query(
        "SELECT * FROM solicitudes WHERE id=$solicitud_id AND cliente_id=$cliente_id AND estado='aprobada'"
    )->fetch_assoc();

    if (!$sol) {
        setMensaje("Solicitud no valida.", "error");
        header("Location: " . $base . "/views/cliente/mis_solicitudes.php");
        exit;
    }

    // Guardar imagen de la firma digital
    $carpeta = __DIR__ . "/../public/uploads/firmas/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0777, true);

    $nombre_firma = "firma_cliente_" . $solicitud_id . "_" . time() . ".png";
    $ruta_firma   = $carpeta . $nombre_firma;
    $ruta_db      = "public/uploads/firmas/" . $nombre_firma;

    // Decodificar base64 y guardar como imagen PNG
    $data_sin_header = substr($firma_data, strpos($firma_data, ',') + 1);
    $imagen_decodificada = base64_decode($data_sin_header);
    file_put_contents($ruta_firma, $imagen_decodificada);

    // Guardar en BD — contrato_firmado guarda la firma del cliente
    $conn->query(
        "UPDATE solicitudes SET contrato_firmado='$ruta_db', fecha_firma=NOW() WHERE id=$solicitud_id"
    );

    // Notificar al ASESOR
    $msg_asesor = "El cliente firmo digitalmente el contrato de la solicitud {$sol['codigo']}. Ya puedes preguntar el metodo de desembolso y enviar al administrador.";
    $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Cliente firmo el contrato', ?, 'exito')");
    $n->bind_param("is", $sol['asesor_id'], $msg_asesor);
    $n->execute();

    // Mensaje en chat
    $msg_chat = "¡Gracias! Tu firma digital fue recibida. El asesor procedera a gestionar el desembolso de tu prestamo.";
    $asesor_id = $sol['asesor_id'];
    $mc = $conn->prepare("INSERT INTO mensajes_solicitud (solicitud_id, usuario_id, mensaje, tipo) VALUES (?, ?, ?, 'asesor')");
    $mc->bind_param("iis", $solicitud_id, $asesor_id, $msg_chat);
    $mc->execute();

    setMensaje("Contrato firmado exitosamente. El asesor fue notificado y procedera con el desembolso.", "exito");
    header("Location: " . $base . "/views/cliente/mis_solicitudes.php?id=$solicitud_id");
    exit;
}

function registrarMetodoDesembolso() {
    global $conn, $base;
    requiereRol([2]);

    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $asesor_id    = $_SESSION['usuario_id'];
    $metodo       = limpiar($_POST['metodo_desembolso'] ?? '');
    $cuenta       = limpiar($_POST['cuenta_desembolso'] ?? '');
    $banco        = limpiar($_POST['banco_desembolso'] ?? '');

    if (!$solicitud_id || !$metodo) {
        setMensaje("Selecciona el metodo de desembolso.", "error");
        header("Location: " . $base . "/views/asesor/solicitudes.php");
        exit;
    }

    $sol = $conn->query(
        "SELECT s.*, CONCAT(u.nombres,' ',u.apellidos) AS cliente_nombre
         FROM solicitudes s
         JOIN usuarios u ON s.cliente_id = u.id
         WHERE s.id=$solicitud_id AND s.asesor_id=$asesor_id
         AND s.estado='aprobada' AND s.contrato_firmado IS NOT NULL"
    )->fetch_assoc();

    if (!$sol) {
        setMensaje("Solicitud no valida o contrato pendiente.", "error");
        header("Location: " . $base . "/views/asesor/solicitudes.php");
        exit;
    }

    // Guardar metodo de desembolso
    $stmt = $conn->prepare(
        "UPDATE solicitudes SET metodo_desembolso=?, cuenta_desembolso=?, banco_desembolso=?, listo_para_desembolso=1 WHERE id=?"
    );
    $stmt->bind_param("sssi", $metodo, $cuenta, $banco, $solicitud_id);
    $stmt->execute();

    // Descripcion del metodo
    $desc_metodo = $metodo == 'caja' ? 'Retiro por Caja' : "Transferencia a {$banco} — Cuenta: {$cuenta}";

    // Notificar al ADMINISTRADOR
    $admins = $conn->query("SELECT id FROM usuarios WHERE rol_id=3 AND activo=1");
    while ($adm = $admins->fetch_assoc()) {
        $msg = "La solicitud {$sol['codigo']} de {$sol['cliente_nombre']} esta lista para desembolso. Contrato firmado por el cliente. Metodo solicitado: {$desc_metodo}.";
        $n = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Solicitud lista para desembolso', ?, 'exito')");
        $n->bind_param("is", $adm['id'], $msg);
        $n->execute();
    }

    // Notificar al CLIENTE
    $msg_c = "Tu solicitud {$sol['codigo']} esta en proceso de desembolso. Metodo: {$desc_metodo}. Te notificaremos cuando el dinero haya sido enviado.";
    $nc = $conn->prepare("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Desembolso en proceso', ?, 'info')");
    $nc->bind_param("is", $sol['cliente_id'], $msg_c);
    $nc->execute();

    setMensaje("Informacion enviada al administrador. Procedera con el desembolso.", "exito");
    header("Location: " . $base . "/views/asesor/solicitudes.php?id=$solicitud_id");
    exit;
}