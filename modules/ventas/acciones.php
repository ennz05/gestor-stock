<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents('php://input'), true);

$response = ['success' => false, 'error' => ''];

try {
    switch ($action) {
        case 'completar':
            if (isset($data['id'])) {
                $venta_id = intval($data['id']);
                
                // Verificar que la venta exista y esté pendiente
                $query = "SELECT estado FROM ventas WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $venta_id);
                $stmt->execute();
                $venta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($venta && $venta['estado'] == 'pendiente') {
                    $update = "UPDATE ventas SET estado = 'completada' WHERE id = :id";
                    $stmt_update = $db->prepare($update);
                    $stmt_update->bindParam(':id', $venta_id);
                    $stmt_update->execute();
                    
                    $response['success'] = true;
                } else {
                    $response['error'] = 'Venta no encontrada o ya no está pendiente';
                }
            }
            break;
            
        case 'cancelar':
            if (isset($data['id'])) {
                $venta_id = intval($data['id']);
                
                $db->beginTransaction();
                
                // 1. Obtener detalles de la venta para restaurar stock
                $query_detalles = "SELECT producto_id, cantidad FROM ventas_detalle WHERE venta_id = :id";
                $stmt_detalles = $db->prepare($query_detalles);
                $stmt_detalles->bindParam(':id', $venta_id);
                $stmt_detalles->execute();
                $detalles = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);
                
                // 2. Restaurar stock de cada producto
                foreach ($detalles as $detalle) {
                    $query_restore = "UPDATE productos SET stock_actual = stock_actual + :cantidad WHERE id = :producto_id";
                    $stmt_restore = $db->prepare($query_restore);
                    $stmt_restore->bindParam(':cantidad', $detalle['cantidad']);
                    $stmt_restore->bindParam(':producto_id', $detalle['producto_id']);
                    $stmt_restore->execute();
                    
                    // Registrar movimiento de ajuste
                    $query_movimiento = "INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, motivo, usuario) 
                                        VALUES (:producto_id, 'entrada', :cantidad, 'Cancelación venta #{$venta_id}', 'Sistema')";
                    $stmt_movimiento = $db->prepare($query_movimiento);
                    $stmt_movimiento->bindParam(':producto_id', $detalle['producto_id']);
                    $stmt_movimiento->bindParam(':cantidad', $detalle['cantidad']);
                    $stmt_movimiento->execute();
                }
                
                // 3. Marcar venta como cancelada
                $query_cancel = "UPDATE ventas SET estado = 'cancelada' WHERE id = :id";
                $stmt_cancel = $db->prepare($query_cancel);
                $stmt_cancel->bindParam(':id', $venta_id);
                $stmt_cancel->execute();
                
                $db->commit();
                $response['success'] = true;
            }
            break;
            
        default:
            $response['error'] = 'Acción no válida';
    }
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $response['error'] = 'Error en la base de datos: ' . $e->getMessage();
}

echo json_encode($response);
?>