<?php
require_once "config/conexion.php";
$hash = password_hash("admin123", PASSWORD_BCRYPT);
$conn->query("UPDATE usuarios SET contrasena_hash='$hash' WHERE correo='admin@cooperativa.com'");
echo "Hash actualizado. Contraseña: admin123";
?>