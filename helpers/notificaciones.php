<?php
// helpers/notificaciones.php
// Devuelve notificaciones del usuario en sesión como JSON
session_start();
require_once __DIR__ . "/../config/conexion.php";

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['total' => 0, 'items' => []]);
    exit;
}

$uid    = $_SESSION['usuario_id'];
$accion = $_GET['accion'] ?? 'listar';

// Marcar como leída
if ($accion == 'leer' && isset($_GET['id'])) {
    $nid = intval($_GET['id']);
    $conn->query("UPDATE notificaciones SET leida=1 WHERE id=$nid AND usuario_id=$uid");
    echo json_encode(['ok' => true]);
    exit;
}

// Marcar todas como leídas
if ($accion == 'leer_todas') {
    $conn->query("UPDATE notificaciones SET leida=1 WHERE usuario_id=$uid");
    echo json_encode(['ok' => true]);
    exit;
}

// Listar notificaciones
$notifs = $conn->query(
    "SELECT * FROM notificaciones WHERE usuario_id=$uid
     ORDER BY creado_en DESC LIMIT 15"
)->fetch_all(MYSQLI_ASSOC);

$total_no_leidas = $conn->query(
    "SELECT COUNT(*) AS t FROM notificaciones WHERE usuario_id=$uid AND leida=0"
)->fetch_assoc()['t'] ?? 0;

header('Content-Type: application/json');
echo json_encode([
    'total' => intval($total_no_leidas),
    'items' => $notifs
]);