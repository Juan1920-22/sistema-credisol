<?php
// helpers/funciones.php

function getBase() {
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        return 'https://' . $_SERVER['HTTP_X_FORWARDED_HOST'] . '/cooperativa';
    }
    return 'http://localhost/cooperativa';
}

function limpiar($valor) {
    return htmlspecialchars(strip_tags(trim($valor)));
}
// ===== FUNCIÓN PURA 1: Formateo de moneda =====
// - Entrada: número flotante
// - Salida: string formateado
// - Sin efectos secundarios
// - Transparencia referencial: soles(1500) = "S/ 1,500.00" SIEMPRE
function soles($monto) {
    return "S/ " . number_format($monto, 2, '.', ',');
}
// Ejemplos de uso:
// soles(1500)     → "S/ 1,500.00"
// soles(0)        → "S/ 0.00"
// soles(99999.99) → "S/ 99,999.99"

// ===== FUNCIÓN PURA 2: Formateo de fechas =====
// - Entrada: string de fecha en formato Y-m-d
// - Salida: string en formato d/m/Y
// - Sin efectos secundarios
function fechaCorta($fecha) {
    if (!$fecha) return "—";
    return date("d/m/Y", strtotime($fecha));
}

function calcularCuota($monto, $tasaAnual, $plazoMeses) {
    $tasa = ($tasaAnual / 100) / 12;
    if ($tasa == 0) return round($monto / $plazoMeses, 2);
    $cuota = $monto * ($tasa * pow(1 + $tasa, $plazoMeses)) / (pow(1 + $tasa, $plazoMeses) - 1);
    return round($cuota, 2);
}

function badgeEstado($estado) {
    $estilos = [
        'pendiente'        => 'background:#fef3c7;color:#92400e;',
        'en_evaluacion'    => 'background:#dbeafe;color:#1e40af;',
        'aprobada_asesor'  => 'background:#ede9fe;color:#5b21b6;',
        'rechazada_asesor' => 'background:#fee2e2;color:#991b1b;',
        'aprobada'         => 'background:#d1fae5;color:#065f46;',
        'rechazada'        => 'background:#fee2e2;color:#991b1b;',
        'desembolsada'     => 'background:#a7f3d0;color:#064e3b;',
    ];
    $textos = [
        'pendiente'        => 'Pendiente',
        'en_evaluacion'    => 'En evaluación',
        'aprobada_asesor'  => 'Aprob. Asesor',
        'rechazada_asesor' => 'Rechazada',
        'aprobada'         => 'Aprobada',
        'rechazada'        => 'Rechazada',
        'desembolsada'     => 'Desembolsada',
    ];
    $estilo = $estilos[$estado] ?? 'background:#f3f4f6;color:#374151;';
    $texto  = $textos[$estado]  ?? ucfirst($estado);
    return "<span style=\"{$estilo}padding:3px 10px;border-radius:20px;font-size:0.78rem;font-weight:600;\">{$texto}</span>";
}

function requiereLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: " . getBase() . "/views/auth/login.php");
        exit;
    }
}

function requiereRol($rolesPermitidos) {
    requiereLogin();
    if (!in_array($_SESSION['rol_id'], $rolesPermitidos)) {
        header("Location: " . getBase() . "/views/auth/login.php");
        exit;
    }
}

function setMensaje($texto, $tipo = 'info') {
    $_SESSION['mensaje'] = ['texto' => $texto, 'tipo' => $tipo];
}

function mostrarMensaje() {
    if (!isset($_SESSION['mensaje'])) return;
    $m = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    $colores = [
        'exito'       => 'background:#f0fdf4;color:#15803d;border-left:3px solid #22c55e;',
        'error'       => 'background:#fef2f2;color:#b91c1c;border-left:3px solid #ef4444;',
        'info'        => 'background:#eff6ff;color:#1e40af;border-left:3px solid #3b82f6;',
        'advertencia' => 'background:#fefce8;color:#854d0e;border-left:3px solid #eab308;',
    ];
    $estilo = $colores[$m['tipo']] ?? $colores['info'];
    echo "<div style=\"{$estilo}padding:12px 16px;border-radius:6px;margin-bottom:16px;font-weight:500;font-size:0.9rem;\">"
       . limpiar($m['texto']) . "</div>";
}

// Mostrar avatar: foto si existe, inicial si no
function avatar($nombre, $foto = null, $size = 38, $color = '#3b82f6') {
    $foto_sesion = $_SESSION['foto'] ?? null;
    $foto_usar   = $foto ?: $foto_sesion;
    
    if ($foto_usar) {
        $ruta = __DIR__ . '/../' . $foto_usar;
        if (file_exists($ruta)) {
            return "<img src='../../{$foto_usar}?v=" . time() . "' 
                    style='width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;flex-shrink:0;'>";
        }
    }
    // Mostrar inicial
    $inicial = strtoupper(substr($nombre, 0, 1));
    return "<div style='width:{$size}px;height:{$size}px;border-radius:50%;background:linear-gradient(135deg,{$color},#1d4ed8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:" . round($size * 0.4) . "px;flex-shrink:0;'>{$inicial}</div>";
}

// Actualizar cuotas vencidas y carteras en mora automáticamente
function actualizarMora($conn) {
    $hoy = date('Y-m-d');
    // Marcar cuotas vencidas
    $conn->query("UPDATE pagos SET estado='vencido' 
                  WHERE fecha_vencimiento < '$hoy' AND estado='pendiente'");
    // Marcar carteras en mora si tienen cuotas vencidas
    $conn->query("UPDATE cartera_prestamos SET estado='en_mora'
                  WHERE estado='vigente' AND id IN (
                      SELECT DISTINCT cartera_id FROM pagos WHERE estado='vencido'
                  )");
}