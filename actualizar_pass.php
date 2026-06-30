<?php
require_once "config/conexion.php";

// Cambia este correo por el del asesor que creaste
$correo = "2201080122@undc.edu.pe";

// La contraseña que quieres que tenga
$nueva_pass = "Credisol123";

$hash = password_hash($nueva_pass, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE usuarios SET contrasena_hash=? WHERE correo=?");
$stmt->bind_param("ss", $hash, $correo);
$stmt->execute();

echo "Listo. Contraseña actualizada para: $correo";
echo "<br>Contraseña: $nueva_pass";
?>