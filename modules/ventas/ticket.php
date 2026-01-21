<?php
session_start();
require_once '../../config/database.php';

$venta_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venta_id > 0) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener datos de la venta
    $query = "SELECT v.*, c.monto_inicial, DATE_FORMAT(v.fecha, '%d/%m/%Y %H:%i:%s') as fecha_formateada
              FROM ventas v 
              LEFT JOIN caja c ON v.caja_id = c.id 
              WHERE v.id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $venta_id);
    $stmt->execute();
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener detalles de la venta
    $query_detalles = "SELECT vd.*, p.nombre, p.codigo_barras 
                      FROM ventas_detalle vd 
                      JOIN productos p ON vd.producto_id = p.id 
                      WHERE vd.venta_id = :id 
                      ORDER BY vd.id";
    $stmt_detalles = $db->prepare($query_detalles);
    $stmt_detalles->bindParam(':id', $venta_id);
    $stmt_detalles->execute();
    $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($_SESSION['venta_procesada'])) {
    // Usar datos de sesión si están disponibles
    $venta = $_SESSION['venta_procesada'];
    $detalles = $venta['items'];
    $venta_id = $venta['id'];
} else {
    die("No hay datos de venta para mostrar.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket de Venta #<?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
                padding: 0;
            }
            body {
                width: 80mm;
                margin: 0;
                padding: 5px;
                font-family: 'Courier New', monospace;
                font-size: 12px;
                line-height: 1.2;
            }
            .no-print {
                display: none !important;
            }
            .break-page {
                page-break-after: always;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 5px;
            background: white;
        }
        
        .ticket {
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #000;
        }
        
        .empresa {
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .direccion, .telefono {
            font-size: 10px;
        }
        
        .titulo {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .info-venta {
            margin: 5px 0;
            font-size: 11px;
        }
        
        .separador {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        
        th {
            border-bottom: 1px solid #000;
            padding: 3px 2px;
            text-align: left;
            font-weight: bold;
        }
        
        td {
            padding: 3px 2px;
            vertical-align: top;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .total {
            font-weight: bold;
            font-size: 13px;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 10px;
        }
        
        .mensaje {
            font-style: italic;
            text-align: center;
            margin: 10px 0;
            font-size: 10px;
        }
        
        /* Botón de impresión (solo en navegador) */
        .btn-print {
            display: block;
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            margin: 20px 0;
            font-size: 16px;
            cursor: pointer;
            text-align: center;
            border-radius: 8px;
        }
        
        .btn-print:hover {
            background: #218838;
        }
        
        .btn-actions {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }
        
        .btn-action {
            flex: 1;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-action:hover {
            background: #0056b3;
        }
        
        .btn-action.success {
            background: #28a745;
        }
        
        .btn-action.success:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <!-- Botones de acción (solo visible en navegador) -->
    <div class="no-print btn-actions">
        <button class="btn-print" onclick="window.print();">
            <i class="fas fa-print"></i> 🖨️ IMPRIMIR TICKET
        </button>
        
        <a href="nueva.php" class="btn-action success">
            <i class="fas fa-cart-plus"></i> NUEVA VENTA
        </a>
        
        <a href="index.php" class="btn-action">
            <i class="fas fa-history"></i> HISTORIAL
        </a>
    </div>
    
    <div class="ticket">
        <!-- Encabezado del ticket -->
        <div class="header">
            <div class="empresa">MI NEGOCIO</div>
            <div class="direccion">Dirección de tu negocio</div>
            <div class="telefono">Tel: (123) 456-7890</div>
            <div>RUC: 12345678901</div>
        </div>
        
        <!-- Información de la venta -->
        <div class="titulo">TICKET DE VENTA</div>
        
        <div class="info-venta">
            <strong>Fecha:</strong> <?php echo isset($venta['fecha_formateada']) ? $venta['fecha_formateada'] : date('d/m/Y H:i:s'); ?><br>
            <strong>Ticket #:</strong> <?php echo str_pad($venta_id, 6, '0', STR_PAD_LEFT); ?><br>
            <strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente']); ?><br>
            <strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['vendedor']); ?><br>
            <strong>Pago:</strong> <?php echo strtoupper($venta['metodo_pago']); ?>
        </div>
        
        <div class="separador"></div>
        
        <!-- Detalles de productos -->
        <table>
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Descripción</th>
                    <th class="text-right">P.U.</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_general = 0;
                foreach ($detalles as $detalle): 
                    $total_general += $detalle['subtotal'];
                    $nombre = isset($detalle['nombre']) ? $detalle['nombre'] : $detalle['nombre'];
                ?>
                <tr>
                    <td><?php echo $detalle['cantidad']; ?></td>
                    <td><?php echo substr($nombre, 0, 20); ?><?php if (strlen($nombre) > 20): ?>...<?php endif; ?></td>
                    <td class="text-right">$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                    <td class="text-right">$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="separador"></div>
        
        <!-- Total -->
        <table>
            <tr>
                <td class="text-right"><strong>TOTAL:</strong></td>
                <td class="text-right total">$<?php echo number_format($venta['total'], 2); ?></td>
            </tr>
        </table>
        
        <!-- Método de pago -->
        <div style="margin-top: 10px; text-align: center; font-size: 11px;">
            <strong>Método de pago:</strong> <?php echo strtoupper($venta['metodo_pago']); ?><br>
            <?php if ($venta['metodo_pago'] == 'efectivo'): ?>
            <strong>¡Gracias por su compra!</strong>
            <?php endif; ?>
        </div>
        
        <!-- Mensaje -->
        <div class="mensaje">
            ¡Gracias por su compra!<br>
            Vuelva pronto
        </div>
        
        <!-- Pie de página -->
        <div class="footer">
            <div>--------------------------------</div>
            <div>Software: Gestor de Stock v2.0</div>
            <div><?php echo date('d/m/Y H:i:s'); ?></div>
            <div>www.minegocio.com</div>
        </div>
    </div>
    
    <script>
        // Auto-imprimir al cargar en ventana nueva
        if (window.opener) {
            setTimeout(function() {
                window.print();
            }, 500);
        }
        
        // Cerrar ventana después de imprimir (opcional)
        window.onafterprint = function() {
            // setTimeout(function() { window.close(); }, 500);
        };
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // ESC para cerrar
            if (e.key === 'Escape') {
                window.close();
            }
            // Ctrl+P para imprimir
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            // F5 para nueva venta
            if (e.key === 'F5') {
                e.preventDefault();
                window.location.href = 'nueva.php';
            }
        });
    </script>
</body>
</html>
<?php
// Limpiar datos de sesión después de mostrar
if (isset($_SESSION['venta_procesada'])) {
    unset($_SESSION['venta_procesada']);
}
?>