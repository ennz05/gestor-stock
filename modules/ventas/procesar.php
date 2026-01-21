<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$functions = new Functions($db);

// Verificar caja abierta
$caja_abierta = $functions->isCajaAbierta();
if (!$caja_abierta) {
    header("Location: ../caja/apertura.php");
    exit;
}

// Verificar que hay productos en el carrito
if (!isset($_SESSION['carrito']) || empty($_SESSION['carrito'])) {
    $_SESSION['error_venta'] = "❌ El carrito está vacío. No hay productos para vender.";
    header("Location: nueva.php");
    exit;
}

// Verificar que todos los productos tengan stock suficiente
$errores_stock = [];
foreach ($_SESSION['carrito'] as $item) {
    $query = "SELECT stock_actual, nombre FROM productos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $item['id']);
    $stmt->execute();
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto['stock_actual'] < $item['cantidad']) {
        $errores_stock[] = "{$producto['nombre']}: Stock disponible {$producto['stock_actual']}, solicitado {$item['cantidad']}";
    }
}

if (!empty($errores_stock)) {
    $_SESSION['error_venta'] = "❌ Error de stock:<br>" . implode("<br>", $errores_stock);
    header("Location: nueva.php");
    exit;
}

// Procesar la venta
try {
    $db->beginTransaction();
    
    // Obtener datos del formulario
    $cliente = isset($_POST['cliente']) ? trim($_POST['cliente']) : 'CLIENTE GENERAL';
    $metodo_pago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'efectivo';
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    $vendedor = $functions->getUsuarioDefault();
    $caja_id = $caja_abierta['id'];
    
    // Calcular total
    $total_venta = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $total_venta += $item['subtotal'];
    }
    
    // 1. Crear registro de venta (encabezado)
    $query_venta = "INSERT INTO ventas (cliente, total, vendedor, metodo_pago, observaciones, caja_id) 
                   VALUES (:cliente, :total, :vendedor, :metodo_pago, :observaciones, :caja_id)";
    
    $stmt_venta = $db->prepare($query_venta);
    $stmt_venta->bindParam(':cliente', $cliente);
    $stmt_venta->bindParam(':total', $total_venta);
    $stmt_venta->bindParam(':vendedor', $vendedor);
    $stmt_venta->bindParam(':metodo_pago', $metodo_pago);
    $stmt_venta->bindParam(':observaciones', $observaciones);
    $stmt_venta->bindParam(':caja_id', $caja_id);
    $stmt_venta->execute();
    
    $venta_id = $db->lastInsertId();
    
    // 2. Insertar detalles de venta (el trigger se encargará del stock)
    $query_detalle = "INSERT INTO ventas_detalle (venta_id, producto_id, cantidad, precio_unitario, subtotal) 
                     VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :subtotal)";
    $stmt_detalle = $db->prepare($query_detalle);
    
    // 3. Registrar en movimientos_inventario para cada producto
    $query_movimiento = "INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, motivo, usuario) 
                        VALUES (:producto_id, 'salida', :cantidad, :motivo, :usuario)";
    $stmt_movimiento = $db->prepare($query_movimiento);
    
    // 4. Actualizar caja (sumar a las ventas del día)
    $query_actualizar_caja = "UPDATE caja SET monto_final = COALESCE(monto_final, monto_inicial) + :monto 
                             WHERE id = :caja_id";
    $stmt_caja = $db->prepare($query_actualizar_caja);
    $stmt_caja->bindParam(':monto', $total_venta);
    $stmt_caja->bindParam(':caja_id', $caja_id);
    
    foreach ($_SESSION['carrito'] as $item) {
        // Insertar detalle
        $stmt_detalle->bindParam(':venta_id', $venta_id);
        $stmt_detalle->bindParam(':producto_id', $item['id']);
        $stmt_detalle->bindParam(':cantidad', $item['cantidad']);
        $stmt_detalle->bindParam(':precio_unitario', $item['precio']);
        $stmt_detalle->bindParam(':subtotal', $item['subtotal']);
        $stmt_detalle->execute();
        
        // Registrar movimiento
        $motivo_movimiento = "Venta múltiple #$venta_id";
        $stmt_movimiento->bindParam(':producto_id', $item['id']);
        $stmt_movimiento->bindParam(':cantidad', $item['cantidad']);
        $stmt_movimiento->bindParam(':motivo', $motivo_movimiento);
        $stmt_movimiento->bindParam(':usuario', $vendedor);
        $stmt_movimiento->execute();
    }
    
    // Actualizar caja
    $stmt_caja->execute();
    
    // Confirmar transacción
    $db->commit();
    
    // Guardar datos para el ticket
    $_SESSION['venta_procesada'] = [
        'id' => $venta_id,
        'fecha' => date('Y-m-d H:i:s'),
        'cliente' => $cliente,
        'total' => $total_venta,
        'metodo_pago' => $metodo_pago,
        'vendedor' => $vendedor,
        'items' => $_SESSION['carrito']
    ];
    
    // Limpiar carrito
    $_SESSION['carrito'] = [];
    
    // Redirigir al ticket
    header("Location: ticket.php?id=$venta_id");
    exit;
    
} catch (PDOException $e) {
    // Revertir transacción en caso de error
    $db->rollBack();
    
    $_SESSION['error_venta'] = "❌ Error al procesar la venta: " . $e->getMessage();
    header("Location: nueva.php");
    exit;
}
?>