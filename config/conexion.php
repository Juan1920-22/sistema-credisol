<?php
// config/conexion.php
// SINGLETON: Una única conexión a la base de datos
// compartida por todos los módulos del sistema
$host     = "localhost";
$usuario  = "root";
$password = "";
$base     = "cooperativa_db";
// Instancia única — se crea UNA SOLA VEZ
$conn = new mysqli($host, $usuario, $password, $base);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}