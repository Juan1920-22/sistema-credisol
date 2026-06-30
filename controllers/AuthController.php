<?php
session_start();
require_once "../config/conexion.php";
require_once "../helpers/funciones.php";

$base   = getBase();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if ($accion == 'login')        hacerLogin();
elseif ($accion == 'registro') hacerRegistro();
elseif ($accion == 'logout')   hacerLogout();
else {
    header("Location: " . $base . "/views/auth/login.php");
    exit;
}
// FACTORY METHOD: Según el tipo de usuario (rol),
// el sistema "fabrica" una sesión y experiencia diferente
function hacerLogin() {
    global $conn, $base;
    $correo     = limpiar($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if (!$correo || !$contrasena) {
        setMensaje("Completa todos los campos.", "error");
        header("Location: " . $base . "/views/auth/login.php");
        exit;
    }
// Consulta base — obtiene el ROL del usuario
    $stmt = $conn->prepare(
        "SELECT u.*, r.nombre AS rol_nombre
         FROM usuarios u JOIN roles r ON u.rol_id = r.id
         WHERE u.correo = ? AND u.activo = 1"
    );
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario || !password_verify($contrasena, $usuario['contrasena_hash'])) {
        setMensaje("Correo o contraseña incorrectos.", "error");
        header("Location: " . $base . "/views/auth/login.php");
        exit;
    }
// Construir la sesión del usuario
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['rol_id']     = $usuario['rol_id'];
    $_SESSION['nombres']    = $usuario['nombres'];
    $_SESSION['apellidos']  = $usuario['apellidos'];
    $_SESSION['correo']     = $usuario['correo'];
    $_SESSION['rol_nombre'] = $usuario['rol_nombre'];
    $_SESSION['foto']       = $usuario['foto'] ?? null;
// ===== FACTORY METHOD =====
    // Según el ROL, fabrica una experiencia diferente
    // Cada rama crea un "producto" distinto: vista + permisos
    if ($usuario['rol_id'] == 1)
        // Fábrica de sesión CLIENTE
        // Producto: Dashboard con solicitudes, ahorros, pagos
        header("Location: " . $base . "/views/cliente/dashboard.php");
    elseif ($usuario['rol_id'] == 2)
        // Fábrica de sesión ASESOR
        // Producto: Panel con solicitudes asignadas para evaluar
        header("Location: " . $base . "/views/asesor/dashboard.php");

    elseif ($usuario['rol_id'] == 3)
        // Fábrica de sesión ADMINISTRADOR
        // Producto: Panel completo con control total del sistema
        header("Location: " . $base . "/views/admin/dashboard.php");
    exit;
}
// También aplica Factory al crear usuarios
// El admin "fabrica" diferentes tipos de usuarios
function hacerRegistro() {
    global $conn, $base;
    $nombres    = limpiar($_POST['nombres'] ?? '');
    $apellidos  = limpiar($_POST['apellidos'] ?? '');
    $dni        = limpiar($_POST['dni'] ?? '');
    $correo     = limpiar($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar  = $_POST['confirmar_contrasena'] ?? '';

    // Validaciones
    if (!$nombres || !$apellidos) {
        setMensaje("El nombre y apellido son obligatorios.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }
    if (strlen($dni) !== 8 || !ctype_digit($dni)) {
        setMensaje("El DNI debe tener exactamente 8 dígitos numéricos.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        setMensaje("El correo no es válido.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }
    if (strlen($contrasena) < 8) {
        setMensaje("La contraseña debe tener al menos 8 caracteres.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }
    if ($contrasena !== $confirmar) {
        setMensaje("Las contraseñas no coinciden.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }

    // Verificar duplicados
    $check = $conn->prepare("SELECT id FROM usuarios WHERE correo = ? OR dni = ?");
    $check->bind_param("ss", $correo, $dni);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        setMensaje("Ya existe una cuenta con ese correo o DNI.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }

    $hash = password_hash($contrasena, PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO usuarios (rol_id, dni, nombres, apellidos, correo, contrasena_hash)
         VALUES (1, ?, ?, ?, ?, ?)"
         //        ^--- rol_id=1 fabrica siempre un CLIENTE
    );
    // Al crear desde el admin, puede fabricar ASESOR (rol_id=2)
    // o CLIENTE (rol_id=1) según la necesidad
    $stmt->bind_param("sssss", $dni, $nombres, $apellidos, $correo, $hash);

    if (!$stmt->execute()) {
        setMensaje("Error al crear la cuenta. Intenta de nuevo.", "error");
        header("Location: " . $base . "/views/auth/registro.php");
        exit;
    }

    $nuevoId = $conn->insert_id;

    // Crear historial crediticio vacío
    $hist = $conn->prepare(
        "INSERT INTO historial_crediticio (cliente_id, score_crediticio) VALUES (?, 500)"
    );
    $hist->bind_param("i", $nuevoId);
    $hist->execute();

    setMensaje("Cuenta creada exitosamente. Ya puedes iniciar sesión.", "exito");
    header("Location: " . $base . "/views/auth/login.php");
    exit;
}

function hacerLogout() {
    global $base;
    session_unset();
    session_destroy();
    header("Location: " . $base . "/views/auth/login.php");
    exit;
}