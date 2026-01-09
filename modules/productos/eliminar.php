<?php
include '../../includes/header.php';
require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$mensaje = '';
$tipo_mensaje = '';

// Obtener datos del producto
$producto = null;
if (isset($_GET['id'])) {
    $query = "SELECT * FROM productos WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['id']);
    $stmt->execute();
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Si no existe el producto, redirigir
if (!$producto) {
    header("Location: listar.php");
    exit;
}

// Procesar la eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar'])) {
    try {
        // Verificar si hay movimientos de inventario asociados
        $query_check = "SELECT COUNT(*) as total FROM movimientos_inventario WHERE producto_id = :id";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':id', $_GET['id']);
        $stmt_check->execute();
        $movimientos = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($movimientos['total'] > 0) {
            // Si hay movimientos, verificar si el usuario marcó la opción de eliminar movimientos
            if (!isset($_POST['eliminar_movimientos']) || $_POST['eliminar_movimientos'] != '1') {
                $mensaje = "No se puede eliminar el producto porque tiene movimientos de inventario asociados. Debe marcar la opción para eliminar también los movimientos.";
                $tipo_mensaje = "error";
                goto show_form; // Saltar a mostrar el formulario
            } else {
                // Eliminar movimientos primero
                $query_delete_movimientos = "DELETE FROM movimientos_inventario WHERE producto_id = :id";
                $stmt_movimientos = $db->prepare($query_delete_movimientos);
                $stmt_movimientos->bindParam(':id', $_GET['id']);
                $stmt_movimientos->execute();
            }
        }
        
        // Eliminar el producto
        $query = "DELETE FROM productos WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            $mensaje = "Producto eliminado exitosamente!";
            $tipo_mensaje = "success";
            
            // Redirigir después de 2 segundos
            echo '<script>
                setTimeout(function() {
                    window.location.href = "listar.php";
                }, 2000);
            </script>';
        } else {
            $mensaje = "Error al eliminar el producto.";
            $tipo_mensaje = "error";
        }
    } catch(PDOException $exception) {
        $mensaje = "Error: " . $exception->getMessage();
        $tipo_mensaje = "error";
    }
}

show_form:
?>

<div class="container">
    <h2>Eliminar Producto</h2>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>">
            <?php echo $mensaje; ?>
            <?php if ($tipo_mensaje == 'success'): ?>
                <p>Redirigiendo a la lista de productos...</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!isset($_POST['confirmar']) || $tipo_mensaje == 'error'): ?>
    <div class="confirmation-card">
        <div class="warning-icon">
            ⚠️
        </div>
        
        <h3>¿Está seguro de eliminar este producto?</h3>
        
        <div class="product-details">
            <h4>Información del Producto:</h4>
            <div class="details-grid">
                <div class="detail-item">
                    <label>Código de Barras:</label>
                    <span><?php echo htmlspecialchars($producto['codigo_barras']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Nombre:</label>
                    <span><?php echo htmlspecialchars($producto['nombre']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Stock Actual:</label>
                    <span class="stock-number"><?php echo $producto['stock_actual']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Precio Venta:</label>
                    <span>$<?php echo number_format($producto['precio_venta'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <?php
        // Verificar si hay movimientos de inventario
        $query_movimientos = "SELECT COUNT(*) as total FROM movimientos_inventario WHERE producto_id = :id";
        $stmt_movimientos = $db->prepare($query_movimientos);
        $stmt_movimientos->bindParam(':id', $_GET['id']);
        $stmt_movimientos->execute();
        $total_movimientos = $stmt_movimientos->fetch(PDO::FETCH_ASSOC)['total'];
        ?>
        
        <form method="post" action="" class="confirmation-form" id="deleteForm">
            <?php if ($total_movimientos > 0): ?>
            <div class="movimientos-warning">
                <h4>⚠️ Advertencia Importante</h4>
                <p>Este producto tiene <strong><?php echo $total_movimientos; ?> movimiento(s)</strong> de inventario registrados.</p>
                
                <div class="warning-option">
                    <label>
                        <input type="checkbox" name="eliminar_movimientos" id="eliminar_movimientos" value="1" required>
                        Entiendo que también se eliminarán todos los movimientos de inventario asociados a este producto.
                    </label>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="confirmation-actions">
                <button type="submit" name="confirmar" value="1" class="btn btn-danger" id="deleteButton">
                    Sí, Eliminar Producto
                </button>
                <a href="listar.php" class="btn">Cancelar</a>
                <a href="editar.php?id=<?php echo $producto['id']; ?>" class="btn btn-warning">
                    Editar en lugar de Eliminar
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
// Validación del checkbox
document.addEventListener('DOMContentLoaded', function() {
    const deleteForm = document.getElementById('deleteForm');
    const eliminarCheckbox = document.getElementById('eliminar_movimientos');
    const deleteButton = document.getElementById('deleteButton');
    
    if (deleteForm && eliminarCheckbox) {
        deleteForm.addEventListener('submit', function(e) {
            if (!eliminarCheckbox.checked) {
                e.preventDefault();
                alert('Debe marcar la casilla de confirmación para eliminar los movimientos asociados.');
                return false;
            }
            
            if (!confirm('¿Está completamente seguro de eliminar este producto y todos sus movimientos de inventario? Esta acción no se puede deshacer.')) {
                e.preventDefault();
                return false;
            }
        });
    } else if (deleteButton) {
        // Si no hay movimientos, solo mostrar confirmación simple
        deleteButton.addEventListener('click', function(e) {
            if (!confirm('¿Está completamente seguro de eliminar este producto? Esta acción no se puede deshacer.')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<style>
.confirmation-card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 5px solid #dc3545;
}

.warning-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.confirmation-card h3 {
    color: #dc3545;
    margin-bottom: 2rem;
}

.product-details {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    text-align: left;
}

.product-details h4 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1rem;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item label {
    font-weight: bold;
    color: #666;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.detail-item span {
    color: #333;
    font-size: 1rem;
}

.stock-number {
    font-weight: bold;
    color: #dc3545;
}

.movimientos-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
    text-align: left;
}

.movimientos-warning h4 {
    color: #856404;
    margin-top: 0;
}

.movimientos-warning p {
    color: #856404;
    margin-bottom: 1rem;
}

.warning-option {
    background: white;
    padding: 1rem;
    border-radius: 4px;
}

.warning-option label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    color: #856404;
    font-weight: bold;
}

.warning-option input[type="checkbox"] {
    margin-top: 0.2rem;
}

.confirmation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.confirmation-actions .btn {
    flex: 1;
    max-width: 200px;
}
</style>

<?php include '../../includes/footer.php'; ?>