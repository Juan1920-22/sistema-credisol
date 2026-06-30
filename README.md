# CREDISOL — Sistema Web de Cooperativa de Ahorro y Crédito

Sistema web desarrollado para digitalizar y automatizar los procesos crediticios y de ahorro de la Cooperativa de Ahorro y Crédito CREDISOL, aplicando los paradigmas de **Programación Funcional** y **Programación Reactiva**.

## Descripción del Proyecto

CREDISOL es una plataforma web que permite gestionar de forma digital el ciclo completo de un préstamo cooperativo: desde la solicitud del cliente, pasando por la evaluación del asesor de crédito, hasta la aprobación final y el desembolso por parte del administrador. El sistema incluye además gestión de cuentas de ahorro, cronogramas de pago, notificaciones automáticas y reportes financieros en tiempo real.

## Tecnologías Utilizadas

- **PHP** — Lenguaje de programación backend
- **MySQL** — Base de datos relacional
- **JavaScript** — Interactividad y reactividad del frontend
- **HTML5 / CSS3** — Estructura y estilos
- **XAMPP** — Entorno de desarrollo local (Apache + MySQL + PHP)
- **Chart.js** — Gráficos de reportes financieros
- **ngrok** — Despliegue temporal para pruebas remotas

## Patrones de Diseño Aplicados

| Patrón | Ubicación | Función |
|---|---|---|
| Singleton | `config/conexion.php` | Conexión única a base de datos |
| Factory Method | `controllers/AuthController.php` | Sesión según rol de usuario |
| Observer | `controllers/DocumentoController.php` | Notificaciones automáticas |
| Strategy | `views/asesor/solicitudes.php` | Evaluación crediticia por score |
| Decorator | `controllers/SolicitudController.php` | Validaciones en capas |
| Pipes & Filters | Flujo de estados | 7 etapas del proceso crediticio |

## Programación Funcional y Reactiva

- **Funcional:** funciones puras en `helpers/funciones.php` (`soles()`, `fechaCorta()`, `limpiar()`)
- **Reactiva:** notificaciones automáticas, polling en tiempo real, mora automática (`actualizarMora()`)

## Vistas del Sistema

### Cliente
- Solicitud de préstamos
- Seguimiento de solicitudes en tiempo real
- Cuenta de ahorros
- Cronograma de pagos
- Comprobantes imprimibles

### Asesor de Crédito
- Evaluación de solicitudes asignadas
- Historial crediticio del cliente
- Gestión de documentos
- Mensajería con el cliente

### Administrador
- Aprobaciones finales
- Registro de desembolsos
- Gestión de ahorros y pagos
- Reportes financieros con gráficos
- Búsqueda global

## Estructura del Proyecto

```
cooperativa/
├── config/
│   └── conexion.php
├── controllers/
│   ├── AuthController.php
│   ├── SolicitudController.php
│   └── DocumentoController.php
├── helpers/
│   ├── funciones.php
│   └── notificaciones.php
├── views/
│   ├── cliente/
│   ├── asesor/
│   └── admin/
├── public/
│   ├── img/
│   └── uploads/
└── index.php
```

## Instalación Local

1. Clonar el repositorio dentro de la carpeta `htdocs` de XAMPP:
```bash
git clone https://github.com/Juan1920-22/sistema-credisol.git
```

2. Importar la base de datos `cooperativa_db.sql` mediante phpMyAdmin

3. Iniciar Apache y MySQL desde el panel de XAMPP

4. Acceder al sistema en:
```
http://localhost/cooperativa/
```

## Equipo de Desarrollo

| Integrante | Rol |
|---|---|
| Fernández Martínez Juan Eduardo | Líder de Proyecto / Backend |
| Cama Auris Jefferson | Backend Developer |
| Castilla Tejada Fabian Alexander | Frontend Developer |
| Espinoza Salvador Luz Angelica | QA / Documentación |

## Metodología

Desarrollo en **Cascada (Waterfall)** con arquitectura **MVC** adaptada.

---

Proyecto académico desarrollado en 2026.