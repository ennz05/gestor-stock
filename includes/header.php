<?php
// No iniciamos sesión aquí para evitar conflictos
// Cada archivo que incluya header debe manejar su propia sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Stock</title>
    <link rel="stylesheet" href="/gestor-stock/css/styles.css">
</head>
<body>
    <header>
        <nav>
            <div class="nav-header">
                <h1>Sistema de Gestión de Stock</h1>
                <?php if (isset($_SESSION['caja_abierta']) && $_SESSION['caja_abierta']): ?>
                    <div class="caja-status open">
                        💰 Caja Abierta
                    </div>
                <?php else: ?>
                    <div class="caja-status closed">
                        🔒 Caja Cerrada
                    </div>
                <?php endif; ?>
            </div>
            <ul>
                <li><a href="/gestor-stock/index.php">🏠 Inicio</a></li>
                <li class="dropdown">
                    <a href="#">📦 Productos ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="/gestor-stock/modules/productos/listar.php">Listar Productos</a></li>
                        <li><a href="/gestor-stock/modules/productos/agregar.php">Agregar Producto</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#">📊 Inventario ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="/gestor-stock/modules/inventario/entrada.php">Entrada</a></li>
                        <li><a href="/gestor-stock/modules/inventario/salida.php">Salida</a></li>
                        <li><a href="/gestor-stock/modules/inventario/ajuste.php">Ajuste</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#">📈 Reportes ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="/gestor-stock/modules/reportes/stock.php">Stock Actual</a></li>
                        <li><a href="/gestor-stock/modules/reportes/movimientos.php">Movimientos</a></li>
                    </ul>
                </li>
                <li class="dropdown">
                    <a href="#">⚙️ Sistema ▾</a>
                    <ul class="dropdown-menu">
                        <li><a href="/gestor-stock/modules/configuracion/porcentaje.php">Configurar % Ganancia</a></li>
                        <li><a href="/gestor-stock/modules/caja/apertura.php">Abrir Caja</a></li>
                        <li><a href="/gestor-stock/modules/caja/cierre.php">Cerrar Caja</a></li>
                    </ul>
                </li>
                <li><a href="/gestor-stock/backups/backup.php?action=backup" onclick="return confirm('¿Generar backup de la base de datos?')">💾 Backup</a></li>
            </ul>
        </nav>
    </header>
    <main>